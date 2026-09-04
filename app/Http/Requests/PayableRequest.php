<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor' => ['required', 'string', 'max:190'],
            'invoice_no' => ['required', 'string', 'max:80'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'description' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
