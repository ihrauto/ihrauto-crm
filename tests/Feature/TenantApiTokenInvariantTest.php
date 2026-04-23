<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S-10 — regression tests that lock in the invariants documented on
 * TenantApiToken::findActiveByPlainTextToken(). If any of these fail, the
 * auth path for the tenant API has regressed.
 */
class TenantApiTokenInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_token_cannot_be_used_to_authenticate(): void
    {
        $tenant = Tenant::factory()->create();
        [$token, $plaintext] = TenantApiToken::issue($tenant, 'revoked-test');

        $this->assertNotNull(TenantApiToken::findActiveByPlainTextToken($plaintext));

        $token->revoke();

        $this->assertNull(
            TenantApiToken::findActiveByPlainTextToken($plaintext),
            'Revoked token must not be returned by findActiveByPlainTextToken()'
        );
    }

    public function test_unknown_token_returns_null(): void
    {
        Tenant::factory()->create();

        $this->assertNull(
            TenantApiToken::findActiveByPlainTextToken('tk_completely_invalid_token'),
            'Unknown plain text must resolve to null, not throw or return stale token'
        );
    }

    public function test_empty_or_null_token_returns_null(): void
    {
        $this->assertNull(TenantApiToken::findActiveByPlainTextToken(null));
        $this->assertNull(TenantApiToken::findActiveByPlainTextToken(''));
    }

    public function test_token_lookup_loads_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        [, $plaintext] = TenantApiToken::issue($tenant, 'eager-load-test');

        $resolved = TenantApiToken::findActiveByPlainTextToken($plaintext);

        $this->assertNotNull($resolved);
        $this->assertTrue(
            $resolved->relationLoaded('tenant'),
            'tenant relation must be eager-loaded — the auth middleware depends on it'
        );
        $this->assertSame($tenant->id, $resolved->tenant->id);
    }

    public function test_second_tenants_token_does_not_resolve_first_tenants_record(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        [, $plaintextA] = TenantApiToken::issue($tenantA, 'tenant-a-token');
        [, $plaintextB] = TenantApiToken::issue($tenantB, 'tenant-b-token');

        $resolvedA = TenantApiToken::findActiveByPlainTextToken($plaintextA);
        $resolvedB = TenantApiToken::findActiveByPlainTextToken($plaintextB);

        $this->assertSame($tenantA->id, $resolvedA->tenant->id);
        $this->assertSame($tenantB->id, $resolvedB->tenant->id);
        $this->assertNotSame($resolvedA->id, $resolvedB->id);
    }
}
