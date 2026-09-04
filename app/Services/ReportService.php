<?php

namespace App\Services;

use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Section 16: reports never read from a separate "report table" -- every
 * method here queries the live transactional tables directly, the same way
 * the existing frontend's reports.js computes figures from the in-memory
 * arrays returned by data.js. This also means the dashboard and the
 * Reports module can never disagree with each other, since both call into
 * this same service.
 */
class ReportService
{
    public function dashboardTotals(): array
    {
        $totalRevenue = (float) Revenue::sum('amount');
        $totalExpenses = (float) Expense::sum('amount');
        $outstandingAr = (float) AccountsReceivable::where('status', '!=', 'Paid')->sum('balance');
        $outstandingAp = (float) AccountsPayable::where('status', '!=', 'Paid')->sum('balance');
        $totalAllocated = (float) Budget::sum('allocated');
        $totalActual = (float) Budget::sum('actual_spending');

        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => $totalRevenue - $totalExpenses,
            'outstanding_ar' => $outstandingAr,
            'outstanding_ap' => $outstandingAp,
            'budget_utilization_pct' => $totalAllocated > 0 ? (int) round(($totalActual / $totalAllocated) * 100) : 0,
        ];
    }

    public function incomeStatement(): array
    {
        $revenue = (float) Revenue::sum('amount');
        $expenses = (float) Expense::sum('amount');

        return ['revenue' => $revenue, 'expenses' => $expenses, 'net' => $revenue - $expenses];
    }

    public function budgetVsActual(): Collection
    {
        return Budget::with('department')->get()->map(fn (Budget $b) => [
            'department' => $b->department->name,
            'category' => $b->category,
            'allocated' => (float) $b->allocated,
            'actual' => (float) $b->actual_spending,
            'variance' => (float) $b->allocated - (float) $b->actual_spending,
            'utilization_pct' => $b->utilizationPercent(),
        ]);
    }

    public function apAging(): array
    {
        return $this->agingBuckets(
            AccountsPayable::where('status', '!=', 'Paid')->get(['due_date', 'balance'])
        );
    }

    public function arAging(): array
    {
        return $this->agingBuckets(
            AccountsReceivable::where('status', '!=', 'Paid')->get(['due_date', 'balance'])
        );
    }

    private function agingBuckets(Collection $rows): array
    {
        $buckets = ['Current' => 0.0, '1-30 Days' => 0.0, '31-60 Days' => 0.0, '61-90 Days' => 0.0, '90+ Days' => 0.0];

        foreach ($rows as $row) {
            $overdueDays = Carbon::parse($row->due_date)->diffInDays(now(), false);
            $bucket = match (true) {
                $overdueDays <= 0 => 'Current',
                $overdueDays <= 30 => '1-30 Days',
                $overdueDays <= 60 => '31-60 Days',
                $overdueDays <= 90 => '61-90 Days',
                default => '90+ Days',
            };
            $buckets[$bucket] += (float) $row->balance;
        }

        return $buckets;
    }

    public function cashFlowSummary(float $beginningBalance = 0.0): array
    {
        $inflows = (float) Revenue::sum('amount') + (float) ArPayment::sum('amount');
        $outflows = (float) Expense::sum('amount') + (float) ApPayment::sum('amount');

        return [
            'beginning_balance' => $beginningBalance,
            'inflows' => $inflows,
            'outflows' => $outflows,
            'ending_balance' => $beginningBalance + $inflows - $outflows,
        ];
    }

    public function expenseReport(array $filters): Collection
    {
        return Expense::with('department')
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('date', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('expense_category', $v))
            ->orderByDesc('date')
            ->get();
    }

    public function revenueReport(array $filters): Collection
    {
        return Revenue::with('department')
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('date', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('revenue_type', $v))
            ->orderByDesc('date')
            ->get();
    }
}
