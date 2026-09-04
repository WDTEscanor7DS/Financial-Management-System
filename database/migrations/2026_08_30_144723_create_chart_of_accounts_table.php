<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("chart_of_accounts", function (Blueprint $table) {
            $table->id();
            $table->string("account_code", 20)->unique();
            $table->string("account_name", 190);
            $table->enum("account_type", ["Asset", "Liability", "Equity", "Revenue", "Expense"]);
            $table->enum("normal_balance", ["Debit", "Credit"]);
            $table->boolean("is_active")->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("chart_of_accounts");
    }
};