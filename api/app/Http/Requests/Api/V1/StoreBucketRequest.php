<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBucketRequest extends FormRequest
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
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('buckets', 'name'),
            ],
            'storage_path' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'quota_bytes' => ['nullable', 'integer', 'min:0'],
            'max_file_size_bytes' => ['nullable', 'integer', 'min:0'],
            'allowed_extensions' => ['nullable', 'array'],
            'allowed_extensions.*' => ['string', 'max:20'],
            'allowed_mime_types' => ['nullable', 'array'],
            'allowed_mime_types.*' => ['string', 'max:255'],
            'versioning_enabled' => ['nullable', 'boolean'],
            'public_access' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Bucket names may only contain lowercase letters, numbers, and hyphens, and must start/end with a letter or number.',
        ];
    }
}
