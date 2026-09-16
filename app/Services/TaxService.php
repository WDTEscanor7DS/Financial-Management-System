<?php

namespace App\Services;

use App\Models\TaxRemittance;
use App\Models\TaxType;
use Illuminate\Support\Facades\DB;

class TaxService
{
    public function __construct(private readonly CashBankService $cashBank) {}

    public function remit(array $data, int $userId): TaxRemittance
    {
        return DB::transaction(function () use ($data, $userId) {
            $taxType = TaxType::findOrFail($data['tax_type_id']);

            $cashTransaction = $this->cashBank->withdraw([
                'bank_account_id' => $data['bank_account_id'],
                'transaction_date' => $data['remittance_date'],
                'amount' => $data['amount'],
                'reference_no' => $data['bir_reference_no'] ?? null,
                'description' => 'Tax remittance — ' . $taxType->name . ' (' . $data['period_covered'] . ')',
                'contra_account_id' => $taxType->chart_of_account_id,
            ], $userId);

            $remittance = TaxRemittance::create([
                'tax_type_id' => $taxType->id,
                'period_covered' => $data['period_covered'],
                'remittance_date' => $data['remittance_date'],
                'amount' => $data['amount'],
                'bir_reference_no' => $data['bir_reference_no'] ?? null,
                'bank_account_id' => $data['bank_account_id'],
                'cash_transaction_id' => $cashTransaction->id,
                'created_by' => $userId,
            ]);

            AuditService::log('Recorded Tax Remittance', 'Tax', (string) $remittance->id);

            return $remittance->load('taxType', 'bankAccount', 'cashTransaction.journalEntry');
        });
    }
}