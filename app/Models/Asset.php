<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = [
        'asset_name', 'category', 'serial_no', 'purchase_date', 'purchase_cost',
        'useful_life', 'salvage_value', 'department_id', 'location', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Straight-line depreciation, computed on read rather than stored --
     * see the migration note in create_assets_table for why there is no
     * separate depreciation-schedule table yet.
     */
    public function annualDepreciation(): float
    {
        $life = max(1, (int) $this->useful_life);

        return ((float) $this->purchase_cost - (float) $this->salvage_value) / $life;
    }

    public function accumulatedDepreciation(): float
    {
        $depreciableBase = (float) $this->purchase_cost - (float) $this->salvage_value;
        $yearsElapsed = max(0, $this->purchase_date->diffInDays(now()) / 365);

        return min($depreciableBase, $this->annualDepreciation() * $yearsElapsed);
    }

    public function bookValue(): float
    {
        return (float) $this->purchase_cost - $this->accumulatedDepreciation();
    }
}
