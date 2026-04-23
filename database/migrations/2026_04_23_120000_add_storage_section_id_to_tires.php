<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B-16: tires.storage_location was a freeform string, which made it
 * impossible to prevent typos, orphan references, and location deletions
 * silently breaking tire lookups.
 *
 * Non-destructive evolution:
 *   1. Add a nullable storage_section_id FK next to the existing column.
 *   2. Backfill where a storage_section.name matches the tire's text
 *      location (tenant-scoped so we don't cross leak).
 *   3. Keep the legacy column for now — UIs still reference it; a follow-
 *      up migration can drop it once every tenant's data is reconciled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->foreignId('storage_section_id')
                ->nullable()
                ->after('storage_location')
                ->constrained('storage_sections')
                ->nullOnDelete();
            $table->index(['tenant_id', 'storage_section_id'], 'tires_tenant_section_idx');
        });

        // Backfill: for each tire, find a storage_section in the same
        // tenant whose `name` equals the tire's storage_location. Safe
        // on both PostgreSQL and SQLite.
        DB::statement(<<<'SQL'
            UPDATE tires
            SET storage_section_id = (
                SELECT storage_sections.id
                FROM storage_sections
                WHERE storage_sections.tenant_id = tires.tenant_id
                  AND storage_sections.name = tires.storage_location
                LIMIT 1
            )
            WHERE tires.storage_location IS NOT NULL
              AND tires.storage_location <> ''
        SQL);
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropIndex('tires_tenant_section_idx');
            $table->dropConstrainedForeignId('storage_section_id');
        });
    }
};
