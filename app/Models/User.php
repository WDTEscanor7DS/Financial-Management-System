<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'department_id', 'role_id', 'name', 'email', 'password', 'status',
    ];

    // Never serialize the password hash or remember token -- Section 29 of
    // the brief: passwords must never appear in API responses.
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed', // Laravel bcrypt-hashes on assignment
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function procurementRequestsMade(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class, 'requester_id');
    }

    public function procurementRequestsReviewed(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class, 'reviewer_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Bridges Laravel authorization (Gate::allows() / @can()) to our RBAC
     * permission slugs, so controllers can call $user->can('create_revenue')
     * the same way they would call a normal Gate ability. Falls back to the
     * parent implementation for anything registered as a real Gate ability
     * or Policy method name (e.g. 'update' on a Model instance).
     */
    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && $this->role && str_contains($abilities, '_') && ! str_contains($abilities, '::')) {
            if ($this->role->hasPermission($abilities)) {
                return true;
            }
        }

        return parent::can($abilities, $arguments);
    }
}
