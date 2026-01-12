<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add soft deletes to all main tables
        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['email'], 'customers_email_index');
            $table->index(['phone'], 'customers_phone_index');
            $table->index(['is_active'], 'customers_is_active_index');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['customer_id', 'is_active'], 'vehicles_customer_active_index');
            $table->index(['make', 'model'], 'vehicles_make_model_index');
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->softDeletes();
            // Performance indexes for common queries
            $table->index(['status'], 'checkins_status_index');
            $table->index(['service_bay'], 'checkins_service_bay_index');
            $table->index(['priority'], 'checkins_priority_index');
            $table->index(['checkin_time'], 'checkins_checkin_time_index');
            $table->index(['checkout_time'], 'checkins_checkout_time_index');

            // Composite indexes for complex queries
            $table->index(['status', 'checkin_time'], 'checkins_status_time_index');
            $table->index(['customer_id', 'status'], 'checkins_customer_status_index');
            $table->index(['vehicle_id', 'status'], 'checkins_vehicle_status_index');
            $table->index(['service_bay', 'status'], 'checkins_bay_status_index');
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['status'], 'tires_status_index');
            $table->index(['storage_location'], 'tires_storage_location_index');
            $table->index(['season'], 'tires_season_index');
            $table->index(['customer_id', 'status'], 'tires_customer_status_index');
            $table->index(['vehicle_id', 'status'], 'tires_vehicle_status_index');
        });

        // Fix email unique constraint issue - make it conditional
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        // Create a unique index that ignores NULL values
        DB::statement('CREATE UNIQUE INDEX customers_email_unique ON customers (email) WHERE email IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop soft deletes
        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex('customers_email_index');
            $table->dropIndex('customers_phone_index');
            $table->dropIndex('customers_is_active_index');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex('vehicles_customer_active_index');
            $table->dropIndex('vehicles_make_model_index');
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex('checkins_status_index');
            $table->dropIndex('checkins_service_bay_index');
            $table->dropIndex('checkins_priority_index');
            $table->dropIndex('checkins_checkin_time_index');
            $table->dropIndex('checkins_checkout_time_index');
            $table->dropIndex('checkins_status_time_index');
            $table->dropIndex('checkins_customer_status_index');
            $table->dropIndex('checkins_vehicle_status_index');
            $table->dropIndex('checkins_bay_status_index');
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex('tires_status_index');
            $table->dropIndex('tires_storage_location_index');
            $table->dropIndex('tires_season_index');
            $table->dropIndex('tires_customer_status_index');
            $table->dropIndex('tires_vehicle_status_index');
        });

        // Restore original email unique constraint
        DB::statement('DROP INDEX IF EXISTS customers_email_unique');
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
