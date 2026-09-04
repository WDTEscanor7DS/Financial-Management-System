<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $fillable = [
        'account_code', 'account_name', 'account_type', 'normal_balance', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }
}