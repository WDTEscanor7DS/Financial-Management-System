<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function recordMovement(array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var InventoryItem $item */
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);

            $type = $data['type'];
            $quantity = (int) $data['quantity'];

            if ($type === 'Stock In') {
                $item->increment('quantity_on_hand', $quantity);
            } elseif ($type === 'Stock Out') {
                if ($quantity > $item->quantity_on_hand) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stock out quantity exceeds available stock (' . $item->quantity_on_hand . ' on hand).',
                    ]);
                }
                $item->decrement('quantity_on_hand', $quantity);
            } else { // Adjustment
                $newQuantity = $item->quantity_on_hand + $quantity;
                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Adjustment would result in negative stock.',
                    ]);
                }
                $item->update(['quantity_on_hand' => $newQuantity]);
            }

            $movement = StockMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type' => $type,
                'quantity' => $quantity,
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'created_by' => $userId,
                'moved_at' => $data['moved_at'],
            ]);

            AuditService::log('Recorded ' . $type, 'Inventory', (string) $movement->id);

            return $movement->load('item', 'creator');
        });
    }
}