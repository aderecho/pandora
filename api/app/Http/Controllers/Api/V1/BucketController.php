<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBucketRequest;
use App\Http\Requests\Api\V1\UpdateBucketRequest;
use App\Http\Resources\BucketResource;
use App\Http\Responses\ApiResponse;
use App\Models\Bucket;
use App\Services\Authorization\AccessControlService;
use App\Services\BucketService;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BucketController extends Controller
{
    public function __construct(
        private readonly BucketService $buckets,
        private readonly AccessControlService $accessControl,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Bucket::query();

        $visibleIds = $this->accessControl->visibleBucketIds(
            $request->user(),
            CurrentActor::token(),
            PermissionKey::BucketList->value
        );

        if ($visibleIds !== null) {
            $query->whereIn('id', $visibleIds);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $perPage = min((int) $request->integer('per_page', 20), 100);
        $buckets = $query->orderBy('name')->paginate($perPage);

        return ApiResponse::paginated(BucketResource::collection($buckets), $buckets);
    }

    public function store(StoreBucketRequest $request): JsonResponse
    {
        $bucket = $this->buckets->create($request->validated(), $request->user());
        $this->audit('bucket.create', $bucket, null, AuditStatus::Success);

        return ApiResponse::success(new BucketResource($bucket), status: 201);
    }

    public function show(Bucket $bucket): JsonResponse
    {
        return ApiResponse::success(new BucketResource($bucket->load('creator')), [
            'usage' => $this->buckets->usage($bucket),
        ]);
    }

    public function update(UpdateBucketRequest $request, Bucket $bucket): JsonResponse
    {
        $bucket = $this->buckets->update($bucket, $request->validated());
        $this->audit('bucket.update', $bucket, null, AuditStatus::Success, $request->validated());

        return ApiResponse::success(new BucketResource($bucket));
    }

    public function destroy(Bucket $bucket): JsonResponse
    {
        $this->buckets->delete($bucket);
        $this->audit('bucket.delete', $bucket, null, AuditStatus::Success);

        return ApiResponse::success(['message' => 'Bucket deleted.']);
    }
}
