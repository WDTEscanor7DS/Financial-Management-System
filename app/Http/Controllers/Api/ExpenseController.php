<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request)
    {
        $expenses = Expense::with(['department', 'budget'])
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('expense_category'), fn ($q, $v) => $q->where('expense_category', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('date')
            ->get();

        return response()->json(['data' => $expenses->map($this->transform(...))]);
    }

    public function store(ExpenseRequest $request)
    {
        $expense = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($expense)], 201);
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $expense = $this->service->update($expense, $request->validated());

        return response()->json(['data' => $this->transform($expense)]);
    }

    public function destroy(Expense $expense)
    {
        $this->service->delete($expense);

        return response()->json(status: 204);
    }

    private function transform(Expense $e): array
    {
        return [
            'id' => sprintf('EXP-%05d', $e->id),
            'raw_id' => $e->id,
            'date' => $e->date->toDateString(),
            'department' => $e->department->name,
            'departmentId' => $e->department_id,
            'expenseCategory' => $e->expense_category,
            'description' => $e->description,
            'vendor' => $e->vendor,
            'referenceNo' => $e->reference_no,
            'amount' => (float) $e->amount,
            'paymentMethod' => $e->payment_method,
            'status' => $e->status,
            'budgetId' => $e->budget_id ? sprintf('BUD-%05d', $e->budget_id) : null,
            'budgetRawId' => $e->budget_id,
        ];
    }
}
