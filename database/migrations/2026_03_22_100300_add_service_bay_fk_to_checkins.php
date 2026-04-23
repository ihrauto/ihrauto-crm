<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace string service_bay column with proper FK to service_bays table.
     */
    public function up(): void
    {
        // Step 1: Add new FK column
        Schema::table('checkins', function (Blueprint $table) {
            $table->unsignedBigInteger('service_bay_id')->nullable()->after('service_bay');
            $table->foreign('service_bay_id')->references('id')->on('service_bays')->nullOnDelete();
        });

        // Step 2: Migrate existing string data to IDs where possible
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE checkins
                SET service_bay_id = service_bays.id
                FROM service_bays
                WHERE checkins.service_bay = service_bays.name
                AND checkins.tenant_id = service_bays.tenant_id
                AND checkins.service_bay IS NOT NULL
            ');
        } else {
            DB::statement('
                UPDATE checkins
                SET service_bay_id = (
                    SELECT service_bays.id FROM service_bays
                    WHERE service_bays.name = checkins.service_bay
                    AND service_bays.tenant_id = checkins.tenant_id
                )
                WHERE checkins.service_bay IS NOT NULL
            ');
        }

        // Note: The old service_bay string column is kept for now.
        // It will be dropped in a future migration after all code references
        // are migrated to use service_bay_id instead.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropForeign(['service_bay_id']);
            $table->dropColumn('service_bay_id');
        });
    }
};
