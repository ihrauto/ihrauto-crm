<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add unique composite index on (tenant_id, sku) for products.
     * Uses a partial index (WHERE sku IS NOT NULL) to allow NULL SKUs.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support partial indexes via Schema builder
            return;
        }

        // Partial unique index: enforce uniqueness only when SKU is not null
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS products_tenant_id_sku_unique ON products (tenant_id, sku) WHERE sku IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS products_tenant_id_sku_unique');
    }
};
