<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scalability C-4 — TenantContext::id() memoizes the auth-user fallback
 * so repeated calls inside a single request don't keep re-reading the
 * User model.
 */
class TenantContextMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_fallback_is_memoized_within_request(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);

        // Force the fallback path: no tenant currently bound.
        $ctx->clear();

        $first = $ctx->id();
        $this->assertSame($tenant->id, $first);

        // Swap auth user mid-request. id() should still return the
        // first-resolved fallback value, proving it's memoized.
        $otherTenant = Tenant::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->actingAs($otherUser);

        $this->assertSame($first, $ctx->id(), 'Fallback should be memoized for the request');
    }

    public function test_clear_resets_the_fallback(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $ctx->clear();

        $ctx->id(); // triggers memo

        // New user after clear — new fallback value.
        $otherTenant = Tenant::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->actingAs($otherUser);
        $ctx->clear();

        $this->assertSame($otherTenant->id, $ctx->id());
    }
}
