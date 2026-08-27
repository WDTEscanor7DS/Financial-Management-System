<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    protected $fillable = [
        'name', 'type', 'department_id', 'allocation', 'used', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'allocation' => 'decimal:2',
            'used' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FundAllocation::class);
    }

    public function remaining(): float
    {
        return (float) $this->allocation - (float) $this->used;
    }
}
