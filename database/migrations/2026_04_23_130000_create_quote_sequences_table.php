<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant quote sequence, mirroring `invoice_sequences`.
 *
 * Before this, QuoteService::generateQuoteNumber() read MAX(id) and added
 * 1 — not concurrency-safe under parallel writes. A locked sequence row
 * gives us gapless, race-free numbering with the same semantics as
 * InvoiceSequence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_sequences');
    }
};
