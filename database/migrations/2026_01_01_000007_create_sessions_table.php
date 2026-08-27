<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Database-backed sessions so an Administrator can forcibly invalidate
    // a specific users session (Section 31/35: disabling/suspending a user
    // must kill any session they are already holding).
    public function up(): void
    {
        Schema::create("sessions", function (Blueprint $table) {
            $table->string("id")->primary();
            $table->foreignId("user_id")->nullable()->index();
            $table->string("ip_address", 45)->nullable();
            $table->text("user_agent")->nullable();
            $table->longText("payload");
            $table->integer("last_activity")->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sessions");
    }
};
