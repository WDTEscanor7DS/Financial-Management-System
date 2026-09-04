<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundAllocation extends Model
{
    protected $fillable = ['fund_id', 'amount', 'description', 'allocated_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
