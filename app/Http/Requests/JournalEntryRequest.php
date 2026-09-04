<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}