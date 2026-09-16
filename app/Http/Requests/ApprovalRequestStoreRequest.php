<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workflow_code' => ['required', 'string', 'exists:approval_workflows,code'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}