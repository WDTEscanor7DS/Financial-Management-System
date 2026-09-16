<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryItemRequest;
use App\Http\Requests\StockMovementRequest;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\InventoryService;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    public function index()
    {
        $items = InventoryItem::orderBy('item_name')->get()->map($this->transformItem(...));

        return response()->json(['data' => $items]);
    }

    public function storeItem(InventoryItemRequest $request)
    {
        $item = InventoryItem::create($request->validated() + ['quantity_on_hand' => 0, 'status' => 'Active']);

        return response()->json(['data' => $this->transformItem($item)], 201);
    }

    public function movements()
    {
        $movements = StockMovement::with(['item', 'creator'])
            ->orderByDesc('moved_at')
            ->get()
            ->map($this->transformMovement(...));

        return response()->json(['data' => $movements]);
    }

    public function storeMovement(StockMovementRequest $request)
    {
        $movement = $this->service->recordMovement($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transformMovement($movement)], 201);
    }

    private function transformItem(InventoryItem $i): array
    {
        return [
            'id' => $i->id,
            'itemCode' => $i->item_code,
            'itemName' => $i->item_name,
            'category' => $i->category,
            'unitOfMeasure' => $i->unit_of_measure,
            'unitCost' => (float) $i->unit_cost,
            'quantityOnHand' => $i->quantity_on_hand,
            'reorderLevel' => $i->reorder_level,
            'isLowStock' => $i->isLowStock(),
            'status' => $i->status,
        ];
    }

    private function transformMovement(StockMovement $m): array
    {
        return [
            'id' => sprintf('SM-%05d', $m->id),
            'itemName' => $m->item->item_name,
            'type' => $m->movement_type,
            'quantity' => $m->quantity,
            'referenceNo' => $m->reference_no,
            'description' => $m->description,
            'movedAt' => $m->moved_at->toDateString(),
            'createdBy' => $m->creator?->name,
        ];
    }
}