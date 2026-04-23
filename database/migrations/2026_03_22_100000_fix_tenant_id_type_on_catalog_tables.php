<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix tenant_id type mismatch on products, services, and stock_movements tables.
     * These tables used string('tenant_id') but tenants.id is bigint.
     * This migration safely converts with data preservation.
     */
    public function up(): void
    {
        // This migration fixes a PostgreSQL-specific type mismatch (string → bigint).
        // SQLite test databases already use the correct types from fresh schema.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $tables = ['products', 'services', 'stock_movements'];

        foreach ($tables as $tableName) {
            // Step 1: Add temporary column
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id_new')->nullable()->after('id');
            });

            // Step 2: Copy data (cast string to bigint)
            DB::statement("UPDATE {$tableName} SET tenant_id_new = CAST(tenant_id AS BIGINT) WHERE tenant_id IS NOT NULL AND tenant_id != ''");

            // Step 3: Drop old column and its index
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("{$tableName}_tenant_id_index");
                $table->dropColumn('tenant_id');
            });

            // Step 4: Rename new column and add FK + index
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('tenant_id_new', 'tenant_id');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $tables = ['products', 'services', 'stock_movements'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(["{$tableName}_tenant_id_foreign"]);
            });

            // Add temporary string column
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('tenant_id_old')->nullable()->after('id');
            });

            DB::statement("UPDATE {$tableName} SET tenant_id_old = CAST(tenant_id AS VARCHAR)");

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("{$tableName}_tenant_id_index");
                $table->dropColumn('tenant_id');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('tenant_id_old', 'tenant_id');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('tenant_id')->nullable(false)->change();
                $table->index('tenant_id');
            });
        }
    }
};
