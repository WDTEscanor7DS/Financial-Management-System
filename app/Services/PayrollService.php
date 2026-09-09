<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private readonly GeneralLedgerService $ledger) {}

    // NOTE: these rates are simplified for prototype/demo purposes only.
    // They do NOT reflect the actual SSS/PhilHealth/Pag-IBIG contribution
    // tables or the BIR withholding tax brackets, which are far more
    // detailed and change periodically. Do not use for real payroll.
    private const SSS_RATE = 0.045;
    private const PHILHEALTH_RATE = 0.02;
    private const PAGIBIG_RATE = 0.02;

    private function withholdingTax(float $basicPay): float
    {
        if ($basicPay > 33333) return $basicPay * 0.15;
        if ($basicPay > 20833) return $basicPay * 0.10;
        return 0;
    }

    public function generatePayslips(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status !== 'Draft') {
            throw ValidationException::withMessages([
                'status' => 'Payslips can only be generated for a Draft payroll period.',
            ]);
        }

        return DB::transaction(function () use ($period) {
            $employees = Employee::where('status', 'Active')->get();

            foreach ($employees as $employee) {
                $basicPay = (float) $employee->monthly_rate;
                $sss = round($basicPay * self::SSS_RATE, 2);
                $philhealth = round($basicPay * self::PHILHEALTH_RATE, 2);
                $pagibig = round($basicPay * self::PAGIBIG_RATE, 2);
                $tax = round($this->withholdingTax($basicPay), 2);
                $totalDeductions = round($sss + $philhealth + $pagibig + $tax, 2);

                Payslip::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'basic_pay' => $basicPay,
                    'sss_deduction' => $sss,
                    'philhealth_deduction' => $philhealth,
                    'pagibig_deduction' => $pagibig,
                    'withholding_tax' => $tax,
                    'other_deductions' => 0,
                    'gross_pay' => $basicPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => round($basicPay - $totalDeductions, 2),
                ]);
            }

            $period->update(['status' => 'Processed']);

            return $period->load('payslips.employee');
        });
    }

    public function postToLedger(PayrollPeriod $period, int $userId): PayrollPeriod
    {
        if ($period->status !== 'Processed') {
            throw ValidationException::withMessages([
                'status' => 'Only a Processed payroll period can be posted to the ledger.',
            ]);
        }

        $payslips = $period->payslips;

        if ($payslips->isEmpty()) {
            throw ValidationException::withMessages([
                'payslips' => 'This payroll period has no payslips to post.',
            ]);
        }

        return DB::transaction(function () use ($period, $payslips, $userId) {
            $totals = [
                'gross' => $payslips->sum('gross_pay'),
                'sss' => $payslips->sum('sss_deduction'),
                'philhealth' => $payslips->sum('philhealth_deduction'),
                'pagibig' => $payslips->sum('pagibig_deduction'),
                'tax' => $payslips->sum('withholding_tax'),
                'net' => $payslips->sum('net_pay'),
            ];

            $accountId = fn (string $code) => ChartOfAccount::where('account_code', $code)->value('id');

            $lines = [
                ['account_id' => $accountId('5100'), 'debit' => $totals['gross'], 'credit' => 0, 'description' => 'Payroll expense'],
                ['account_id' => $accountId('2200'), 'debit' => 0, 'credit' => $totals['sss'], 'description' => 'SSS payable'],
                ['account_id' => $accountId('2210'), 'debit' => 0, 'credit' => $totals['philhealth'], 'description' => 'PhilHealth payable'],
                ['account_id' => $accountId('2220'), 'debit' => 0, 'credit' => $totals['pagibig'], 'description' => 'Pag-IBIG payable'],
                ['account_id' => $accountId('2230'), 'debit' => 0, 'credit' => $totals['tax'], 'description' => 'Withholding tax payable'],
                ['account_id' => $accountId('2240'), 'debit' => 0, 'credit' => $totals['net'], 'description' => 'Salaries payable (net pay)'],
            ];

            // Remove zero-amount lines (e.g. no employee crossed a tax bracket)
            // so the entry doesn't carry meaningless ₱0 rows.
            $lines = array_values(array_filter($lines, fn ($l) => $l['debit'] > 0 || $l['credit'] > 0));

            $entry = $this->ledger->createEntry([
                'entry_date' => $period->end_date,
                'reference_no' => 'PR-' . $period->id,
                'description' => 'Payroll posting — ' . $period->period_label,
                'source_module' => 'Payroll',
                'source_id' => $period->id,
                'created_by' => $userId,
                'lines' => $lines,
            ]);

            $period->update(['status' => 'Posted', 'journal_entry_id' => $entry->id]);

            AuditService::log('Posted Payroll', 'Payroll', (string) $period->id);

            return $period->load('payslips.employee', 'journalEntry');
        });
    }
}