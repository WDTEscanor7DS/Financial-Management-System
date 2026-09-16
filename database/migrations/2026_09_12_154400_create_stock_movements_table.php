<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->enum('movement_type', ['Stock In', 'Stock Out', 'Adjustment']);
            $table->integer('quantity');
            $table->string('reference_no', 60)->nullable();
            $table->string('description', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('moved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};