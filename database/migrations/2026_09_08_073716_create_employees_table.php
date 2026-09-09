<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 30)->unique();
            $table->string('full_name', 190);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position', 120);
            $table->enum('employment_type', ['Full-time', 'Part-time']);
            $table->decimal('monthly_rate', 12, 2);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->date('hire_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};