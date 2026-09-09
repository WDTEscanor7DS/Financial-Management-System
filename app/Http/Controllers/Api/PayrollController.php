<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollPeriodRequest;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function index()
    {
        $periods = PayrollPeriod::withCount('payslips')
            ->orderByDesc('start_date')
            ->get()
            ->map($this->transform(...));

        return response()->json(['data' => $periods]);
    }

    public function store(PayrollPeriodRequest $request)
    {
        $period = PayrollPeriod::create($request->validated() + [
            'status' => 'Draft',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->transform($period)], 201);
    }

    public function show(PayrollPeriod $payrollPeriod)
    {
        $payrollPeriod->load('payslips.employee');

        return response()->json([
            'data' => $this->transform($payrollPeriod),
            'payslips' => $payrollPeriod->payslips->map(fn ($p) => [
                'id' => $p->id,
                'employeeName' => $p->employee->full_name,
                'basicPay' => (float) $p->basic_pay,
                'sssDeduction' => (float) $p->sss_deduction,
                'philhealthDeduction' => (float) $p->philhealth_deduction,
                'pagibigDeduction' => (float) $p->pagibig_deduction,
                'withholdingTax' => (float) $p->withholding_tax,
                'totalDeductions' => (float) $p->total_deductions,
                'netPay' => (float) $p->net_pay,
            ]),
        ]);
    }

    public function generate(PayrollPeriod $payrollPeriod)
    {
        $period = $this->service->generatePayslips($payrollPeriod);

        return response()->json(['data' => $this->transform($period)]);
    }

    public function post(PayrollPeriod $payrollPeriod, Request $request)
    {
        $period = $this->service->postToLedger($payrollPeriod, $request->user()->id);

        return response()->json(['data' => $this->transform($period)]);
    }

    private function transform(PayrollPeriod $p): array
    {
        return [
            'id' => $p->id,
            'periodLabel' => $p->period_label,
            'startDate' => $p->start_date->toDateString(),
            'endDate' => $p->end_date->toDateString(),
            'status' => $p->status,
            'payslipCount' => $p->payslips_count ?? $p->payslips()->count(),
            'journalEntryId' => $p->journal_entry_id ? sprintf('JE-%05d', $p->journal_entry_id) : null,
        ];
    }
}