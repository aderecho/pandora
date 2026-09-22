<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditStatus;
use App\Exceptions\Storage\FileRejectedException;
use App\Exceptions\Storage\QuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadObjectRequest;
use App\Http\Resources\StoredObjectResource;
use App\Http\Responses\ApiResponse;
use App\Models\Bucket;
use App\Services\ObjectService;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObjectController extends Controller
{
    public function __construct(private readonly ObjectService $objects)
    {
    }

    public function index(Request $request, Bucket $bucket): JsonResponse
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min((int) $request->integer('per_page', 50), 200);

        $paginator = $this->objects->list(
            $bucket,
            (string) $request->string('prefix', ''),
            $request->only(['name', 'mime_type', 'uploaded_by', 'from', 'to']),
            $page,
            $perPage,
        );

        $items = collect($paginator->items())->map(function (array $entry) {
            return $entry['type'] === 'folder'
                ? ['type' => 'folder', 'key' => $entry['key']]
                : (new StoredObjectResource($entry['object']))->resolve();
        })->values();

        return ApiResponse::paginated($items, $paginator);
    }

    public function store(UploadObjectRequest $request, Bucket $bucket): JsonResponse
    {
        $key = $request->string('key')->toString();

        if ($request->isDirectoryRequest()) {
            $object = $this->objects->makeDirectory($bucket, $key);
            $this->audit('object.mkdir', $bucket, $key, AuditStatus::Success);

            return ApiResponse::success(new StoredObjectResource($object), status: 201);
        }

        try {
            $object = $this->objects->upload(
                $bucket,
                $key,
                $request->file('file'),
                $request->user(),
                CurrentActor::token(),
            );
        } catch (FileRejectedException|QuotaExceededException $e) {
            $this->audit('object.upload', $bucket, $key, AuditStatus::Failure, ['reason' => $e->getMessage()]);
            throw $e;
        }

        $this->audit('object.upload', $bucket, $key, AuditStatus::Success, ['size_bytes' => $object->size_bytes]);

        return ApiResponse::success(new StoredObjectResource($object), status: 201);
    }

    public function show(Bucket $bucket, string $key): StreamedResponse
    {
        $response = $this->objects->download($bucket, $key);
        $this->audit('object.download', $bucket, $key, AuditStatus::Success);

        return $response;
    }

    public function metadata(Bucket $bucket, string $key): JsonResponse
    {
        $object = $this->objects->metadata($bucket, $key);

        return ApiResponse::success(new StoredObjectResource($object));
    }

    public function destroy(Bucket $bucket, string $key): JsonResponse
    {
        $this->objects->delete($bucket, $key);
        $this->audit('object.delete', $bucket, $key, AuditStatus::Success);

        return ApiResponse::success(['message' => 'Object deleted.']);
    }
}
