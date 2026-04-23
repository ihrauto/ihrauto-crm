<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add tenant_id to audit_logs for proper multi-tenant scoping.
     * Nullable because some audit logs are system-level (no tenant context).
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });

        // Backfill tenant_id from the user's tenant_id where possible
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE audit_logs
                SET tenant_id = users.tenant_id
                FROM users
                WHERE audit_logs.user_id = users.id
                AND audit_logs.tenant_id IS NULL
            ');
        } else {
            DB::statement('
                UPDATE audit_logs
                SET tenant_id = (SELECT users.tenant_id FROM users WHERE users.id = audit_logs.user_id)
                WHERE audit_logs.user_id IS NOT NULL
                AND audit_logs.tenant_id IS NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
