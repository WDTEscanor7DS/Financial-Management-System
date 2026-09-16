<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:30', 'unique:inventory_items,item_code'],
            'item_name' => ['required', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit_of_measure' => ['required', 'string', 'max:30'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
        ];
    }
}