<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'disabled'])],
        ];
    }
}
