<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("funds", function (Blueprint $table) {
            $table->id();
            $table->string("name", 190);
            $table->enum("type", ["Operating", "Capital", "Restricted"]);
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->decimal("allocation", 15, 2);
            // used is a cached sum of fund_allocations.amount, recalculated
            // transactionally by FundService::allocate() (Section 13/51:
            // this is exactly where the "two accountants allocate the same
            // remaining balance" race condition is prevented with a
            // SELECT ... FOR UPDATE row lock).
            $table->decimal("used", 15, 2)->default(0);
            $table->enum("status", ["Active", "Closed"])->default("Active");
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index(["department_id", "status"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("funds");
    }
};
