<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayableRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\AccountsPayable;
use App\Services\PayableService;
use Illuminate\Http\Request;

class AccountsPayableController extends Controller
{
    public function __construct(private readonly PayableService $service) {}

    public function index(Request $request)
    {
        $payables = AccountsPayable::with('department')
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $payables->map($this->transform(...))]);
    }

    public function store(PayableRequest $request)
    {
        $payable = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($payable)], 201);
    }

    public function recordPayment(PaymentRequest $request, AccountsPayable $accountsPayable)
    {
        $payable = $this->service->recordPayment($accountsPayable->id, (float) $request->input('amount'), $request->user()->id);

        return response()->json(['data' => $this->transform($payable)]);
    }

    public function destroy(AccountsPayable $accountsPayable)
    {
        $this->service->delete($accountsPayable);

        return response()->json(status: 204);
    }

    private function transform(AccountsPayable $p): array
    {
        return [
            'id' => sprintf('AP-%05d', $p->id),
            'raw_id' => $p->id,
            'vendor' => $p->vendor,
            'invoiceNo' => $p->invoice_no,
            'invoiceDate' => $p->invoice_date->toDateString(),
            'dueDate' => $p->due_date->toDateString(),
            'description' => $p->description,
            'department' => $p->department->name,
            'departmentId' => $p->department_id,
            'amount' => (float) $p->amount,
            'amountPaid' => (float) $p->amount_paid,
            'balance' => (float) $p->balance,
            'status' => $p->status,
            'isOverdue' => $p->isOverdue(),
        ];
    }
}
