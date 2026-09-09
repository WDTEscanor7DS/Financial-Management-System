<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('contra_account_id')->nullable()->after('transfer_to_account_id')
                ->constrained('chart_of_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['contra_account_id']);
            $table->dropColumn('contra_account_id');
        });
    }
};