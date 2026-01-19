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
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('min_stock_quantity');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('unit');
            $table->string('order_number')->nullable()->after('purchase_price');
            $table->string('supplier')->nullable()->after('order_number');
            $table->string('status')->default('in_stock')->after('supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit', 'purchase_price', 'order_number', 'supplier', 'status']);
        });
    }
};
