<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Storage\FileRejectedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PresignedUploadRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Bucket;
use App\Services\ObjectService;
use App\Services\PresignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresignedUrlController extends Controller
{
    public function __construct(
        private readonly PresignedUrlService $presigned,
        private readonly ObjectService $objects,
    ) {}

    public function presignDownload(Bucket $bucket, string $key): JsonResponse
    {
        // Confirms the object actually exists before handing out a URL for it.
        $this->objects->metadata($bucket, $key);

        return ApiResponse::success($this->presigned->forDownload($bucket, $key));
    }

    public function presignUpload(PresignedUploadRequest $request, Bucket $bucket): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($this->presigned->forUpload(
            $bucket,
            $data['key'],
            $data['max_size_bytes'] ?? null,
            $data['ttl_seconds'] ?? null,
        ));
    }

    /**
     * Public, unauthenticated: identity comes entirely from the signature.
     */
    public function download(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$bucket, $error] = $this->verifyAndResolveBucket($request, 'GET');

        if ($error !== null) {
            return $error;
        }

        $key = (string) $request->query('key');

        return $this->objects->download($bucket, $key);
    }

    /**
     * Public, unauthenticated: streams the raw PUT body straight to disk.
     */
    public function upload(Request $request): JsonResponse
    {
        [$bucket, $error] = $this->verifyAndResolveBucket($request, 'PUT');

        if ($error !== null) {
            return $error;
        }

        $key = (string) $request->query('key');
        $maxSize = $request->query('max_size') !== null ? (int) $request->query('max_size') : null;

        $stream = fopen('php://input', 'rb');

        if ($stream === false) {
            return ApiResponse::error('STREAM_ERROR', 'Unable to read request body.', status: 500);
        }

        try {
            $object = $this->objects->uploadStream($bucket, $key, $stream, $maxSize);
        } catch (FileRejectedException $e) {
            return ApiResponse::error('FILE_REJECTED', $e->getMessage(), status: 422);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return ApiResponse::success(['key' => $object->key, 'size_bytes' => $object->size_bytes], status: 201);
    }

    /**
     * @return array{0: ?Bucket, 1: ?JsonResponse}
     */
    private function verifyAndResolveBucket(Request $request, string $verb): array
    {
        $bucketName = (string) $request->query('bucket');
        $key = (string) $request->query('key');
        $expires = (int) $request->query('expires');
        $maxSize = $request->query('max_size') !== null ? (int) $request->query('max_size') : null;
        $signature = (string) $request->query('signature');

        if (! $this->presigned->verify($verb, $bucketName, $key, $expires, $maxSize, $signature)) {
            return [null, ApiResponse::error('INVALID_SIGNATURE', 'This presigned URL is invalid or has expired.', status: 403)];
        }

        $bucket = Bucket::query()->where('name', $bucketName)->first();

        if ($bucket === null || ! $bucket->isActive()) {
            return [null, ApiResponse::error('BUCKET_NOT_FOUND', 'Bucket not found or not active.', status: 404)];
        }

        return [$bucket, null];
    }
}
