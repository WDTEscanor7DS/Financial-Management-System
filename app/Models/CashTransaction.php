<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $fillable = [
        'bank_account_id', 'transaction_date', 'type', 'amount', 'reference_no',
        'description', 'transfer_to_account_id', 'contra_account_id', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transferToAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'transfer_to_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'contra_account_id');
    }
}