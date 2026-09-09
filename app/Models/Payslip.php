<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'basic_pay', 'sss_deduction',
        'philhealth_deduction', 'pagibig_deduction', 'withholding_tax',
        'other_deductions', 'gross_pay', 'total_deductions', 'net_pay',
    ];

    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'sss_deduction' => 'decimal:2',
            'philhealth_deduction' => 'decimal:2',
            'pagibig_deduction' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}