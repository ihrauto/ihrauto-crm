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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('postal_code')->nullable()->after('address');
            $table->string('uid_number')->nullable()->after('country');
            $table->boolean('vat_registered')->default(false)->after('uid_number');
            $table->string('vat_number')->nullable()->after('vat_registered');
            $table->string('bank_name')->nullable()->after('vat_number');
            $table->string('iban')->nullable()->after('bank_name');
            $table->string('account_holder')->nullable()->after('iban');
            $table->string('invoice_email')->nullable()->after('account_holder');
            $table->string('invoice_phone')->nullable()->after('invoice_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'postal_code',
                'uid_number',
                'vat_registered',
                'vat_number',
                'bank_name',
                'iban',
                'account_holder',
                'invoice_email',
                'invoice_phone',
            ]);
        });
    }
};
