<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function accountant(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'accountant')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_stock_in_increases_quantity(): void
    {
        $accountant = $this->accountant();
        $item = InventoryItem::create([
            'item_code' => 'INV-0001', 'item_name' => 'Bond Paper', 'unit_of_measure' => 'Ream',
            'unit_cost' => 250, 'quantity_on_hand' => 10, 'reorder_level' => 5, 'status' => 'Active',
        ]);

        $response = $this->actingAs($accountant)->postJson('/api/inventory/movements', [
            'inventory_item_id' => $item->id,
            'type' => 'Stock In',
            'quantity' => 20,
            'moved_at' => now()->toDateString(),
            'description' => 'Test stock in',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity_on_hand' => 30]);
    }

    public function test_stock_out_exceeding_available_is_rejected(): void
    {
        $accountant = $this->accountant();
        $item = InventoryItem::create([
            'item_code' => 'INV-0001', 'item_name' => 'Bond Paper', 'unit_of_measure' => 'Ream',
            'unit_cost' => 250, 'quantity_on_hand' => 10, 'reorder_level' => 5, 'status' => 'Active',
        ]);

        $response = $this->actingAs($accountant)->postJson('/api/inventory/movements', [
            'inventory_item_id' => $item->id,
            'type' => 'Stock Out',
            'quantity' => 999,
            'moved_at' => now()->toDateString(),
            'description' => 'Test excessive stock out',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity_on_hand' => 10]);
    }

    public function test_negative_adjustment_cannot_result_in_negative_stock(): void
    {
        $accountant = $this->accountant();
        $item = InventoryItem::create([
            'item_code' => 'INV-0001', 'item_name' => 'Bond Paper', 'unit_of_measure' => 'Ream',
            'unit_cost' => 250, 'quantity_on_hand' => 5, 'reorder_level' => 5, 'status' => 'Active',
        ]);

        $response = $this->actingAs($accountant)->postJson('/api/inventory/movements', [
            'inventory_item_id' => $item->id,
            'type' => 'Adjustment',
            'quantity' => -10,
            'moved_at' => now()->toDateString(),
            'description' => 'Test negative adjustment',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity_on_hand' => 5]);
    }
}