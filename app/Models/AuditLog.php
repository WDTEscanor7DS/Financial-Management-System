<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false; // created_at only, set via DB default

    protected $fillable = [
        'user_id', 'role', 'action', 'module', 'record_type', 'record_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Audit rows are append-only (Section 17/44): the application layer
     * never issues an UPDATE or DELETE against this table. Overriding these
     * two methods to always fail is a deliberate belt-and-braces guard in
     * case a future developer calls ->update() or ->delete() on a fetched
     * AuditLog instance by mistake.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Audit log entries are immutable and cannot be updated.');
    }

    public function delete(): bool|null
    {
        throw new \RuntimeException('Audit log entries cannot be deleted through the application.');
    }
}
