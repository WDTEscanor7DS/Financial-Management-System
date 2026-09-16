<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly ApprovalService $approval,
        private readonly InventoryService $inventory,
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function submitForApproval(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        if ($po->status !== 'Draft') {
            throw ValidationException::withMessages([
                'status' => 'Only a Draft purchase order can be submitted for approval.',
            ]);
        }

        return DB::transaction(function () use ($po, $userId) {
            $request = $this->approval->createRequest(
                'purchase_order',
                'PurchaseOrder',
                $po->id,
                'PO ' . $po->po_number . ' — ' . $po->supplier_name . ' (\u20b1' . number_format($po->total_amount, 2) . ')',
                $userId
            );

            $po->update([
                'status' => 'Pending Approval',
                'approval_request_id' => $request->id,
            ]);

            return $po->fresh()->load('items.inventoryItem', 'approvalRequest.actions.step');
        });
    }

    public function syncApprovalStatus(PurchaseOrder $po): PurchaseOrder
    {
        $po->load('approvalRequest');

        if ($po->status === 'Pending Approval' && $po->approvalRequest) {
            if ($po->approvalRequest->status === 'Approved') {
                $po->update(['status' => 'Approved']);
            } elseif ($po->approvalRequest->status === 'Rejected') {
                $po->update(['status' => 'Rejected']);
            }
        }

        return $po->fresh()->load('items.inventoryItem', 'approvalRequest.actions.step');
    }

    public function receive(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        if ($po->status !== 'Approved') {
            throw ValidationException::withMessages([
                'status' => 'Only an Approved purchase order can be received.',
            ]);
        }

        return DB::transaction(function () use ($po, $userId) {
            $po->load('items.inventoryItem');

            foreach ($po->items as $item) {
                $this->inventory->recordMovement([
                    'inventory_item_id' => $item->inventory_item_id,
                    'type' => 'Stock In',
                    'quantity' => $item->quantity,
                    'reference_no' => $po->po_number,
                    'description' => 'Received from PO ' . $po->po_number . ' — ' . $po->supplier_name,
                    'moved_at' => now()->toDateString(),
                ], $userId);
            }

            $inventoryAccountId = ChartOfAccount::where('account_code', '1200')->value('id');
            $payableAccountId = ChartOfAccount::where('account_code', '2000')->value('id');

            $entry = $this->ledger->createEntry([
                'entry_date' => now()->toDateString(),
                'reference_no' => $po->po_number,
                'description' => 'Goods received — PO ' . $po->po_number . ' (' . $po->supplier_name . ')',
                'source_module' => 'PurchaseOrder',
                'source_id' => $po->id,
                'created_by' => $userId,
                'lines' => [
                    ['account_id' => $inventoryAccountId, 'debit' => $po->total_amount, 'credit' => 0, 'description' => 'Inventory received'],
                    ['account_id' => $payableAccountId, 'debit' => 0, 'credit' => $po->total_amount, 'description' => 'Payable to ' . $po->supplier_name],
                ],
            ]);

            $po->update([
                'status' => 'Received',
                'journal_entry_id' => $entry->id,
                'received_at' => now(),
            ]);

            AuditService::log('Received Purchase Order', 'Purchase Order', (string) $po->id);

            return $po->fresh()->load('items.inventoryItem', 'journalEntry');
        });
    }
}