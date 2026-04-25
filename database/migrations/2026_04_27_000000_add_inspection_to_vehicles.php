<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENG-012: vehicle periodic inspection tracking.
 *
 *   - last_inspection_at  — when the last sticker was issued
 *   - next_inspection_at  — when the next is due (the reminder driver
 *                           reads this column for "due in 30 days" /
 *                           "due in 14" / "due in 3" send buckets)
 *   - inspection_authority — TÜV (DE), MFK (CH), §57a (AT). Drives
 *                            the wording in the SMS template so the
 *                            customer reads the legal name they recognize.
 *
 * `next_inspection_at` is what the reminder query looks at; making it
 * indexed plus partial-on-not-null keeps the daily scheduled command
 * cheap regardless of fleet size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('last_inspection_at')->nullable()->after('mileage');
            $table->date('next_inspection_at')->nullable()->after('last_inspection_at');
            $table->string('inspection_authority', 16)->nullable()->after('next_inspection_at')
                ->comment('TUV (DE) / MFK (CH) / 57A (AT) — drives reminder wording');
            $table->json('inspection_reminders_sent')->nullable()->after('inspection_authority')
                ->comment('Idempotency: list of buckets already notified for the current next_inspection_at value, e.g. ["30d","14d"]');

            $table->index('next_inspection_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['next_inspection_at']);
            $table->dropColumn([
                'last_inspection_at',
                'next_inspection_at',
                'inspection_authority',
                'inspection_reminders_sent',
            ]);
        });
    }
};
