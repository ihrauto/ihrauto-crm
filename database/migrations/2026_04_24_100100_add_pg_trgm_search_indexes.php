<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint A-2 (Scalability 200-tenants, BL-3) — enable PostgreSQL
 * `pg_trgm` extension and add trigram GIN indexes on the hot search
 * columns. Customer / vehicle / invoice / product search currently
 * table-scans at 100k+ rows because it uses `LOWER(col) LIKE '%x%'`.
 *
 * With these indexes PG uses the GIN on any LIKE containing at least 3
 * characters — we get index seeks in 5–20 ms instead of 500 ms–2 s scans.
 *
 * PostgreSQL only. SQLite (tests) skips all of this; the application
 * code still uses LIKE so tests keep working against SQLite's own
 * linear-scan behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // pg_trgm is bundled with Postgres core but not enabled by default.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $indexes = [
            ['customers', 'name', 'customers_name_trgm_idx'],
            ['customers', 'email', 'customers_email_trgm_idx'],
            ['customers', 'phone', 'customers_phone_trgm_idx'],
            ['vehicles', 'license_plate', 'vehicles_plate_trgm_idx'],
            ['vehicles', 'make', 'vehicles_make_trgm_idx'],
            ['vehicles', 'model', 'vehicles_model_trgm_idx'],
            ['invoices', 'invoice_number', 'invoices_number_trgm_idx'],
            ['quotes', 'quote_number', 'quotes_number_trgm_idx'],
            ['products', 'name', 'products_name_trgm_idx'],
            ['products', 'sku', 'products_sku_trgm_idx'],
        ];

        foreach ($indexes as [$tableName, $column, $indexName]) {
            // IF NOT EXISTS lets this migration re-run safely on staging DBs
            // that already have some of these indexes.
            DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$tableName} USING GIN ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $dropNames = [
            'customers_name_trgm_idx',
            'customers_email_trgm_idx',
            'customers_phone_trgm_idx',
            'vehicles_plate_trgm_idx',
            'vehicles_make_trgm_idx',
            'vehicles_model_trgm_idx',
            'invoices_number_trgm_idx',
            'quotes_number_trgm_idx',
            'products_name_trgm_idx',
            'products_sku_trgm_idx',
        ];

        foreach ($dropNames as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }

        // Don't drop the extension itself — other migrations or tenants
        // may use it. Dropping an extension in a shared DB is destructive.
    }
};
