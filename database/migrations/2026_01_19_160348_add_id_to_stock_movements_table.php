<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Only add id if it doesn't exist (SQLite compatibility)
        if (!Schema::hasColumn('stock_movements', 'id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->id()->first();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite' || !Schema::hasColumn('stock_movements', 'id')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
