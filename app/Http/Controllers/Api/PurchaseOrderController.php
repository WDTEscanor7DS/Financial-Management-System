<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index()
    {
        $orders = PurchaseOrder::with(['items.inventoryItem', 'approvalRequest.actions.step', 'creator'])
            ->orderByDesc('order_date')
            ->get()
            ->map(function ($po) {
                $po = $this->service->syncApprovalStatus($po);
                return $this->transform($po);
            });

        return response()->json(['data' => $orders]);
    }

    public function store(PurchaseOrderRequest $request)
    {
        $data = $request->validated();

        $po = DB::transaction(function () use ($data, $request) {
            $totalAmount = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_cost']);

            $po = PurchaseOrder::create([
                'po_number' => 'PO-' . str_pad((string) (PurchaseOrder::max('id') + 1), 5, '0', STR_PAD_LEFT),
                'supplier_name' => $data['supplier_name'],
                'order_date' => $data['order_date'],
                'status' => 'Draft',
                'total_amount' => $totalAmount,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            return $po;
        });

        return response()->json(['data' => $this->transform($po->load('items.inventoryItem'))], 201);
    }

    public function submit(PurchaseOrder $purchaseOrder, \Illuminate\Http\Request $request)
    {
        $po = $this->service->submitForApproval($purchaseOrder, $request->user()->id);

        return response()->json(['data' => $this->transform($po)]);
    }

    public function receive(PurchaseOrder $purchaseOrder, \Illuminate\Http\Request $request)
    {
        $po = $this->service->receive($purchaseOrder, $request->user()->id);

        return response()->json(['data' => $this->transform($po)]);
    }

    private function transform(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'poNumber' => $po->po_number,
            'supplierName' => $po->supplier_name,
            'orderDate' => $po->order_date->toDateString(),
            'status' => $po->status,
            'totalAmount' => (float) $po->total_amount,
            'journalEntryId' => $po->journal_entry_id ? sprintf('JE-%05d', $po->journal_entry_id) : null,
            'createdBy' => $po->creator?->name,
            'items' => $po->items->map(fn ($i) => [
                'itemName' => $i->inventoryItem->item_name,
                'quantity' => $i->quantity,
                'unitCost' => (float) $i->unit_cost,
                'lineTotal' => (float) $i->line_total,
            ]),
            'approvalSteps' => $po->approvalRequest?->actions?->map(fn ($a) => [
                'stepName' => $a->step->step_name,
                'status' => $a->status,
                'actedBy' => $a->actor?->name,
            ]) ?? [],
        ];
    }
}