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
        Schema::table('invoices', function (Blueprint $table) {
            // Issuance tracking
            $table->timestamp('issued_at')->nullable()->after('locked_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete()->after('issued_at');

            // Void tracking
            $table->timestamp('voided_at')->nullable()->after('issued_by');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
            $table->string('void_reason')->nullable()->after('voided_by');

            // Unique invoice number per tenant
            $table->unique(['tenant_id', 'invoice_number'], 'invoices_tenant_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_number_unique');
            $table->dropConstrainedForeignId('issued_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['issued_at', 'voided_at', 'void_reason']);
        });
    }
};
