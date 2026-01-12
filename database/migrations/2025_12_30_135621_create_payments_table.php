<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);

            // cash, card, bank_transfer, twint, other
            $table->string('method')->default('cash');

            $table->date('payment_date');
            $table->string('transaction_reference')->nullable(); // transaction ID, receipt #

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
