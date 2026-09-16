<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxRemittanceRequest;
use App\Models\TaxRemittance;
use App\Models\TaxType;
use App\Services\TaxService;

class TaxController extends Controller
{
    public function __construct(private readonly TaxService $service) {}

    public function taxTypes()
    {
        $types = TaxType::where('is_active', true)
            ->with('chartOfAccount')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'birFormNo' => $t->bir_form_no,
                'accountCode' => $t->chartOfAccount->account_code,
            ]);

        return response()->json(['data' => $types]);
    }

    public function index()
    {
        $remittances = TaxRemittance::with(['taxType', 'bankAccount', 'cashTransaction.journalEntry'])
            ->orderByDesc('remittance_date')
            ->get()
            ->map($this->transform(...));

        return response()->json(['data' => $remittances]);
    }

    public function store(TaxRemittanceRequest $request)
    {
        $remittance = $this->service->remit($request->validated(), $request->user()->id);

        return response()->json(['data' => $this->transform($remittance)], 201);
    }

    private function transform(TaxRemittance $r): array
    {
        return [
            'id' => sprintf('TR-%05d', $r->id),
            'taxTypeName' => $r->taxType->name,
            'periodCovered' => $r->period_covered,
            'remittanceDate' => $r->remittance_date->toDateString(),
            'amount' => (float) $r->amount,
            'birReferenceNo' => $r->bir_reference_no,
            'bankAccountName' => $r->bankAccount->account_name,
            'journalEntryId' => $r->cashTransaction?->journal_entry_id
                ? sprintf('JE-%05d', $r->cashTransaction->journal_entry_id)
                : null,
        ];
    }
}