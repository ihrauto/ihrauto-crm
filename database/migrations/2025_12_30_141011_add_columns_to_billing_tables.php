<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('status');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete(); // Helpers for direct querying
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['created_by', 'customer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['work_order_id']);
            $table->dropColumn(['created_by', 'locked_at', 'work_order_id']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['work_order_id']);
            $table->dropColumn(['created_by', 'work_order_id']);
        });
    }
};
