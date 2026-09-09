<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashTransactionRequest;
use App\Models\BankAccount;
use App\Models\CashTransaction;
use App\Services\CashBankService;
use Illuminate\Http\Request;
use App\Http\Requests\BankAccountRequest;

class CashBankController extends Controller
{
    public function __construct(private readonly CashBankService $service) {}
    
    public function storeAccount(BankAccountRequest $request)
    {
        $data = $request->validated();
        $account = \App\Models\BankAccount::create([
            ...$data,
            'current_balance' => $data['opening_balance'],
            'status' => 'Active',
        ]);

        return response()->json(['data' => [
            'id' => $account->id,
            'accountName' => $account->account_name,
            'accountType' => $account->account_type,
            'bankName' => $account->bank_name,
            'accountNumber' => $account->account_number,
            'currentBalance' => (float) $account->current_balance,
        ]], 201);
    }

    public function accounts()
    {
        $accounts = BankAccount::where('status', 'Active')
            ->orderBy('account_name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'accountName' => $a->account_name,
                'accountType' => $a->account_type,
                'bankName' => $a->bank_name,
                'accountNumber' => $a->account_number,
                'currentBalance' => (float) $a->current_balance,
            ]);

        return response()->json(['data' => $accounts]);
    }

    public function index(Request $request)
    {
        $transactions = CashTransaction::with(['bankAccount', 'transferToAccount', 'contraAccount', 'creator'])
            ->when($request->query('bank_account_id'), fn ($q, $v) => $q->where('bank_account_id', $v))
            ->orderByDesc('transaction_date')
            ->get()
            ->map($this->transform(...));

        return response()->json(['data' => $transactions]);
    }

    public function store(CashTransactionRequest $request)
    {
        $data = $request->validated();

        $transaction = match ($data['type']) {
            'Deposit' => $this->service->deposit($data, $request->user()->id),
            'Withdrawal' => $this->service->withdraw($data, $request->user()->id),
            'Transfer' => $this->service->transfer($data, $request->user()->id),
        };

        return response()->json(['data' => $this->transform($transaction)], 201);
    }

    private function transform(CashTransaction $t): array
    {
        return [
            'id' => sprintf('CT-%05d', $t->id),
            'transactionDate' => $t->transaction_date->toDateString(),
            'type' => $t->type,
            'amount' => (float) $t->amount,
            'referenceNo' => $t->reference_no,
            'description' => $t->description,
            'bankAccountName' => $t->bankAccount->account_name,
            'transferToAccountName' => $t->transferToAccount?->account_name,
            'contraAccountName' => $t->contraAccount?->account_name,
            'journalEntryId' => $t->journal_entry_id ? sprintf('JE-%05d', $t->journal_entry_id) : null,
            'createdBy' => $t->creator?->name,
        ];
    }
}