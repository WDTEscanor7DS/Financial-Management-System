<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("assets", function (Blueprint $table) {
            $table->id();
            $table->string("asset_name", 190);
            $table->enum("category", [
                "IT Equipment",
                "Office Equipment",
                "Transportation",
                "Facilities",
                "Furniture & Fixtures",
            ]);
            $table->string("serial_no", 100)->nullable()->unique();
            $table->date("purchase_date");
            $table->decimal("purchase_cost", 15, 2);
            $table->unsignedSmallInteger("useful_life"); // years
            $table->decimal("salvage_value", 15, 2)->default(0);
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->string("location", 190)->nullable();
            $table->enum("status", ["In Use", "Under Maintenance", "Disposed"])->default("In Use");
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index(["department_id", "category"]);
        });
    }

    // Depreciation itself (annual/accumulated/book value) is computed on
    // read by AssetService — straight-line depreciation is fully
    // deterministic from purchase_cost/salvage_value/useful_life/
    // purchase_date, so no depreciation-schedule table is needed for the
    // functionality currently in the frontend. If a future requirement adds
    // manual depreciation adjustments or a different method per asset, add
    // an `asset_depreciation_entries` ledger table at that point rather
    // than before it is needed.
    public function down(): void
    {
        Schema::dropIfExists("assets");
    }
};
