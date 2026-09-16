<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 30)->unique();
            $table->string('item_name', 190);
            $table->string('category', 80)->nullable();
            $table->string('unit_of_measure', 30);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->enum('status', ['Active', 'Discontinued'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};