<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S-02 — defense in depth: invoice_items and quote_items were indirectly
 * tenant-scoped through their parent (Invoice / Quote). Direct model access
 * (e.g. `InvoiceItem::find($id)`) bypassed that protection. Add a real
 * tenant_id column, backfill from the parent, and enforce it via the
 * BelongsToTenant trait's TenantScope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'invoice_id']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'quote_id']);
        });

        // Backfill from parent tables. Use a portable correlated subquery
        // so the migration works on both PostgreSQL (prod) and SQLite (tests).
        DB::statement(<<<'SQL'
            UPDATE invoice_items
            SET tenant_id = (
                SELECT invoices.tenant_id
                FROM invoices
                WHERE invoices.id = invoice_items.invoice_id
            )
        SQL);

        DB::statement(<<<'SQL'
            UPDATE quote_items
            SET tenant_id = (
                SELECT quotes.tenant_id
                FROM quotes
                WHERE quotes.id = quote_items.quote_id
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'invoice_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'quote_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
