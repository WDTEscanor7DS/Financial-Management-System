<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $fillable = [
        'department_id', 'fiscal_year', 'category', 'allocated',
        'actual_spending', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated' => 'decimal:2',
            'actual_spending' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function remaining(): float
    {
        return (float) $this->allocated - (float) $this->actual_spending;
    }

    public function utilizationPercent(): int
    {
        if ((float) $this->allocated <= 0) {
            return 0;
        }

        return (int) round(((float) $this->actual_spending / (float) $this->allocated) * 100);
    }
}
