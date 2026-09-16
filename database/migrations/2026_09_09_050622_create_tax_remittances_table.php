<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_type_id')->constrained()->restrictOnDelete();
            $table->string('period_covered', 60);
            $table->date('remittance_date');
            $table->decimal('amount', 15, 2);
            $table->string('bir_reference_no', 60)->nullable();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('cash_transaction_id')->nullable()->constrained('cash_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_remittances');
    }
};