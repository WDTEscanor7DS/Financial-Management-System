<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function income()
    {
        return response()->json(['data' => $this->reports->incomeStatement()]);
    }

    public function budgetVsActual()
    {
        return response()->json(['data' => $this->reports->budgetVsActual()]);
    }

    public function aging()
    {
        return response()->json(['data' => [
            'ap' => $this->reports->apAging(),
            'ar' => $this->reports->arAging(),
        ]]);
    }

    public function cashFlow(Request $request)
    {
        $beginning = (float) $request->query('beginning_balance', 0);

        return response()->json(['data' => $this->reports->cashFlowSummary($beginning)]);
    }

    public function expenses(Request $request)
    {
        $rows = $this->reports->expenseReport([
            'date' => $request->query('date'),
            'department_id' => $request->query('department_id'),
            'category' => $request->query('category'),
        ]);

        AuditService::log('Generated Report', 'Financial Reporting', null, 'Generated filtered expense report.');

        return response()->json(['data' => $rows->map(fn ($e) => [
            'date' => $e->date->toDateString(), 'department' => $e->department->name,
            'category' => $e->expense_category, 'description' => $e->description, 'amount' => (float) $e->amount,
        ])]);
    }

    public function revenues(Request $request)
    {
        $rows = $this->reports->revenueReport([
            'date' => $request->query('date'),
            'department_id' => $request->query('department_id'),
            'type' => $request->query('type'),
        ]);

        AuditService::log('Generated Report', 'Financial Reporting', null, 'Generated filtered revenue report.');

        return response()->json(['data' => $rows->map(fn ($r) => [
            'date' => $r->date->toDateString(), 'department' => $r->department->name,
            'type' => $r->revenue_type, 'description' => $r->description, 'amount' => (float) $r->amount,
        ])]);
    }
}
