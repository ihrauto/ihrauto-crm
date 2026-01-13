<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDemoDataCommand extends Command
{
    protected $signature = 'crm:clean-demo-data 
                            {--tenant=all : Tenant ID or "all" for all tenants}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--keep-products : Preserve products and services}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clean operational demo data while preserving users and configuration';

    public function handle(): int
    {
        $tenantOption = $this->option('tenant');
        $dryRun = $this->option('dry-run');
        $keepProducts = $this->option('keep-products');
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

        if (!$force && !$dryRun) {
            if (!$this->confirm('⚠️  This will delete operational data. Continue?')) {
                $this->info('Cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info("Processing {$tenants->count()} tenant(s)...\n");

        $totals = [];

        foreach ($tenants as $tenant) {
            $result = $this->processTenant($tenant, $dryRun, $keepProducts);
            foreach ($result as $table => $count) {
                $totals[$table] = ($totals[$table] ?? 0) + $count;
            }
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $rows = [];
        foreach ($totals as $table => $count) {
            $rows[] = [ucfirst(str_replace('_', ' ', $table)), $count];
        }
        $this->table(['Data Type', 'Records Deleted'], $rows);

        if ($dryRun) {
            $this->warn("\n⚠️  DRY RUN - No data was actually deleted.");
        }

        return Command::SUCCESS;
    }

    protected function processTenant(Tenant $tenant, bool $dryRun, bool $keepProducts): array
    {
        $this->info("📦 Tenant: {$tenant->name} (ID: {$tenant->id})");

        $results = [];

        // Order matters - delete in reverse dependency order
        $tables = [
            'payments' => \App\Models\Payment::class,
            'invoice_items' => \App\Models\InvoiceItem::class,
            'invoices' => \App\Models\Invoice::class,
            'work_orders' => \App\Models\WorkOrder::class,
            'checkins' => \App\Models\Checkin::class,
            'appointments' => \App\Models\Appointment::class,
            'tires' => \App\Models\Tire::class,
            'vehicles' => \App\Models\Vehicle::class,
            'customers' => \App\Models\Customer::class,
            'stock_movements' => \App\Models\StockMovement::class,
        ];

        // Optionally include products and services
        if (!$keepProducts) {
            $tables['products'] = \App\Models\Product::class;
            $tables['services'] = \App\Models\Service::class;
        }

        foreach ($tables as $name => $model) {
            if (!class_exists($model)) {
                continue;
            }

            $query = $model::where('tenant_id', $tenant->id);
            $count = $query->count();

            if ($count > 0) {
                if (!$dryRun) {
                    // Use forceDelete if model uses SoftDeletes, else normal delete
                    if (method_exists($model, 'forceDelete')) {
                        $query->forceDelete();
                    } else {
                        $query->delete();
                    }
                    $this->line("   🗑️  {$name}: {$count} records deleted");
                } else {
                    $this->line("   [DRY] {$name}: {$count} records would be deleted");
                }
            }

            $results[$name] = $count;
        }

        // Clear audit logs (optional - handled separately)
        $auditCount = DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->count();

        if ($auditCount > 0 && !$dryRun) {
            DB::table('audit_logs')->where('tenant_id', $tenant->id)->delete();
            $this->line("   🗑️  audit_logs: {$auditCount} records deleted");
        } elseif ($auditCount > 0) {
            $this->line("   [DRY] audit_logs: {$auditCount} records would be deleted");
        }
        $results['audit_logs'] = $auditCount;

        $this->newLine();

        return $results;
    }
}
