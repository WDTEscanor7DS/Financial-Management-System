<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer' => ['required', 'string', 'max:190'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
