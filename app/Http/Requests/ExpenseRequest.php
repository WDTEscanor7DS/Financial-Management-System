<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'department_id' => ['required', 'exists:departments,id'],
            'expense_category' => ['required', Rule::in([
                'Salaries', 'Utilities', 'Supplies', 'Maintenance',
                'Procurement', 'Transportation', 'Equipment', 'Other Operating Expenses',
            ])],
            'description' => ['required', 'string', 'max:255'],
            'vendor' => ['required', 'string', 'max:190'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', Rule::in(['Cash', 'Bank Transfer', 'Check'])],
            'status' => ['sometimes', Rule::in(['Paid', 'Pending'])],
            'budget_id' => ['nullable', 'exists:budgets,id'],
        ];
    }
}
