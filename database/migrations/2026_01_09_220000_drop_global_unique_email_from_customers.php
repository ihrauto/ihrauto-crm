<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes the global unique constraint on customers.email,
     * leaving only the tenant-scoped unique constraint (tenant_id, email).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop the global unique constraint that was created in the initial migration
            // Default name is usually customers_email_unique
            $table->dropUnique(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
