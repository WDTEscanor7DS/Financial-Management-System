<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountsReceivable extends Model
{
    protected $table = 'accounts_receivable';

    protected $fillable = [
        'customer', 'reference_no', 'description', 'invoice_date', 'due_date',
        'amount', 'amount_paid', 'balance', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ArPayment::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'Paid' && now()->toDateString() > $this->due_date->toDateString();
    }
}
