<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\Asset;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\ProcurementRequest;
use App\Models\Revenue;
use App\Services\AssetService;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly AssetService $assets,
    ) {}

    public function summary()
    {
        $totals = $this->reports->dashboardTotals();

        $totalAssetsBookValue = Asset::all()->sum(fn (Asset $a) => $this->assets->depreciationSummary($a)['book_value']);
        $availableFunds = (float) Fund::sum('allocation') - (float) Fund::sum('used');

        return response()->json(['data' => [
            'totalRevenue' => $totals['total_revenue'],
            'totalExpenses' => $totals['total_expenses'],
            'netIncome' => $totals['net_income'],
            'outstandingReceivables' => $totals['outstanding_ar'],
            'outstandingPayables' => $totals['outstanding_ap'],
            'availableFunds' => $availableFunds,
            'totalAssets' => $totalAssetsBookValue,
            'budgetUtilizationPct' => $totals['budget_utilization_pct'],
        ]]);
    }

    public function recentTransactions()
    {
        $revenues = Revenue::latest('date')->take(8)->get()->map(fn (Revenue $r) => [
            'id' => sprintf('REV-%05d', $r->id), 'date' => $r->date->toDateString(), 'type' => 'Revenue',
            'description' => $r->description, 'department' => $r->department->name,
            'amount' => (float) $r->amount, 'status' => $r->status,
        ]);

        $expenses = Expense::latest('date')->take(8)->get()->map(fn (Expense $e) => [
            'id' => sprintf('EXP-%05d', $e->id), 'date' => $e->date->toDateString(), 'type' => 'Expense',
            'description' => $e->description, 'department' => $e->department->name,
            'amount' => (float) $e->amount, 'status' => $e->status,
        ]);

        $combined = $revenues->concat($expenses)
            ->sortByDesc('date')
            ->values()
            ->take(8);

        return response()->json(['data' => $combined]);
    }

    public function pendingActions()
    {
        $items = [];

        foreach (ProcurementRequest::where('status', 'Pending Review')->get() as $p) {
            $items[] = ['tone' => 'warning', 'text' => "Procurement request PR-{$p->id} awaiting review", 'sub' => $p->requester->name];
        }

        foreach (AccountsPayable::where('status', '!=', 'Paid')->get() as $p) {
            if ($p->isOverdue()) {
                $items[] = ['tone' => 'danger', 'text' => "Invoice AP-{$p->id} ({$p->vendor}) is overdue", 'sub' => number_format((float) $p->balance, 2).' outstanding'];
            }
        }

        foreach (AccountsReceivable::where('status', '!=', 'Paid')->get() as $r) {
            if ($r->isOverdue()) {
                $items[] = ['tone' => 'info', 'text' => "Receivable AR-{$r->id} ({$r->customer}) is overdue", 'sub' => number_format((float) $r->balance, 2).' outstanding'];
            }
        }

        foreach (Budget::all() as $b) {
            if ($b->utilizationPercent() >= 80) {
                $items[] = ['tone' => 'warning', 'text' => "Budget warning: {$b->category} at {$b->utilizationPercent()}%", 'sub' => 'Fiscal Year '.$b->fiscal_year];
            }
        }

        return response()->json(['data' => array_slice($items, 0, 8)]);
    }
}
