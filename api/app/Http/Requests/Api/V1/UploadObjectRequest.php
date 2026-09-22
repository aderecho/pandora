<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Bucket;
use App\Rules\AllowedExtension;
use App\Rules\AllowedMimeType;
use App\Rules\SafeObjectKey;
use Illuminate\Foundation\Http\FormRequest;

class UploadObjectRequest extends FormRequest
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
        /** @var Bucket $bucket */
        $bucket = $this->route('bucket');
        $maxKilobytes = (int) ceil((($bucket->max_file_size_bytes ?? config('fileserver.default_max_file_size_bytes')) ?: PHP_INT_MAX) / 1024);

        // A key ending in "/" with no file creates an empty folder marker,
        // matching the CLI's `mkdir` command — otherwise a file is required.
        if ($this->isDirectoryRequest()) {
            return [
                'key' => ['required', 'string', new SafeObjectKey],
                'file' => ['prohibited'],
            ];
        }

        return [
            'key' => ['required', 'string', new SafeObjectKey],
            'file' => [
                'required', 'file', "max:{$maxKilobytes}",
                new AllowedExtension($bucket->allowed_extensions, (string) $this->input('key')),
                new AllowedMimeType($bucket->allowed_mime_types),
            ],
        ];
    }

    public function isDirectoryRequest(): bool
    {
        return str_ends_with((string) $this->input('key', ''), '/');
    }
}
