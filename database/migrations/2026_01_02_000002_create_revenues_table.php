<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("revenues", function (Blueprint $table) {
            $table->id();
            $table->date("date");
            $table->enum("revenue_type", ["Tuition", "Miscellaneous Fees", "Service Income", "Other Institutional Revenue"]);
            $table->string("description", 255);
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->string("payer", 190);
            $table->string("reference_no", 60)->nullable();
            $table->decimal("amount", 15, 2);
            $table->enum("payment_method", ["Cash", "Bank Transfer", "Check", "Online Payment"])->default("Cash");
            $table->enum("status", ["Received", "Pending"])->default("Received");
            $table->foreignId("recorded_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index(["department_id", "date"]);
            $table->index("status");
            $table->index("reference_no");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("revenues");
    }
};
