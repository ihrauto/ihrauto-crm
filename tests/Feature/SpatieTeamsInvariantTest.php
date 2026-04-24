<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * C1 (sprint 2026-04-24): Spatie teams=true. These tests lock the
 * architectural invariants so a future refactor cannot silently
 * regress the tenant-scoping of role assignments.
 */
class SpatieTeamsInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function config_teams_flag_is_enabled(): void
    {
        $this->assertTrue(config('permission.teams'));
        $this->assertSame('tenant_id', config('permission.column_names.team_foreign_key'));
    }

    #[Test]
    public function role_assignment_for_tenant_user_writes_the_tenant_id_on_the_pivot(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $user->assignRole('admin');

        $pivotTenantId = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->value('tenant_id');

        $this->assertSame($tenant->id, (int) $pivotTenantId);
    }

    #[Test]
    public function super_admin_assignment_keeps_team_null_on_the_pivot(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super-admin');

        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('super-admin');

        $pivotTenantId = DB::table('model_has_roles')
            ->where('model_id', $superAdmin->id)
            ->where('model_type', User::class)
            ->value('tenant_id');

        $this->assertNull($pivotTenantId, 'super-admin role assignments must stay global (tenant_id = NULL).');
    }

    #[Test]
    public function user_hasrole_resolves_to_own_tenant_regardless_of_last_registrar_state(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userA->assignRole('admin');

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $userB->assignRole('manager');

        // At this point the registrar's team id is $tenantB->id (side
        // effect of the last assignRole). Calling $userA->hasRole
        // MUST still return true — User::hasRole pushes the user's own
        // team context before deferring to Spatie.
        $this->assertSame($tenantB->id, app(PermissionRegistrar::class)->getPermissionsTeamId());

        $this->assertTrue($userA->hasRole('admin'));
        $this->assertFalse($userA->hasRole('manager'));
        $this->assertTrue($userB->hasRole('manager'));
        $this->assertFalse($userB->hasRole('admin'));
    }

    #[Test]
    public function user_from_tenant_a_does_not_inherit_role_assignment_from_tenant_b(): void
    {
        // This is the whole point of teams=true. Sibling users in two
        // tenants with the "admin" role name should not be able to see
        // each other's pivot rows.
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('admin');

        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $adminB->assignRole('admin');

        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $adminA->id, 'tenant_id' => $tenantA->id,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $adminB->id, 'tenant_id' => $tenantB->id,
        ]);

        // Confirm the two tenant ids are actually different so the
        // test wouldn't trivially pass on a single-tenant fixture.
        $this->assertNotSame($tenantA->id, $tenantB->id);
    }
}
