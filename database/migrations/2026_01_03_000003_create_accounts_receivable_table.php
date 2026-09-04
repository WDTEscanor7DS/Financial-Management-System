<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("accounts_receivable", function (Blueprint $table) {
            $table->id();
            $table->string("customer", 190);
            $table->string("reference_no", 80)->nullable();
            $table->string("description", 255);
            $table->date("invoice_date");
            $table->date("due_date");
            $table->decimal("amount", 15, 2);
            $table->decimal("amount_paid", 15, 2)->default(0);
            $table->decimal("balance", 15, 2);
            $table->enum("status", ["Outstanding", "Partially Paid", "Paid"])->default("Outstanding");
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index("status");
            $table->index("due_date");
            $table->index("reference_no");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("accounts_receivable");
    }
};
