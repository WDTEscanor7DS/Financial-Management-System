<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxRemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_type_id' => ['required', 'integer', 'exists:tax_types,id'],
            'period_covered' => ['required', 'string', 'max:60'],
            'remittance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bir_reference_no' => ['nullable', 'string', 'max:60'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
        ];
    }
}