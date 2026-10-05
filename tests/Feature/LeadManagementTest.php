<?php

namespace Tests\Feature;

use App\Modules\CRM\Events\LeadRejected;
use App\Modules\CRM\Models\Lead;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lead rejection (RejectLeadAction) — a deliberate "not a fit" decision,
 * distinct from the existing expire action (which just kills a stale intake
 * link). DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class LeadManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug).' Rentals',
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    private function makeUser(Tenant $tenant, string $role = TenantUser::ROLE_ADMIN): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'User',
            'email' => $role.'@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'role' => $role,
        ]);
    }

    private function makeLead(Tenant $tenant, array $attrs = []): Lead
    {
        app()->instance('current_tenant', $tenant);

        return Lead::create(array_merge([
            'name' => 'Prospect',
            'email' => 'prospect@example.test',
            'phone' => '0400000000',
            'token' => (string) Str::uuid(),
            'status' => Lead::STATUS_NEW,
            'source' => Lead::SOURCE_LINK,
        ], $attrs));
    }

    public function test_admin_can_reject_a_lead(): void
    {
        Event::fake([LeadRejected::class]);

        $tenant = $this->makeTenant('reject-a');
        $admin = $this->makeUser($tenant);
        $lead = $this->makeLead($tenant);

        $this->actingAs($admin, 'tenant')
            ->post("/app/reject-a/leads/{$lead->id}/reject")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Lead::STATUS_REJECTED, $lead->fresh()->status);
        Event::assertDispatched(LeadRejected::class);
    }

    public function test_a_converted_lead_cannot_be_rejected(): void
    {
        $tenant = $this->makeTenant('reject-b');
        $admin = $this->makeUser($tenant);
        $lead = $this->makeLead($tenant, ['status' => Lead::STATUS_CONVERTED]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/reject-b/leads/{$lead->id}/reject")
            ->assertStatus(422);

        $this->assertSame(Lead::STATUS_CONVERTED, $lead->fresh()->status);
    }

    public function test_rejecting_an_already_rejected_lead_is_idempotent(): void
    {
        $tenant = $this->makeTenant('reject-c');
        $admin = $this->makeUser($tenant);
        $lead = $this->makeLead($tenant, ['status' => Lead::STATUS_REJECTED]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/reject-c/leads/{$lead->id}/reject")
            ->assertRedirect();

        $this->assertSame(Lead::STATUS_REJECTED, $lead->fresh()->status);
    }

    public function test_a_lead_cannot_be_rejected_across_tenants(): void
    {
        $tenantA = $this->makeTenant('reject-d');
        $tenantB = $this->makeTenant('reject-e');
        $adminB = $this->makeUser($tenantB);
        $leadA = $this->makeLead($tenantA);

        $this->actingAs($adminB, 'tenant')
            ->post("/app/reject-e/leads/{$leadA->id}/reject")
            ->assertNotFound();

        $this->assertSame(Lead::STATUS_NEW, $leadA->fresh()->status);
    }

    public function test_rejected_lead_shows_up_in_the_rejected_tab_count(): void
    {
        $tenant = $this->makeTenant('reject-f');
        $admin = $this->makeUser($tenant);
        $this->makeLead($tenant, ['status' => Lead::STATUS_REJECTED]);

        $this->actingAs($admin, 'tenant')
            ->get('/app/reject-f/leads')
            ->assertInertia(fn ($page) => $page->where('counts.rejected', 1));
    }
}
