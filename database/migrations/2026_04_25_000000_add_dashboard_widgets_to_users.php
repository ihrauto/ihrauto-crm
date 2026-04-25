<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENG-009: Dashboard Studio per-user widget preferences.
 *
 * `null` = no preference set; the renderer falls back to role-based
 * defaults from DashboardWidgetCatalog. Once the user toggles a widget,
 * the column holds the explicit list and defaults stop applying.
 *
 * JSON shape: { "version": 1, "enabled": ["active_jobs","pending_jobs",...] }
 *
 * Mass-assignment is intentionally NOT enabled on the User model.
 * Writes go through DashboardStudioService::setEnabled() which validates
 * against the catalog, drops disallowed keys, and caps the list at 50
 * items (defense against JSON-bloat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_widgets')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_widgets');
        });
    }
};
