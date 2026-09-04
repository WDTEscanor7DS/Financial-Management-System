<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Maps to the existing "budgets" localStorage store. The frontend
    // treats one row as both the allocation and the fiscal-year/category
    // grouping (there is no separate multi-line "budget_allocations"
    // concept in the current UI), so this migration keeps that one-level
    // model rather than inventing an unused extra table — see
    // docs/ARCHITECTURE_ASSESSMENT.md Section 8 discussion.
    public function up(): void
    {
        Schema::create("budgets", function (Blueprint $table) {
            $table->id();
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->char("fiscal_year", 4);
            $table->string("category", 150);
            $table->decimal("allocated", 15, 2);
            // actual_spending is a cached, transaction-maintained sum of
            // linked expenses (see ExpenseService) — never written to
            // directly outside a DB transaction.
            $table->decimal("actual_spending", 15, 2)->default(0);
            $table->enum("status", ["Active", "Closed"])->default("Active");
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->unique(["department_id", "fiscal_year", "category"], "budgets_unique_allocation");
            $table->index(["department_id", "fiscal_year"]);
            $table->index("status");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("budgets");
    }
};
