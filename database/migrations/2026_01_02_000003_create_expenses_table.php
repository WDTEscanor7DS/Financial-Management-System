<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("expenses", function (Blueprint $table) {
            $table->id();
            $table->date("date");
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->enum("expense_category", [
                "Salaries",
                "Utilities",
                "Supplies",
                "Maintenance",
                "Procurement",
                "Transportation",
                "Equipment",
                "Other Operating Expenses",
            ]);
            $table->string("description", 255);
            $table->string("vendor", 190);
            $table->string("reference_no", 60)->nullable();
            $table->decimal("amount", 15, 2);
            $table->enum("payment_method", ["Cash", "Bank Transfer", "Check"])->default("Cash");
            $table->enum("status", ["Paid", "Pending"])->default("Paid");
            // Nullable: an expense does not have to be linked to a budget,
            // matching the optional "Linked Budget" field already in the
            // existing Add Expense modal.
            $table->foreignId("budget_id")->nullable()->constrained("budgets")->nullOnDelete();
            $table->foreignId("recorded_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index(["department_id", "date"]);
            $table->index("budget_id");
            $table->index("status");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("expenses");
    }
};
