<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("procurement_requests", function (Blueprint $table) {
            $table->id();
            $table->foreignId("requester_id")->constrained("users")->restrictOnDelete();
            $table->foreignId("department_id")->constrained()->restrictOnDelete();
            $table->enum("request_type", [
                "Purchase Request",
                "Reimbursement",
                "Financial Request",
                "Office Supplies",
                "Equipment",
                "Services",
            ]);
            $table->text("description");
            $table->string("quantity", 120)->nullable();
            $table->decimal("estimated_cost", 15, 2);
            $table->enum("priority", ["Low", "Medium", "High", "Urgent"])->default("Medium");
            $table->date("date_submitted");
            // Enforced status machine — see ProcurementService::review()/
            // advance(): Pending Review -> Approved|Rejected;
            // Approved -> Procurement Processing -> Completed.
            // No status other than these five is ever written, and an
            // Employee-role request can only ever create a row in
            // "Pending Review" (Section 14).
            $table->enum("status", [
                "Pending Review",
                "Approved",
                "Rejected",
                "Procurement Processing",
                "Completed",
            ])->default("Pending Review");
            $table->foreignId("reviewer_id")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("reviewed_at")->nullable();
            $table->text("remarks")->nullable();
            $table->timestamps();

            $table->index(["department_id", "status"]);
            $table->index("requester_id");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("procurement_requests");
    }
};
