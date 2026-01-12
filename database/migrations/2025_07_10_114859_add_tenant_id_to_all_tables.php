<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add tenant_id columns as nullable
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'email']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'license_plate']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'service_bay']);
            $table->index(['tenant_id', 'checkin_time']);
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'storage_location']);
        });

        // Update existing unique constraints to include tenant_id for proper isolation
        Schema::table('customers', function (Blueprint $table) {
            // Add tenant-scoped unique constraint for email
            $table->unique(['tenant_id', 'email'], 'customers_tenant_email_unique');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            // Drop existing license_plate unique constraint
            $table->dropUnique(['license_plate']);
            // Add tenant-scoped unique constraint for license plate
            $table->unique(['tenant_id', 'license_plate'], 'vehicles_tenant_license_unique');
        });

        // Add foreign key constraints
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove tenant-scoped unique constraints
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_tenant_email_unique');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_tenant_license_unique');
            $table->unique('license_plate'); // Restore original unique constraint
        });

        // Remove foreign key constraints and tenant_id columns
        Schema::table('tires', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
