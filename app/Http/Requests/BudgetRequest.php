<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level permission middleware already gates access
    }

    public function rules(): array
    {
        $budgetId = $this->route('budget')?->id;

        return [
            'department_id' => ['required', 'exists:departments,id'],
            'fiscal_year' => ['required', 'digits:4'],
            'category' => [
                'required', 'string', 'max:150',
                Rule::unique('budgets')->where(fn ($q) => $q
                    ->where('department_id', $this->input('department_id'))
                    ->where('fiscal_year', $this->input('fiscal_year')))
                    ->ignore($budgetId),
            ],
            'allocated' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['Active', 'Closed'])],
        ];
    }

    public function messages(): array
    {
        return [
            'category.unique' => 'A budget for this department, fiscal year, and category already exists.',
        ];
    }
}
