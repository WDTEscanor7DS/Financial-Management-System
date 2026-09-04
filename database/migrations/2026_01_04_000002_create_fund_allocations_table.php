<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Append-only ledger of every "Allocate" action against a fund. This is
    // what funds.used is derived from — the fund row itself is never
    // decremented/incremented without a matching row appearing here first.
    public function up(): void
    {
        Schema::create("fund_allocations", function (Blueprint $table) {
            $table->id();
            $table->foreignId("fund_id")->constrained("funds")->cascadeOnDelete();
            $table->decimal("amount", 15, 2);
            $table->string("description", 255)->nullable();
            $table->foreignId("allocated_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index("fund_id");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("fund_allocations");
    }
};
