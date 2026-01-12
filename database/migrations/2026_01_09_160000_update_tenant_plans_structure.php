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
     * Updates the subscription plan structure:
     * - Changes plan values from old structure to new (basic, standard, custom)
     * - Adds max_work_orders column for BASIC plan monthly limit
     */
    public function up(): void
    {
        // Add max_work_orders column for BASIC plan monthly limit
        Schema::table('tenants', function (Blueprint $table) {
            $table->integer('max_work_orders')->nullable()->after('max_vehicles');
        });

        // Update existing tenants with appropriate max_work_orders based on plan
        // Note: 'basic' stays as 'basic', so we just need to set the limit
        DB::table('tenants')->where('plan', 'basic')->update(['max_work_orders' => 50]);

        // For SQLite compatibility, we need to handle the plan column differently
        // Since SQLite has CHECK constraints on enums, and the existing data already
        // uses 'basic', 'standard', 'custom' values (as seen in TenantSeeder),
        // we only need to update legacy values if they exist

        // Map old plan names to new ones (if any old data exists)
        // free -> basic, premium -> standard, enterprise -> custom
        // These updates will fail silently if the plan values don't match the constraint
        try {
            DB::table('tenants')->where('plan', 'free')->update(['plan' => 'basic', 'max_work_orders' => 50]);
        } catch (\Exception $e) {
            // Ignore - plan value may not exist or constraint prevents it
        }

        try {
            DB::table('tenants')->where('plan', 'premium')->update(['plan' => 'standard', 'max_work_orders' => null]);
        } catch (\Exception $e) {
            // Ignore - plan value may not exist or constraint prevents it
        }

        try {
            DB::table('tenants')->where('plan', 'enterprise')->update(['plan' => 'custom', 'max_work_orders' => null]);
        } catch (\Exception $e) {
            // Ignore - plan value may not exist or constraint prevents it
        }

        // Set null (unlimited) for standard and custom plans
        DB::table('tenants')->whereIn('plan', ['standard', 'custom'])->update(['max_work_orders' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('max_work_orders');
        });
    }
};
