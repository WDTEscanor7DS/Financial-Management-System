<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::in(['Operating', 'Capital', 'Restricted'])],
            'department_id' => ['required', 'exists:departments,id'],
            'allocation' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
