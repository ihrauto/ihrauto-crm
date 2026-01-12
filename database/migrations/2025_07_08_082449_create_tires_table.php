<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->string('brand');
            $table->string('model');
            $table->string('size'); // e.g., 255/55R18
            $table->enum('season', ['winter', 'summer', 'all_season']);
            $table->integer('quantity')->default(4);
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor', 'needs_replacement']);
            $table->string('storage_location'); // e.g., A-12
            $table->date('storage_date');
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->decimal('tread_depth', 4, 2)->nullable(); // in mm
            $table->enum('status', ['stored', 'ready_pickup', 'maintenance', 'disposed'])->default('stored');
            $table->text('notes')->nullable();
            $table->decimal('storage_fee', 8, 2)->nullable();
            $table->boolean('customer_notified')->default(false);
            $table->date('pickup_reminder_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tires');
    }
};
