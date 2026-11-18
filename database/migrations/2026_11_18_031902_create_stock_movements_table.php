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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', ['receipt', 'issue', 'adjustment']);
            $table->decimal('quantity_change', 18, 4);
            $table->decimal('quantity_before', 18, 4)->unsigned();
            $table->decimal('quantity_after', 18, 4)->unsigned();
            $table->timestamp('occurred_at');
            $table->text('reason')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'location_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
