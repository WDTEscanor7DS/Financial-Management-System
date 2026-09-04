<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcurementRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'exists:departments,id'],
            'request_type' => ['required', Rule::in([
                'Purchase Request', 'Reimbursement', 'Financial Request',
                'Office Supplies', 'Equipment', 'Services',
            ])],
            'description' => ['required', 'string'],
            'quantity' => ['nullable', 'string', 'max:120'],
            'estimated_cost' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['sometimes', Rule::in(['Low', 'Medium', 'High', 'Urgent'])],
            // requester_id, status, date_submitted are set server-side from
            // the authenticated user -- never accepted from the client, so
            // an Employee cannot submit a request "as" someone else or with
            // a pre-approved status (Section 14/32).
        ];
    }
}
