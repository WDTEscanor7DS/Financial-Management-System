<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntryRequest;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\GeneralLedgerService;
use Illuminate\Http\Request;

class GeneralLedgerController extends Controller
{
    public function __construct(private readonly GeneralLedgerService $service) {}

    public function accounts()
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return response()->json(['data' => $accounts]);
    }
    public function index(Request $request)
    {
        $entries = JournalEntry::with(['lines.account', 'creator', 'reversal'])
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
            ->orderByDesc('entry_date')
            ->get();

        return response()->json(['data' => $entries->map($this->transform(...))]);
    }

    public function store(JournalEntryRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $entry = $this->service->createEntry($data);

        return response()->json(['data' => $this->transform($entry)], 201);
    }

        public function reverse(JournalEntry $journalEntry, Request $request)
    {
        $reversal = $this->service->reverseEntry($journalEntry, $request->user()->id);

        return response()->json(['data' => $this->transform($reversal)], 201);
    }

    public function show(JournalEntry $journalEntry)
    {
        return response()->json(['data' => $this->transform($journalEntry->load('lines.account', 'creator'))]);
    }

        public function trialBalance()
    {
        $rows = \DB::table('journal_entry_lines')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->selectRaw('chart_of_accounts.id, chart_of_accounts.account_code, chart_of_accounts.account_name, chart_of_accounts.account_type, chart_of_accounts.normal_balance, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name', 'chart_of_accounts.account_type', 'chart_of_accounts.normal_balance')
            ->orderBy('chart_of_accounts.account_code')
            ->get()
            ->map(function ($row) {
                $net = $row->normal_balance === 'Debit'
                    ? $row->total_debit - $row->total_credit
                    : $row->total_credit - $row->total_debit;

                return [
                    'accountCode' => $row->account_code,
                    'accountName' => $row->account_name,
                    'accountType' => $row->account_type,
                    'totalDebit' => (float) $row->total_debit,
                    'totalCredit' => (float) $row->total_credit,
                    'netBalance' => (float) $net,
                    'normalBalance' => $row->normal_balance,
                ];
            });

        $grandDebit = $rows->sum('totalDebit');
        $grandCredit = $rows->sum('totalCredit');

        return response()->json([
            'data' => $rows,
            'grandTotalDebit' => $grandDebit,
            'grandTotalCredit' => $grandCredit,
            'isBalanced' => round($grandDebit, 2) === round($grandCredit, 2),
        ]);
    }

     private function transform(JournalEntry $e): array
    {
        return [
            'id' => sprintf('JE-%05d', $e->id),
            'raw_id' => $e->id,
            'entryDate' => $e->entry_date->toDateString(),
            'referenceNo' => $e->reference_no,
            'description' => $e->description,
            'sourceModule' => $e->source_module,
            'createdBy' => $e->creator?->name,
            'reversesEntryId' => $e->reverses_entry_id ? sprintf('JE-%05d', $e->reverses_entry_id) : null,
            'isReversed' => $e->relationLoaded('reversal') ? $e->reversal !== null : false,
            'lines' => $e->lines->map(fn ($l) => [
                'accountCode' => $l->account->account_code,
                'accountName' => $l->account->account_name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
                'description' => $l->description,
            ]),
        ];
    }
}