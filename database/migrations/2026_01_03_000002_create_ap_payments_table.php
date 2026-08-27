<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Append-only payment history — this is the source of truth that
    // accounts_payable.amount_paid/balance are derived from.
    public function up(): void
    {
        Schema::create("ap_payments", function (Blueprint $table) {
            $table->id();
            $table->foreignId("accounts_payable_id")->constrained("accounts_payable")->cascadeOnDelete();
            $table->decimal("amount", 15, 2);
            $table->date("paid_at");
            $table->foreignId("recorded_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index("accounts_payable_id");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("ap_payments");
    }
};
