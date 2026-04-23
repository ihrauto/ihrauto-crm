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

        /*
         * Bug review OPS-05: on many managed PG hosts (AWS RDS without
         * rds_superuser, Cloud SQL without cloudsqlsuperuser), the app
         * user does NOT have CREATE EXTENSION privilege, even if the
         * extension is safe to enable. We try once and on failure fall
         * back to plain btree indexes on the same columns — slower than
         * GIN trigram but still O(log n) for prefix matches, and the app
         * keeps booting. Ops gets a warning in the migration output.
         */
        $trgmAvailable = true;
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Throwable $e) {
            $trgmAvailable = false;
            // The migration shouldn't fail in this case — we want the app
            // to keep booting and use fallback indexes. Log for ops.
            \Illuminate\Support\Facades\Log::warning(
                'pg_trgm extension could not be created ({$error}); falling back '
                .'to btree indexes. If you have CREATE EXTENSION privilege, run it '
                .'manually: CREATE EXTENSION IF NOT EXISTS pg_trgm;',
                ['error' => $e->getMessage()]
            );
        }

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
            if ($trgmAvailable) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$tableName} USING GIN ({$column} gin_trgm_ops)");
            } else {
                // Fallback: btree. LIKE 'prefix%' still uses it; LIKE
                // '%substring%' degrades to a scan but we at least don't
                // block the migration.
                DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$tableName} ({$column})");
            }
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
