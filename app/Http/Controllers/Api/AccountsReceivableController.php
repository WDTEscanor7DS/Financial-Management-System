<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\ReceivableRequest;
use App\Models\AccountsReceivable;
use App\Services\ReceivableService;
use Illuminate\Http\Request;

class AccountsReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $service) {}

    public function index(Request $request)
    {
        $receivables = AccountsReceivable::when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $receivables->map($this->transform(...))]);
    }

    public function store(ReceivableRequest $request)
    {
        $receivable = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($receivable)], 201);
    }

    public function recordPayment(PaymentRequest $request, AccountsReceivable $accountsReceivable)
    {
        $receivable = $this->service->recordPayment($accountsReceivable->id, (float) $request->input('amount'), $request->user()->id);

        return response()->json(['data' => $this->transform($receivable)]);
    }

    public function destroy(AccountsReceivable $accountsReceivable)
    {
        $this->service->delete($accountsReceivable);

        return response()->json(status: 204);
    }

    private function transform(AccountsReceivable $r): array
    {
        return [
            'id' => sprintf('AR-%05d', $r->id),
            'raw_id' => $r->id,
            'customer' => $r->customer,
            'referenceNo' => $r->reference_no,
            'description' => $r->description,
            'invoiceDate' => $r->invoice_date->toDateString(),
            'dueDate' => $r->due_date->toDateString(),
            'amount' => (float) $r->amount,
            'amountPaid' => (float) $r->amount_paid,
            'balance' => (float) $r->balance,
            'status' => $r->status,
            'isOverdue' => $r->isOverdue(),
        ];
    }
}
