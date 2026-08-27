<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Seeded once (Administrator, Accountant, College Administrator,
    // Employee) — see database/seeders/RolePermissionSeeder.php. The four
    // roles mirror the ROLES object already used by the existing frontend
    // prototype in js/auth.js so behaviour does not change for end users.
    public function up(): void
    {
        Schema::create("roles", function (Blueprint $table) {
            $table->id();
            $table->string("name", 60)->unique();
            $table->string("slug", 60)->unique();
            $table->string("description", 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("roles");
    }
};
