<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Used by both the AP "Record Payment" and AR "Record Collection" actions
 * -- the shape is identical (a single positive amount). The actual "does
 * this exceed the outstanding balance" check happens inside
 * PayableService/ReceivableService against a row-locked balance, since
 * that number can change between page load and submit and must be
 * re-checked server-side regardless of what this validator allows through.
 */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
