<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENG-011: per-customer SMS opt-out. Tenant-level "SMS enabled" lives
 * in tenants.settings.sms.enabled (no schema change needed there).
 *
 * Default false (i.e. opt-IN by default for transactional notifications
 * like "your car is ready" — these are not marketing). Customers can
 * tell the workshop "stop texting me" and the receptionist flips this
 * flag from the customer detail screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('sms_opt_out')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('sms_opt_out');
        });
    }
};
