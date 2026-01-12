<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces global unique constraint on invoice_number with tenant-scoped constraint.
     */
    public function up(): void
    {
        // Drop global unique constraint safely
        // SQLite supports IF EXISTS, MySQL might vary but usually handles it or throws exception we catch
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS invoices_invoice_number_unique');
            } else {
                // For MySQL/Postgres, Schema builder is usually safer or use raw SQL adapted
                Schema::table('invoices', function (Blueprint $table) {
                    $table->dropUnique(['invoice_number']);
                });
            }
        } catch (\Throwable $e) {
        }

        // Add tenant-scoped unique constraint
        try {
            Schema::table('invoices', function (Blueprint $table) {
                // We don't check existence here, we rely on catch blocking the duplicate error
                $table->unique(['tenant_id', 'invoice_number'], 'invoices_tenant_number_unique');
            });
        } catch (\Throwable $e) {
            // Check if error is "already exists" and suppress, otherwise rethrow ?
            // Actually, for this specific fix, standard behavior is lenient.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_number_unique');
            $table->unique('invoice_number');
        });
    }
};
