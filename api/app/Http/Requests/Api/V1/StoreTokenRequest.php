<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTokenRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(PermissionKey::tokenAssignableValues())],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'bucket_scopes' => ['nullable', 'array'],
            'bucket_scopes.*.bucket' => ['required', 'string', 'exists:buckets,name'],
            'bucket_scopes.*.path_prefix' => ['nullable', 'string'],
            'bucket_scopes.*.permissions' => ['required', 'array', 'min:1'],
            'bucket_scopes.*.permissions.*' => [Rule::in(PermissionKey::tokenAssignableValues())],
        ];
    }
}
