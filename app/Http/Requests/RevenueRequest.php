<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'revenue_type' => ['required', Rule::in(['Tuition', 'Miscellaneous Fees', 'Service Income', 'Other Institutional Revenue'])],
            'description' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'payer' => ['required', 'string', 'max:190'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', Rule::in(['Cash', 'Bank Transfer', 'Check', 'Online Payment'])],
            'status' => ['sometimes', Rule::in(['Received', 'Pending'])],
        ];
    }
}
