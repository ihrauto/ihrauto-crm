<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint C-3 (Scalability + Swiss OR-958f compliance).
 *
 * The `audit_logs` table grows at ~36M rows/year at 200 tenants. Swiss
 * OR Art. 958f requires 10 years of retention; deleting rows is illegal.
 *
 * Strategy:
 *   - Keep the last 2 years in `audit_logs` (the hot table that queries hit).
 *   - Move everything older into `audit_logs_archive` (cold table, same shape).
 *   - Year 10+ can be dropped manually after legal review.
 *
 * This migration only creates the archive table. The actual move is
 * driven by `audit-logs:archive` (app/Console/Commands/ArchiveAuditLogsCommand.php),
 * scheduled weekly in routes/console.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs_archive', function (Blueprint $table) {
            // Mirror the live shape. Keep the original `id` so archived
            // rows can be referenced via their historical ID if audit
            // history is ever requested.
            $table->unsignedBigInteger('id')->primary();

            // tenant_id / user_id kept as nullable unsigned — the source
            // User / Tenant rows may be gone (tenant deletion, user GC)
            // by the time we archive. Don't constrain with an FK.
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('action');
            $table->string('model_type')->nullable();
            $table->string('model_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            // Stamp when the row crossed over — useful for "when was this
            // archived" questions during audits.
            $table->timestamp('archived_at')->useCurrent();

            // Indexes tuned for the common audit queries: "show me what
            // happened to tenant X in model Y around date Z".
            $table->index(['tenant_id', 'created_at'], 'audit_logs_archive_tenant_created_idx');
            $table->index(['tenant_id', 'model_type', 'model_id'], 'audit_logs_archive_tenant_model_idx');
            $table->index('archived_at', 'audit_logs_archive_archived_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs_archive');
    }
};
