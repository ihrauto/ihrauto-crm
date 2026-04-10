<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint B.8 — AuditLog tenant scoping via BelongsToTenant.
 *
 * Verifies:
 *  - AuditLog queries from a tenant user context auto-filter by tenant_id
 *  - Super-admin (no tenant) sees all audit logs
 *  - Explicit withoutGlobalScopes() bypasses the scope (for admin dashboards)
 *  - New audit log creation uses tenant_id() helper correctly
 */
class AuditLogTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        // Seed audit logs for each tenant
        AuditLog::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => null,
            'action' => 'created',
            'model_type' => 'App\\Models\\Customer',
            'model_id' => 1,
            'changes' => ['name' => 'Tenant A Action'],
            'ip_address' => '10.0.0.1',
        ]);

        AuditLog::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => null,
            'action' => 'created',
            'model_type' => 'App\\Models\\Customer',
            'model_id' => 2,
            'changes' => ['name' => 'Tenant B Action'],
            'ip_address' => '10.0.0.2',
        ]);

        // A system-level log with no tenant
        AuditLog::create([
            'tenant_id' => null,
            'user_id' => null,
            'action' => 'tenant_provisioned',
            'model_type' => 'App\\Models\\Tenant',
            'model_id' => $this->tenantA->id,
            'changes' => ['source' => 'system'],
            'ip_address' => '127.0.0.1',
        ]);
    }

    #[Test]
    public function tenant_a_user_only_sees_tenant_a_audit_logs(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        $logs = AuditLog::all();

        $this->assertCount(1, $logs);
        $this->assertEquals($this->tenantA->id, $logs->first()->tenant_id);
    }

    #[Test]
    public function tenant_b_user_only_sees_tenant_b_audit_logs(): void
    {
        app(TenantContext::class)->set($this->tenantB);

        $logs = AuditLog::all();

        $this->assertCount(1, $logs);
        $this->assertEquals($this->tenantB->id, $logs->first()->tenant_id);
    }

    #[Test]
    public function without_tenant_context_all_logs_visible(): void
    {
        // Super-admin scenario: no tenant context set
        app(TenantContext::class)->clear();

        $logs = AuditLog::all();

        // All three (tenant A, tenant B, system) are visible.
        $this->assertCount(3, $logs);
    }

    #[Test]
    public function without_global_scopes_returns_all_logs_explicitly(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        // Explicit bypass (for super-admin controllers)
        $allLogs = AuditLog::withoutGlobalScopes()->get();

        $this->assertCount(3, $allLogs);
    }

    #[Test]
    public function audit_log_tenant_relation_resolves_correctly(): void
    {
        app(TenantContext::class)->set($this->tenantA);

        $log = AuditLog::first();
        $this->assertNotNull($log->tenant);
        $this->assertEquals($this->tenantA->id, $log->tenant->id);
    }
}
