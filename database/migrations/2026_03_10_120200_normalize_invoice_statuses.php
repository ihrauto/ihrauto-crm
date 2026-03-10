<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->whereIn('status', ['cancelled', 'canceled'])
            ->update(['status' => Invoice::STATUS_VOID]);

        DB::table('invoices')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->where('paid_amount', '<=', 0)
            ->update(['status' => Invoice::STATUS_ISSUED]);

        DB::table('invoices')
            ->where('paid_amount', '>', 0)
            ->whereColumn('paid_amount', '<', 'total')
            ->update(['status' => Invoice::STATUS_PARTIAL]);

        DB::table('invoices')
            ->whereColumn('paid_amount', '>=', 'total')
            ->update(['status' => Invoice::STATUS_PAID]);
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('status', Invoice::STATUS_VOID)
            ->update(['status' => 'cancelled']);

        DB::table('invoices')
            ->where('status', Invoice::STATUS_ISSUED)
            ->update(['status' => 'unpaid']);
    }
};
