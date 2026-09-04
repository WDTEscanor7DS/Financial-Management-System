<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AllocateFundRequest;
use App\Http\Requests\FundRequest;
use App\Models\Fund;
use App\Services\FundService;

class FundController extends Controller
{
    public function __construct(private readonly FundService $service) {}

    public function index()
    {
        $funds = Fund::with('department')->orderBy('name')->get();

        return response()->json(['data' => $funds->map($this->transform(...))]);
    }

    public function store(FundRequest $request)
    {
        $fund = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($fund)], 201);
    }

    public function allocate(AllocateFundRequest $request, Fund $fund)
    {
        $fund = $this->service->allocate(
            $fund->id,
            (float) $request->input('amount'),
            $request->input('description'),
            $request->user()->id
        );

        return response()->json(['data' => $this->transform($fund)]);
    }

    public function destroy(Fund $fund)
    {
        $this->service->delete($fund);

        return response()->json(status: 204);
    }

    private function transform(Fund $f): array
    {
        return [
            'id' => sprintf('FND-%04d', $f->id),
            'raw_id' => $f->id,
            'name' => $f->name,
            'type' => $f->type,
            'department' => $f->department->name,
            'departmentId' => $f->department_id,
            'allocation' => (float) $f->allocation,
            'used' => (float) $f->used,
            'remaining' => $f->remaining(),
            'status' => $f->status,
        ];
    }
}
