<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetCRMData extends Command
{
    protected $signature = 'crm:reset-data';

    protected $description = 'Wipes all Customers, Vehicles, Invoices, and operational data. KEEPS Admin Users.';

    public function handle()
    {
        if (! $this->confirm('This will wipe ALL Clients, Vehicles, Finance, and Job data. Users/Tenants will remain. Continue?', true)) {
            return;
        }

        $this->info('Reseting CRM Data...');

        Schema::disableForeignKeyConstraints();

        $tables = [
            'customers',
            'vehicles',
            'checkins',
            'invoices',
            'invoice_items',
            'payments',
            'quotes',
            'quote_items',
            'work_orders',
            'appointments',
            'tires',
            'stock_movements',
            'storage_section_tire', // Pivot table if exists? Or just rely on tires table
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line(" - Truncated: $table");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->info('CRM Data Reset Complete. You can now start from zero.');
    }
}
