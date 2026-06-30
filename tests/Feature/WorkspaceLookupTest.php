<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Global workspace lookup (/find-workspace): resolve a tenant by email and
 * forward to its path-based login. PUBLIC, pre-tenant — the lookup runs scope
 * free across all tenants.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class WorkspaceLookupTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    private function makeUser(Tenant $tenant, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'User of '.$tenant->slug,
            'email' => $email,
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    public function test_single_match_redirects_to_that_tenants_login(): void
    {
        $t = $this->makeTenant('acme');
        $this->makeUser($t, 'owner@acme.test');
        app()->forgetInstance('current_tenant');

        $this->post('/find-workspace', ['email' => 'owner@acme.test'])
            ->assertRedirect('/app/acme/login');
    }

    public function test_multiple_matches_render_a_picker(): void
    {
        $a = $this->makeTenant('acme');
        $b = $this->makeTenant('beta');
        $this->makeUser($a, 'shared@x.test');
        $this->makeUser($b, 'shared@x.test');
        app()->forgetInstance('current_tenant');

        $this->post('/find-workspace', ['email' => 'shared@x.test'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/FindWorkspace')
                ->where('workspaces', fn ($workspaces) => count($workspaces) === 2));
    }

    public function test_no_match_shows_the_not_found_prompt(): void
    {
        $this->makeTenant('acme');
        app()->forgetInstance('current_tenant');

        $this->post('/find-workspace', ['email' => 'nobody@nowhere.test'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/FindWorkspace')
                ->where('notFound', true));
    }

    public function test_lookup_is_rate_limited(): void
    {
        $this->makeTenant('acme');
        app()->forgetInstance('current_tenant');

        for ($i = 0; $i < 10; $i++) {
            $this->post('/find-workspace', ['email' => 'nobody@nowhere.test'])->assertStatus(200);
        }

        $this->post('/find-workspace', ['email' => 'nobody@nowhere.test'])->assertStatus(429);
    }
}
