<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'transaction_date' => ['required', 'date'],
            'type' => ['required', 'in:Deposit,Withdrawal,Transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'contra_account_id' => ['required_if:type,Deposit,Withdrawal', 'nullable', 'integer', 'exists:chart_of_accounts,id'],
            'transfer_to_account_id' => ['required_if:type,Transfer', 'nullable', 'integer', 'exists:bank_accounts,id', 'different:bank_account_id'],
        ];
    }
}