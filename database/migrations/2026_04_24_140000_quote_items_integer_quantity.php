<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DATA-01 (sprint 2026-04-24): align quote_items.quantity with
 * invoice_items.quantity — both must be positive integers.
 *
 * The fresh review surfaced that QuoteItem.quantity was cast decimal:2
 * on the model, while InvoiceItem.quantity is integer (migration
 * 2026_04_10_160100). A fractional quote quantity (e.g. 1.5) silently
 * truncated to 1 when createFromQuote materialised InvoiceItems. This
 * migration closes the drift by applying the same pattern that was
 * already used for invoice_items.
 *
 * Migration strategy:
 *   1. Ceil existing fractional quantities so data survives the type
 *      change without under-billing.
 *   2. Alter the Postgres column to INTEGER with default 1.
 *   3. Add a CHECK constraint `quantity > 0` matching invoice_items.
 *
 * SQLite note: SQLite doesn't enforce column types strictly and
 * ALTER TABLE ALTER COLUMN isn't portable. The application-side cast
 * (`'quantity' => 'integer'` in QuoteItem) covers the test path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE quote_items SET quantity = CEIL(quantity) WHERE quantity != FLOOR(quantity)');
            DB::statement('ALTER TABLE quote_items ALTER COLUMN quantity TYPE INTEGER USING quantity::INTEGER');
            DB::statement('ALTER TABLE quote_items ALTER COLUMN quantity SET DEFAULT 1');

            DB::statement('ALTER TABLE quote_items DROP CONSTRAINT IF EXISTS chk_quote_item_qty');
            DB::statement('ALTER TABLE quote_items ADD CONSTRAINT chk_quote_item_qty CHECK (quantity > 0)');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // SQLite: coerce fractional values in existing rows so the
            // model cast to integer doesn't surprise readers.
            DB::statement('UPDATE quote_items SET quantity = CAST(quantity AS INTEGER) WHERE quantity != CAST(quantity AS INTEGER)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE quote_items DROP CONSTRAINT IF EXISTS chk_quote_item_qty');
        DB::statement('ALTER TABLE quote_items ALTER COLUMN quantity TYPE DECIMAL(8, 2) USING quantity::DECIMAL(8, 2)');
        DB::statement('ALTER TABLE quote_items ALTER COLUMN quantity SET DEFAULT 1');
    }
};
