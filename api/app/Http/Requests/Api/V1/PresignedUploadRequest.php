<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\SafeObjectKey;
use Illuminate\Foundation\Http\FormRequest;

class PresignedUploadRequest extends FormRequest
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
            'key' => ['required', 'string', new SafeObjectKey],
            'max_size_bytes' => ['nullable', 'integer', 'min:1'],
            'ttl_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }
}
