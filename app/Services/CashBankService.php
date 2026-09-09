<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBankService
{
    public function __construct(private readonly GeneralLedgerService $ledger) {}

    public function deposit(array $data, int $userId): CashTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var BankAccount $account */
            $account = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);

            $entry = $this->ledger->createEntry([
                'entry_date' => $data['transaction_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'source_module' => 'CashBank',
                'created_by' => $userId,
                'lines' => [
                    ['account_id' => $account->chart_of_account_id, 'debit' => $data['amount'], 'credit' => 0],
                    ['account_id' => $data['contra_account_id'], 'debit' => 0, 'credit' => $data['amount']],
                ],
            ]);

            $transaction = CashTransaction::create([
                'bank_account_id' => $account->id,
                'transaction_date' => $data['transaction_date'],
                'type' => 'Deposit',
                'amount' => $data['amount'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'contra_account_id' => $data['contra_account_id'],
                'journal_entry_id' => $entry->id,
                'created_by' => $userId,
            ]);

            $account->increment('current_balance', $data['amount']);

            AuditService::log('Recorded Deposit', 'Cash and Bank', (string) $transaction->id);

            return $transaction->load('bankAccount', 'contraAccount', 'journalEntry');
        });
    }

    public function withdraw(array $data, int $userId): CashTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var BankAccount $account */
            $account = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);

            if ($data['amount'] > (float) $account->current_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal exceeds the current balance of this account.',
                ]);
            }

            $entry = $this->ledger->createEntry([
                'entry_date' => $data['transaction_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'source_module' => 'CashBank',
                'created_by' => $userId,
                'lines' => [
                    ['account_id' => $data['contra_account_id'], 'debit' => $data['amount'], 'credit' => 0],
                    ['account_id' => $account->chart_of_account_id, 'debit' => 0, 'credit' => $data['amount']],
                ],
            ]);

            $transaction = CashTransaction::create([
                'bank_account_id' => $account->id,
                'transaction_date' => $data['transaction_date'],
                'type' => 'Withdrawal',
                'amount' => $data['amount'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'contra_account_id' => $data['contra_account_id'],
                'journal_entry_id' => $entry->id,
                'created_by' => $userId,
            ]);

            $account->decrement('current_balance', $data['amount']);

            AuditService::log('Recorded Withdrawal', 'Cash and Bank', (string) $transaction->id);

            return $transaction->load('bankAccount', 'contraAccount', 'journalEntry');
        });
    }

    public function transfer(array $data, int $userId): CashTransaction
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var BankAccount $from */
            $from = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);
            /** @var BankAccount $to */
            $to = BankAccount::query()->lockForUpdate()->findOrFail($data['transfer_to_account_id']);

            if ($data['amount'] > (float) $from->current_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Transfer exceeds the current balance of the source account.',
                ]);
            }

            $entry = $this->ledger->createEntry([
                'entry_date' => $data['transaction_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'source_module' => 'CashBank',
                'created_by' => $userId,
                'lines' => [
                    ['account_id' => $to->chart_of_account_id, 'debit' => $data['amount'], 'credit' => 0],
                    ['account_id' => $from->chart_of_account_id, 'debit' => 0, 'credit' => $data['amount']],
                ],
            ]);

            $transaction = CashTransaction::create([
                'bank_account_id' => $from->id,
                'transaction_date' => $data['transaction_date'],
                'type' => 'Transfer',
                'amount' => $data['amount'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'transfer_to_account_id' => $to->id,
                'journal_entry_id' => $entry->id,
                'created_by' => $userId,
            ]);

            $from->decrement('current_balance', $data['amount']);
            $to->increment('current_balance', $data['amount']);

            AuditService::log('Recorded Transfer', 'Cash and Bank', (string) $transaction->id);

            return $transaction->load('bankAccount', 'transferToAccount', 'journalEntry');
        });
    }
}