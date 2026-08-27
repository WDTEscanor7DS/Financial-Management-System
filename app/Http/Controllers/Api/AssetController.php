<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $service) {}

    public function index(Request $request)
    {
        $assets = Asset::with('department')
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('asset_name')
            ->get();

        return response()->json(['data' => $assets->map($this->transform(...))]);
    }

    public function store(AssetRequest $request)
    {
        $asset = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($asset)], 201);
    }

    public function update(AssetRequest $request, Asset $asset)
    {
        $asset = $this->service->update($asset, $request->validated());

        return response()->json(['data' => $this->transform($asset)]);
    }

    public function destroy(Asset $asset)
    {
        $this->service->delete($asset);

        return response()->json(status: 204);
    }

    private function transform(Asset $a): array
    {
        $depreciation = $this->service->depreciationSummary($a);

        return [
            'id' => sprintf('AST-%05d', $a->id),
            'raw_id' => $a->id,
            'assetName' => $a->asset_name,
            'category' => $a->category,
            'serialNo' => $a->serial_no,
            'purchaseDate' => $a->purchase_date->toDateString(),
            'purchaseCost' => (float) $a->purchase_cost,
            'usefulLife' => $a->useful_life,
            'salvageValue' => (float) $a->salvage_value,
            'department' => $a->department->name,
            'departmentId' => $a->department_id,
            'location' => $a->location,
            'status' => $a->status,
            'annualDepreciation' => $depreciation['annual'],
            'accumulatedDepreciation' => $depreciation['accumulated'],
            'bookValue' => $depreciation['book_value'],
        ];
    }
}
