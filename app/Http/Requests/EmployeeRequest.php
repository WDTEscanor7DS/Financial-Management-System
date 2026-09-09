<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_no' => ['required', 'string', 'max:30', 'unique:employees,employee_no,' . $this->route('employee')?->id],
            'full_name' => ['required', 'string', 'max:190'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position' => ['required', 'string', 'max:120'],
            'employment_type' => ['required', 'in:Full-time,Part-time'],
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:Active,Inactive'],
            'hire_date' => ['required', 'date'],
        ];
    }
}