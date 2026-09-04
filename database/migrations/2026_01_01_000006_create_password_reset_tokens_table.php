<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Laravels standard password-reset table: a token, never the password
    // itself, hashed, single-use (deleted on successful reset), and it
    // expires after config("auth.passwords.users.expire") minutes — see
    // Section 36 of the brief.
    public function up(): void
    {
        Schema::create("password_reset_tokens", function (Blueprint $table) {
            $table->string("email")->primary();
            $table->string("token");
            $table->timestamp("created_at")->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("password_reset_tokens");
    }
};
