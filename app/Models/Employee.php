<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'employee_no', 'full_name', 'department_id', 'position',
        'employment_type', 'monthly_rate', 'status', 'hire_date',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rate' => 'decimal:2',
            'hire_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}