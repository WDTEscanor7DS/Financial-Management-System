<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Deliberately has no updated_at and no soft-delete column: audit
    // entries are immutable and are never edited or deleted through the
    // application (Section 17/44). Only a raw DBA-level operation (backup
    // restore, retention purge per policy) touches this table outside of
    // INSERT.
    public function up(): void
    {
        Schema::create("audit_logs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained("users")->nullOnDelete();
            $table->string("role", 60)->nullable();
            $table->string("action", 100);
            $table->string("module", 100);
            $table->string("record_type", 100)->nullable();
            $table->string("record_id", 60)->nullable();
            $table->json("old_values")->nullable();
            $table->json("new_values")->nullable();
            $table->string("ip_address", 45)->nullable();
            $table->string("user_agent", 255)->nullable();
            $table->text("description")->nullable();
            $table->string("status", 20)->default("Success");
            $table->timestamp("created_at")->useCurrent();

            $table->index("user_id");
            $table->index("module");
            $table->index("created_at");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("audit_logs");
    }
};
