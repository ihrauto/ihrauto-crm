<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C1 (sprint 2026-04-24): upgrade Spatie Laravel Permission from
 * `teams=false` to `teams=true`.
 *
 * The original spatie/permission migration (2026_01_08_150048) ran with
 * teams disabled, so the three pivot tables have no `tenant_id` column.
 * Adding it later — instead of a wipe-and-recreate — preserves every
 * existing role assignment.
 *
 * Schema changes:
 *   - roles                   : add nullable tenant_id + index
 *   - model_has_roles         : add nullable tenant_id + index
 *   - model_has_permissions   : add nullable tenant_id + index
 *
 * Primary keys on the pivot tables are intentionally left alone. Spatie
 * includes `team_foreign_key` in the composite PK when teams=true from
 * the start; but the PK set at creation time by the earlier migration
 * is the `(role_id, model_id, model_type)` tuple. Dropping + recreating
 * a composite PK in a running Postgres table is risky, and our data
 * model (one user = one tenant_id today) keeps the non-PK `tenant_id`
 * consistent without relying on the PK for uniqueness. If we ever allow
 * a single user to hold the same role in two tenants, a follow-up
 * migration must widen the PK.
 *
 * Data backfill:
 *   model_has_roles rows get tenant_id = users.tenant_id for their
 *   model_id. Rows whose user has no tenant (platform super-admins) keep
 *   tenant_id = NULL, which Spatie treats as a global assignment.
 *
 *   model_has_permissions is backfilled the same way; today that table
 *   is empty (all permissions flow through roles) but we backfill
 *   defensively in case the seeder ever writes direct permissions.
 *
 *   Global roles (admin, manager, technician, receptionist, super-admin)
 *   keep tenant_id = NULL — they are available to every tenant.
 *
 * Cache invalidation:
 *   Spatie caches permissions; the cache is tenant-agnostic before this
 *   change. We clear it after the backfill so the first request after
 *   deploy recomputes everything under the new semantics.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columnNames = config('permission.column_names');
        $tableNames = config('permission.table_names');
        $teamFk = $columnNames['team_foreign_key'];

        // --- roles -----------------------------------------------------
        if (! Schema::hasColumn($tableNames['roles'], $teamFk)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamFk) {
                $table->unsignedBigInteger($teamFk)->nullable()->after('id');
                $table->index($teamFk, 'roles_team_foreign_key_index');
            });
        }

        // --- model_has_roles ------------------------------------------
        if (! Schema::hasColumn($tableNames['model_has_roles'], $teamFk)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamFk) {
                $table->unsignedBigInteger($teamFk)->nullable();
                $table->index($teamFk, 'model_has_roles_team_foreign_key_index');
            });
        }

        // --- model_has_permissions ------------------------------------
        if (! Schema::hasColumn($tableNames['model_has_permissions'], $teamFk)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamFk) {
                $table->unsignedBigInteger($teamFk)->nullable();
                $table->index($teamFk, 'model_has_permissions_team_foreign_key_index');
            });
        }

        // --- backfill --------------------------------------------------
        // Roles stay global (tenant_id NULL) intentionally; role assignments
        // inherit the user's tenant_id. We do NOT touch the super-admin
        // user — their User row has tenant_id = NULL in the schema when
        // seeded that way, which keeps their role assignment global.
        DB::table($tableNames['model_has_roles'])
            ->where('model_type', \App\Models\User::class)
            ->whereNull($teamFk)
            ->update([
                $teamFk => DB::raw(
                    '(select tenant_id from users where users.id = '
                    .$tableNames['model_has_roles'].'.model_id)'
                ),
            ]);

        DB::table($tableNames['model_has_permissions'])
            ->where('model_type', \App\Models\User::class)
            ->whereNull($teamFk)
            ->update([
                $teamFk => DB::raw(
                    '(select tenant_id from users where users.id = '
                    .$tableNames['model_has_permissions'].'.model_id)'
                ),
            ]);

        // Spatie caches its permission registrar; force it to forget.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $columnNames = config('permission.column_names');
        $tableNames = config('permission.table_names');
        $teamFk = $columnNames['team_foreign_key'];

        foreach ([$tableNames['roles'], $tableNames['model_has_roles'], $tableNames['model_has_permissions']] as $table) {
            if (Schema::hasColumn($table, $teamFk)) {
                Schema::table($table, function (Blueprint $t) use ($teamFk) {
                    $t->dropColumn($teamFk);
                });
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
