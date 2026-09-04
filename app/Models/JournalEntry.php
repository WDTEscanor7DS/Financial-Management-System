<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_date', 'reference_no', 'description', 'source_module', 'source_id', 'reverses_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
        public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_entry_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reverses_entry_id');
    }
}