<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_name' => ['required', 'string', 'max:190'],
            'category' => ['required', Rule::in([
                'IT Equipment', 'Office Equipment', 'Transportation', 'Facilities', 'Furniture & Fixtures',
            ])],
            'serial_no' => ['nullable', 'string', 'max:100', Rule::unique('assets')->ignore($this->route('asset')?->id)],
            'purchase_date' => ['required', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'useful_life' => ['required', 'integer', 'min:1'],
            'salvage_value' => ['required', 'numeric', 'min:0', 'lte:purchase_cost'],
            'department_id' => ['required', 'exists:departments,id'],
            'location' => ['nullable', 'string', 'max:190'],
            'status' => ['sometimes', Rule::in(['In Use', 'Under Maintenance', 'Disposed'])],
        ];
    }

    public function messages(): array
    {
        return [
            'salvage_value.lte' => 'Salvage value cannot exceed the purchase cost.',
        ];
    }
}
