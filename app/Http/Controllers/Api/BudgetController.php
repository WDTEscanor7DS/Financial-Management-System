<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    public function index(Request $request)
    {
        $budgets = Budget::with('department')
            ->when($request->query('fiscal_year'), fn ($q, $v) => $q->where('fiscal_year', $v))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $budgets->map($this->transform(...))]);
    }

    public function store(BudgetRequest $request)
    {
        $budget = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($budget)], 201);
    }

    public function update(BudgetRequest $request, Budget $budget)
    {
        $budget = $this->service->update($budget, $request->validated());

        return response()->json(['data' => $this->transform($budget)]);
    }

    public function destroy(Budget $budget)
    {
        $this->service->delete($budget);

        return response()->json(status: 204);
    }

    private function transform(Budget $b): array
    {
        return [
            'id' => sprintf('BUD-%05d', $b->id),
            'raw_id' => $b->id,
            'fiscalYear' => $b->fiscal_year,
            'department' => $b->department->name,
            'departmentId' => $b->department_id,
            'category' => $b->category,
            'allocated' => (float) $b->allocated,
            'actualSpending' => (float) $b->actual_spending,
            'remaining' => $b->remaining(),
            'utilization' => $b->utilizationPercent(),
            'status' => $b->status,
            'createdAt' => optional($b->created_at)->toDateString(),
        ];
    }
}
