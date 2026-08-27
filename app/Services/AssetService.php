<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class AssetService
{
    public function create(array $data, int $userId): Asset
    {
        return DB::transaction(function () use ($data, $userId) {
            $asset = Asset::create([...$data, 'created_by' => $userId]);
            AuditService::log('Created Asset', 'Asset & Depreciation', (string) $asset->id);

            return $asset;
        });
    }

    public function update(Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset->update($data);
            AuditService::log('Updated Asset', 'Asset & Depreciation', (string) $asset->id);

            return $asset->refresh();
        });
    }

    public function delete(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $id = $asset->id;
            $asset->delete();
            AuditService::log('Deleted Asset', 'Asset & Depreciation', (string) $id);
        });
    }

    /**
     * Depreciation is computed on read (see Asset model docblock) -- this
     * just packages the three figures the UI already displays per asset.
     */
    public function depreciationSummary(Asset $asset): array
    {
        return [
            'annual' => round($asset->annualDepreciation(), 2),
            'accumulated' => round($asset->accumulatedDepreciation(), 2),
            'book_value' => round($asset->bookValue(), 2),
        ];
    }
}
