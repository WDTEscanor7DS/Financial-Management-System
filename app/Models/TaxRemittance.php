<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRemittance extends Model
{
    protected $fillable = [
        'tax_type_id', 'period_covered', 'remittance_date', 'amount',
        'bir_reference_no', 'bank_account_id', 'cash_transaction_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'remittance_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}