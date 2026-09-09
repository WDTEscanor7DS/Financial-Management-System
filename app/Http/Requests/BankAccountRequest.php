<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_name' => ['required', 'string', 'max:190'],
            'account_type' => ['required', 'in:Bank,Cash'],
            'bank_name' => ['nullable', 'string', 'max:190'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}