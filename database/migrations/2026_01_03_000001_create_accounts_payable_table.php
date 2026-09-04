<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("accounts_payable", function (Blueprint $table) {
            $table->id();
            $table->string("vendor", 190);
            $table->string("invoice_no", 80);
            $table->date("invoice_date");
            $table->date("due_date");
            $table->string("description", 255);
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->decimal("amount", 15, 2);
            // amount_paid / balance are cached sums of ap_payments,
            // recalculated inside PayableService::recordPayment() within a
            // single DB transaction — never edited directly (Section 11).
            $table->decimal("amount_paid", 15, 2)->default(0);
            $table->decimal("balance", 15, 2);
            $table->enum("status", ["Pending", "Partially Paid", "Paid"])->default("Pending");
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->unique(["vendor", "invoice_no"]);
            $table->index(["department_id", "status"]);
            $table->index("due_date");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("accounts_payable");
    }
};
