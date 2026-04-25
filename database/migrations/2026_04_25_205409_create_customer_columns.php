<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier customer columns — patched to target `tenants`, not `users`.
 *
 * The Billable model in this app is the Tenant (one paying customer per
 * tenant), not the User. We also skip Cashier's `trial_ends_at` column
 * because the platform already owns a `tenants.trial_ends_at` column for
 * the app-level trial (used by EnsureTenantTrialActive middleware).
 * Cashier never reads the model's trial_ends_at when subscriptions are
 * managed via Stripe Checkout — the trial info lives on the subscription
 * row instead — so the collision is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four']);
        });
    }
};
