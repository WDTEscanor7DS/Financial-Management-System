<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_module', 60);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_module', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};