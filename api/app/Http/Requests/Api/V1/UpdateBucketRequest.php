<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBucketRequest extends FormRequest
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
            'name' => [
                'sometimes', 'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('buckets', 'name')->ignore($this->route('bucket')),
            ],
            'quota_bytes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_file_size_bytes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'allowed_extensions' => ['sometimes', 'nullable', 'array'],
            'allowed_extensions.*' => ['string', 'max:20'],
            'allowed_mime_types' => ['sometimes', 'nullable', 'array'],
            'allowed_mime_types.*' => ['string', 'max:255'],
            'versioning_enabled' => ['sometimes', 'boolean'],
            'public_access' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
        ];
    }
}
