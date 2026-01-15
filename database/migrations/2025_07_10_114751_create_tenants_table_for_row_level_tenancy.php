<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Basic tenant information
            $table->string('name'); // Company/Organization name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->string('domain')->nullable()->unique(); // Custom domain (optional)
            $table->string('subdomain')->unique(); // subdomain.yourapp.com

            // Contact information
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Kosovo');

            // Subscription & Billing
            $table->enum('plan', ['basic', 'standard', 'custom'])->default('basic');
            $table->integer('max_users')->default(1);
            $table->integer('max_customers')->default(100);
            $table->integer('max_vehicles')->default(500);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trial')->default(true);
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable();

            // Features & Limits
            $table->json('features')->nullable(); // enabled features
            $table->json('settings')->nullable(); // tenant-specific settings
            $table->json('integrations')->nullable(); // third-party integrations

            // Branding
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->default('#1A53F2');
            $table->string('secondary_color')->default('#F1FF30');

            // Database & Technical
            $table->string('database_name')->nullable(); // for future database-per-tenant migration
            $table->string('timezone')->default('Europe/Vienna');
            $table->string('locale')->default('en');
            $table->string('currency')->default('EUR');

            // Security & Compliance
            $table->boolean('two_factor_required')->default(false);
            $table->json('ip_whitelist')->nullable();
            $table->boolean('audit_logs_enabled')->default(true);

            // API & Integration
            $table->string('api_key')->unique()->nullable();
            $table->integer('api_rate_limit')->default(1000); // per hour

            // Status tracking
            $table->timestamp('last_activity_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index(['subdomain', 'is_active']);
            $table->index(['plan', 'is_active']);
            $table->index(['is_trial', 'trial_ends_at']);
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
