<?php

namespace Tests\Feature;

use App\Modules\AI\Context\IntelligenceModeContext;
use App\Modules\AI\Models\AiConversation;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AI Assistant — tenant-restricted, two modes, provider fallback.
 *
 * Security-critical surface: the plan module gate (hard block), per-user
 * conversation ownership (within a tenant), and ZERO cross-tenant data exposure
 * in intelligence mode. Exercises the real HTTP stack (TenantMiddleware +
 * auth:tenant + tenant.module:ai + the ai-chat rate limiter).
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class AiAssistantTest extends TestCase
{
    use DatabaseTransactions;

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Active tenant on a plan that does (or does not) include the AI module.
     * ai_provider defaults to 'log' so chats answer with the fixed notice and
     * never call out.
     *
     * @param  list<string>  $modules
     */
    private function makeTenant(string $slug, array $modules = ['ai']): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug,
            'slug' => 'plan-'.$slug.'-'.Str::random(5),
            'price_monthly' => 0,
            'price_annual' => 0,
            'is_active' => true,
            'is_free' => true,
            'trial_days' => 14,
            'modules' => $modules,
            'limits' => [],
            'sort_order' => 1,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
            'settings' => ['ai_provider' => 'log'],
        ]);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'tenant_id' => $tenant->id,
            'name' => 'User '.$email,
            'email' => $email,
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    public function test_module_gate_blocks_when_plan_excludes_ai(): void
    {
        $tenant = $this->makeTenant('noai-co', modules: []);
        $user = $this->makeUser($tenant, 'a@noai.test');

        $this->actingAs($user, 'tenant')
            ->get("/app/{$tenant->slug}/ai")
            ->assertForbidden();
    }

    public function test_module_gate_allows_when_plan_includes_ai(): void
    {
        $tenant = $this->makeTenant('ai-co');
        $user = $this->makeUser($tenant, 'a@ai.test');

        $this->actingAs($user, 'tenant')
            ->get("/app/{$tenant->slug}/ai")
            ->assertOk();
    }

    public function test_chat_persists_messages_and_uses_log_provider_by_default(): void
    {
        $tenant = $this->makeTenant('chat-co');
        $user = $this->makeUser($tenant, 'a@chat.test');

        $response = $this->actingAs($user, 'tenant')
            ->postJson("/app/{$tenant->slug}/ai/chat", [
                'mode' => 'help',
                'message' => 'How do I add a vehicle?',
            ]);

        $response->assertOk()
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.provider', 'log');

        $this->assertStringContainsString('not configured', $response->json('message.content'));

        // One conversation, two messages (user + assistant), all tenant-scoped.
        app()->instance('current_tenant', $tenant);
        $conversation = AiConversation::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('help', $conversation->mode);
        $this->assertSame('How do I add a vehicle?', $conversation->title);
        $this->assertCount(2, $conversation->messages);
    }

    public function test_a_user_cannot_view_another_users_conversation_in_same_tenant(): void
    {
        $tenant = $this->makeTenant('shared-co');
        $alice = $this->makeUser($tenant, 'alice@shared.test');
        $bob = $this->makeUser($tenant, 'bob@shared.test');

        app()->instance('current_tenant', $tenant);
        $aliceConversation = AiConversation::create([
            'user_id' => $alice->id,
            'mode' => AiConversation::MODE_HELP,
            'title' => 'Alice private chat',
        ]);
        $this->forgetGuards();

        // Same tenant, so binding resolves the row — the POLICY (own-user) blocks.
        $this->actingAs($bob, 'tenant')
            ->get("/app/{$tenant->slug}/ai/{$aliceConversation->id}")
            ->assertForbidden();
    }

    public function test_cross_tenant_conversation_is_not_found(): void
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $alice = $this->makeUser($tenantA, 'a@a.test');
        $bob = $this->makeUser($tenantB, 'b@b.test');

        app()->instance('current_tenant', $tenantA);
        $conversationA = AiConversation::create([
            'user_id' => $alice->id,
            'mode' => AiConversation::MODE_HELP,
            'title' => 'A only',
        ]);
        $this->forgetGuards();

        // Tenant B user requesting tenant A's conversation id under B's URL:
        // TenantScope 404s it before any policy runs.
        $this->actingAs($bob, 'tenant')
            ->get("/app/{$tenantB->slug}/ai/{$conversationA->id}")
            ->assertNotFound();
    }

    public function test_intelligence_context_includes_own_data_and_never_another_tenants(): void
    {
        $tenantA = $this->makeTenant('intel-a');
        $tenantB = $this->makeTenant('intel-b');

        // Tenant A fleet.
        app()->instance('current_tenant', $tenantA);
        // Service due within 30 days → the reg number surfaces in the prompt.
        Vehicle::create([
            'registration_number' => 'AAA-111',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 8000,
            'next_service_due' => now()->addDays(10),
        ]);

        // Tenant B fleet (must NEVER appear in A's context).
        app()->instance('current_tenant', $tenantB);
        Vehicle::create([
            'registration_number' => 'BBB-999',
            'make' => 'Ford',
            'model' => 'Ranger',
            'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 9500,
            'next_service_due' => now()->addDays(10),
        ]);

        // Build tenant A's intelligence prompt with A bound.
        app()->instance('current_tenant', $tenantA);
        $prompt = (new IntelligenceModeContext())->systemPrompt($tenantA);

        $this->assertStringContainsString($tenantA->name, $prompt);
        $this->assertStringContainsString('Total vehicles: 1', $prompt);
        $this->assertStringContainsString('Available: 1', $prompt);
        $this->assertStringContainsString('AAA-111', $prompt);   // A's own vehicle (service due)

        // Hard isolation: none of tenant B's data leaks in.
        $this->assertStringNotContainsString('BBB-999', $prompt);
        $this->assertStringNotContainsString($tenantB->name, $prompt);
    }

    public function test_rate_limit_blocks_after_twenty_requests_per_minute(): void
    {
        $tenant = $this->makeTenant('rl-co');
        $user = $this->makeUser($tenant, 'a@rl.test');

        $this->actingAs($user, 'tenant');

        // 20 allowed, the 21st is throttled (LogAiProvider — no external calls).
        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/app/{$tenant->slug}/ai/chat", [
                'mode' => 'help',
                'message' => "ping {$i}",
            ])->assertOk();
        }

        $this->postJson("/app/{$tenant->slug}/ai/chat", [
            'mode' => 'help',
            'message' => 'one too many',
        ])->assertStatus(429);
    }
}
