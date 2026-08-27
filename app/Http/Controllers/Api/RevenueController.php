<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevenueRequest;
use App\Models\Revenue;
use App\Services\RevenueService;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    public function __construct(private readonly RevenueService $service) {}

    public function index(Request $request)
    {
        $revenues = Revenue::with('department')
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('revenue_type'), fn ($q, $v) => $q->where('revenue_type', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('date'), fn ($q, $v) => $q->whereDate('date', $v))
            ->orderByDesc('date')
            ->get();

        return response()->json(['data' => $revenues->map($this->transform(...))]);
    }

    public function store(RevenueRequest $request)
    {
        $revenue = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($revenue)], 201);
    }

    public function update(RevenueRequest $request, Revenue $revenue)
    {
        $revenue = $this->service->update($revenue, $request->validated());

        return response()->json(['data' => $this->transform($revenue)]);
    }

    public function destroy(Revenue $revenue)
    {
        $this->service->delete($revenue);

        return response()->json(status: 204);
    }

    private function transform(Revenue $r): array
    {
        return [
            'id' => sprintf('REV-%05d', $r->id),
            'raw_id' => $r->id,
            'date' => $r->date->toDateString(),
            'revenueType' => $r->revenue_type,
            'description' => $r->description,
            'department' => $r->department->name,
            'departmentId' => $r->department_id,
            'payer' => $r->payer,
            'referenceNo' => $r->reference_no,
            'amount' => (float) $r->amount,
            'paymentMethod' => $r->payment_method,
            'status' => $r->status,
        ];
    }
}
