<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequest extends Model
{
    protected $fillable = [
        'requester_id', 'department_id', 'request_type', 'description',
        'quantity', 'estimated_cost', 'priority', 'date_submitted',
        'status', 'reviewer_id', 'reviewed_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_submitted' => 'date',
            'reviewed_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The only legal forward transitions in the request workflow (Section
     * 14). ProcurementService rejects any transition not listed here,
     * regardless of which role is calling it.
     */
    public static function allowedTransitions(): array
    {
        return [
            'Pending Review' => ['Approved', 'Rejected'],
            'Approved' => ['Procurement Processing'],
            'Procurement Processing' => ['Completed'],
            'Rejected' => [],
            'Completed' => [],
        ];
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::allowedTransitions()[$this->status] ?? [], true);
    }
}
