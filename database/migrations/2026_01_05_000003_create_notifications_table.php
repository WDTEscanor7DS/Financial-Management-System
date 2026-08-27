<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // user_id nullable = a broadcast notification visible to every user who
    // holds a permission relevant to it (resolved at query time by
    // NotificationService rather than fanned out into one row per user, to
    // avoid an unbounded write on every budget-threshold/overdue event).
    public function up(): void
    {
        Schema::create("notifications", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->text("message");
            $table->boolean("read")->default(false);
            $table->timestamp("created_at")->useCurrent();

            $table->index(["user_id", "read"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("notifications");
    }
};
