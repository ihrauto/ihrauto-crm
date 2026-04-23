<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetCRMData extends Command
{
    /**
     * D-11: destructive command must match the safety surface of
     * CleanDemoDataCommand / PurgeUsersCommand — both support --dry-run
     * and --force. Without --force, the command refuses to run in
     * production even after interactive confirmation.
     */
    protected $signature = 'crm:reset-data
        {--dry-run : Show what would be truncated without touching data}
        {--force : Confirm destructive reset (required in production)}';

    protected $description = 'Wipes all Customers, Vehicles, Invoices, and operational data. KEEPS Admin Users.';

    /**
     * Tables truncated by this command. Ordered so dependent rows are
     * cleared before parents even though foreign keys are disabled.
     */
    private const RESET_TABLES = [
        'invoice_items',
        'payments',
        'invoices',
        'quote_items',
        'quotes',
        'stock_movements',
        'work_orders',
        'checkins',
        'appointments',
        'tires',
        'vehicles',
        'customers',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no data will be changed.');
            foreach (self::RESET_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    $this->line("  would truncate {$table} ({$count} rows)");
                } else {
                    $this->line("  skip {$table} (table missing)");
                }
            }

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to reset production data without --force.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This will wipe ALL Clients, Vehicles, Finance, and Job data. Users/Tenants will remain. Continue?',
            false // NOT default-yes — prior default was `true`, which is dangerous.
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info('Resetting CRM data...');

        Schema::disableForeignKeyConstraints();

        foreach (self::RESET_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  truncated {$table}");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->info('CRM data reset complete.');

        return self::SUCCESS;
    }
}
