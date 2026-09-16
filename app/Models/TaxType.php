<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxType extends Model
{
    protected $fillable = [
        'code', 'name', 'chart_of_account_id', 'bir_form_no', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(TaxRemittance::class);
    }
}