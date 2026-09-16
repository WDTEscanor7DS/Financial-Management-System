<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'type' => ['required', 'in:Stock In,Stock Out,Adjustment'],
            'quantity' => ['required', 'integer'],
            'moved_at' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}