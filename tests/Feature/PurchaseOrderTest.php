<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ApprovalWorkflowSeeder::class);

        ChartOfAccount::create(['account_code' => '1200', 'account_name' => 'Inventory', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        ChartOfAccount::create(['account_code' => '2000', 'account_name' => 'Accounts Payable', 'account_type' => 'Liability', 'normal_balance' => 'Credit']);

        $this->item = InventoryItem::create([
            'item_code' => 'INV-0001', 'item_name' => 'Bond Paper', 'unit_of_measure' => 'Ream',
            'unit_cost' => 250, 'quantity_on_hand' => 10, 'reorder_level' => 5, 'status' => 'Active',
        ]);
    }

    private function userWithRole(string $slug): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $slug)->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_creating_po_computes_total_and_starts_as_draft(): void
    {
        $accountant = $this->userWithRole('accountant');

        $response = $this->actingAs($accountant)->postJson('/api/purchase-orders', [
            'supplier_name' => 'ABC Office Supplies',
            'order_date' => now()->toDateString(),
            'items' => [
                ['inventory_item_id' => $this->item->id, 'quantity' => 20, 'unit_cost' => 250],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_orders', ['supplier_name' => 'ABC Office Supplies', 'status' => 'Draft', 'total_amount' => 5000]);
    }

    public function test_submitting_creates_approval_request(): void
    {
        $accountant = $this->userWithRole('accountant');
        $po = PurchaseOrder::create([
            'po_number' => 'PO-00001', 'supplier_name' => 'ABC Office Supplies', 'order_date' => now(),
            'status' => 'Draft', 'total_amount' => 5000, 'created_by' => $accountant->id,
        ]);

        $response = $this->actingAs($accountant)->postJson("/api/purchase-orders/{$po->id}/submit");

        $response->assertStatus(200);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'Pending Approval']);
        $this->assertDatabaseCount('approval_requests', 1);
    }

    public function test_full_flow_approval_then_receive_updates_inventory_and_ledger(): void
    {
        $accountant = $this->userWithRole('accountant');
        $collegeAdmin = $this->userWithRole('college-administrator');

        $po = PurchaseOrder::create([
            'po_number' => 'PO-00001', 'supplier_name' => 'ABC Office Supplies', 'order_date' => now(),
            'status' => 'Draft', 'total_amount' => 5000, 'created_by' => $accountant->id,
        ]);
        $po->items()->create(['inventory_item_id' => $this->item->id, 'quantity' => 20, 'unit_cost' => 250, 'line_total' => 5000]);

        $this->actingAs($accountant)->postJson("/api/purchase-orders/{$po->id}/submit")->assertStatus(200);

        $approvalRequestId = $po->fresh()->approval_request_id;

        $this->actingAs($collegeAdmin)->postJson("/api/approvals/{$approvalRequestId}/act", ['decision' => 'Approved'])->assertStatus(200);
        $this->actingAs($accountant)->postJson("/api/approvals/{$approvalRequestId}/act", ['decision' => 'Approved'])->assertStatus(200);

        // Re-fetching the PO list triggers syncApprovalStatus() to pick up the now-Approved request.
        $this->actingAs($accountant)->getJson('/api/purchase-orders')->assertStatus(200);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'Approved']);

        $receive = $this->actingAs($accountant)->postJson("/api/purchase-orders/{$po->id}/receive");
        $receive->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'Received']);
        $this->assertDatabaseHas('inventory_items', ['id' => $this->item->id, 'quantity_on_hand' => 30]); // 10 + 20
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseHas('journal_entry_lines', ['debit' => 5000]);
        $this->assertDatabaseHas('journal_entry_lines', ['credit' => 5000]);
    }

    public function test_cannot_receive_before_approved(): void
    {
        $accountant = $this->userWithRole('accountant');
        $po = PurchaseOrder::create([
            'po_number' => 'PO-00001', 'supplier_name' => 'ABC Office Supplies', 'order_date' => now(),
            'status' => 'Pending Approval', 'total_amount' => 5000, 'created_by' => $accountant->id,
        ]);

        $response = $this->actingAs($accountant)->postJson("/api/purchase-orders/{$po->id}/receive");

        $response->assertStatus(422);
        $this->assertDatabaseHas('inventory_items', ['id' => $this->item->id, 'quantity_on_hand' => 10]); // unchanged
    }
}