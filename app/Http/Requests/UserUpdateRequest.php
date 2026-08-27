<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'role_id' => ['sometimes', 'required', 'exists:roles,id'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive', 'Suspended'])],
        ];
    }
}
