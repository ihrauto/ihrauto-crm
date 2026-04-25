<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENG-011: every SMS attempt produces an immutable communication log
 * row. Lets ops verify whether a customer was actually told their car
 * is ready, surface delivery failures in the UI, and feed billing
 * (per-message cost recovery) later.
 *
 * Status lifecycle:
 *   queued     — Twilio API accepted the request (sid returned)
 *   delivered  — webhook receipt (later — not wired yet)
 *   failed     — Twilio rejected synchronously (bad number, no balance)
 *   skipped    — local guard refused (opt-out, missing phone, tenant
 *                disabled, rate limit). Useful for the UI to show
 *                "we wanted to send but couldn't".
 *
 * Channel column is forward-compat for whatsapp / email later.
 *
 * Append-only: no `updated_at` (the row reflects the moment of attempt).
 * Customer/work_order foreign keys nullOnDelete so deleting an old
 * customer doesn't take their notification history down with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Operator who triggered the send.');
            $table->string('channel', 16)->default('sms')->comment('sms / whatsapp / email / push');
            $table->string('to', 64)->comment('E.164 phone or email — what we actually sent to.');
            $table->string('template', 64)->comment('e.g. work_order.ready, appointment.reminder');
            $table->text('body');
            $table->string('status', 16)->index()->comment('queued / delivered / failed / skipped');
            $table->string('provider_id', 64)->nullable()->index()->comment('Twilio SID / equivalent.');
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'work_order_id']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
