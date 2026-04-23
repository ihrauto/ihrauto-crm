<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-14: track when the overdue reminder email was last sent for each
 * invoice so the daily scheduler doesn't spam the tenant admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('last_reminder_sent_at')->nullable()->after('paid_amount');
            $table->index(['tenant_id', 'last_reminder_sent_at'], 'invoices_tenant_reminder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_tenant_reminder_idx');
            $table->dropColumn('last_reminder_sent_at');
        });
    }
};
