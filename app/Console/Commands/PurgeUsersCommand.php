<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeUsersCommand extends Command
{
    protected $signature = 'crm:purge-users 
                            {--tenant=all : Tenant ID or "all" for all tenants}
                            {--except-owner : Keep tenant owners}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Safely soft-delete users while preserving superadmins and tenant owners';

    public function handle(): int
    {
        $tenantOption = $this->option('tenant');
        $exceptOwner = $this->option('except-owner');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Get tenants to process
        $tenants = $tenantOption === 'all'
            ? Tenant::all()
            : Tenant::where('id', $tenantOption)->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return Command::FAILURE;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...\n");

        $totalDeleted = 0;
        $totalProtected = 0;

        foreach ($tenants as $tenant) {
            $result = $this->processTenant($tenant, $exceptOwner, $dryRun);
            $totalDeleted += $result['deleted'];
            $totalProtected += $result['protected'];
        }

        // Summary
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Soft-Deleted', $totalDeleted],
                ['Total Protected', $totalProtected],
            ]
        );

        if ($dryRun) {
            $this->warn("\n⚠️  DRY RUN - No users were actually deleted.");
            $this->info("Run without --dry-run to apply changes.");
        }

        return Command::SUCCESS;
    }

    protected function processTenant(Tenant $tenant, bool $exceptOwner, bool $dryRun): array
    {
        $this->info("📦 Tenant: {$tenant->name} (ID: {$tenant->id})");

        // Get all users for this tenant
        $query = User::where('tenant_id', $tenant->id);

        // Get protected user IDs
        $protectedIds = $this->getProtectedUserIds($tenant, $exceptOwner);

        // Users to delete (exclude protected)
        $usersToDelete = $query->whereNotIn('id', $protectedIds)->get();

        $this->line("   Protected users: " . count($protectedIds));
        $this->line("   Users to delete: " . $usersToDelete->count());

        if ($usersToDelete->isEmpty()) {
            $this->line("   ✓ No users to delete\n");
            return ['deleted' => 0, 'protected' => count($protectedIds)];
        }

        if (!$dryRun) {
            DB::transaction(function () use ($usersToDelete, $tenant) {
                foreach ($usersToDelete as $user) {
                    // Handle foreign key references before deletion
                    $this->reassignDependencies($user, $tenant);

                    // Soft delete
                    $user->delete();
                    $this->line("   🗑️  Deleted: {$user->name} ({$user->email})");
                }
            });
        } else {
            foreach ($usersToDelete as $user) {
                $this->line("   [DRY] Would delete: {$user->name} ({$user->email})");
            }
        }

        $this->newLine();

        return [
            'deleted' => $usersToDelete->count(),
            'protected' => count($protectedIds),
        ];
    }

    protected function getProtectedUserIds(Tenant $tenant, bool $exceptOwner): array
    {
        $protectedIds = [];

        // 1. Always protect super-admin users (global)
        $superAdmins = User::role('super-admin')->pluck('id')->toArray();
        $protectedIds = array_merge($protectedIds, $superAdmins);

        // 2. Protect tenant owner (first admin user for this tenant)
        if ($exceptOwner) {
            $tenantOwner = User::where('tenant_id', $tenant->id)
                ->role('admin')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($tenantOwner) {
                $protectedIds[] = $tenantOwner->id;
            }
        }

        return array_unique($protectedIds);
    }

    protected function reassignDependencies(User $user, Tenant $tenant): void
    {
        // Find replacement user (tenant owner or first admin)
        $replacement = User::where('tenant_id', $tenant->id)
            ->where('id', '!=', $user->id)
            ->role('admin')
            ->first();

        $replacementId = $replacement?->id;

        // Tables with user foreign keys to handle
        $dependencies = [
            ['table' => 'work_orders', 'columns' => ['technician_id', 'created_by']],
            ['table' => 'invoices', 'columns' => ['created_by', 'issued_by', 'voided_by']],
            ['table' => 'checkins', 'columns' => ['created_by']],
            ['table' => 'audit_logs', 'columns' => ['user_id']],
            ['table' => 'stock_movements', 'columns' => ['user_id']],
        ];

        foreach ($dependencies as $dep) {
            foreach ($dep['columns'] as $column) {
                // Check if column exists (some might not)
                if (!DB::getSchemaBuilder()->hasColumn($dep['table'], $column)) {
                    continue;
                }

                $count = DB::table($dep['table'])
                    ->where('tenant_id', $tenant->id)
                    ->where($column, $user->id)
                    ->count();

                if ($count > 0) {
                    // Set to NULL or reassign based on column type
                    $newValue = in_array($column, ['technician_id']) ? null : $replacementId;

                    DB::table($dep['table'])
                        ->where('tenant_id', $tenant->id)
                        ->where($column, $user->id)
                        ->update([$column => $newValue]);

                    $action = $newValue === null ? 'nullified' : "reassigned to user #{$newValue}";
                    $this->line("   📝 {$dep['table']}.{$column}: {$count} records {$action}");
                }
            }
        }
    }
}
