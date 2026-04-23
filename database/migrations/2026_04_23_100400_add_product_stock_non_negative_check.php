<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B-10: ensure stock_quantity can never drop below zero at the database layer.
 *
 * The application layer already validates this via InvoiceService::process
 * StockDeductions (two-pass lockForUpdate). But a CHECK constraint is the
 * last line of defence against future code paths or ad-hoc SQL that would
 * otherwise produce negative inventory.
 *
 * PostgreSQL only. SQLite is typeless and the tests rely on the application
 * layer; the PG constraint is the one that protects production data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Normalize any pre-existing negative rows (shouldn't happen, but the
        // ADD CONSTRAINT below would fail otherwise).
        DB::statement('UPDATE products SET stock_quantity = 0 WHERE stock_quantity < 0');

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_product_stock_non_negative');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_product_stock_non_negative CHECK (stock_quantity >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_product_stock_non_negative');
    }
};
