<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("users", function (Blueprint $table) {
            $table->id();
            $table->foreignId("department_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("role_id")->constrained()->restrictOnDelete();
            $table->string("name", 150);
            $table->string("email", 190)->unique();
            $table->string("password"); // bcrypt hash via Hash::make() — never plaintext
            $table->enum("status", ["Active", "Inactive", "Suspended"])->default("Active");
            $table->timestamp("last_login_at")->nullable();
            $table->timestamp("email_verified_at")->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // preserve history behind procurement/audit foreign keys

            $table->index(["role_id", "status"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("users");
    }
};
