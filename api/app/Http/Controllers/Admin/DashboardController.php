<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditStatus;
use App\Enums\TokenStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\Bucket;
use App\Models\StoredObject;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function metrics(): JsonResponse
    {
        $since = now()->subDay();

        return ApiResponse::success([
            'buckets' => [
                'total' => Bucket::query()->count(),
            ],
            'storage' => [
                'total_bytes' => (int) StoredObject::query()->sum('size_bytes'),
                'file_count' => StoredObject::query()->where('key', 'not like', '%/')->count(),
            ],
            'users' => [
                'active' => User::query()->where('status', UserStatus::Active)->count(),
                'disabled' => User::query()->where('status', UserStatus::Disabled)->count(),
            ],
            'tokens' => [
                'active' => ApiToken::query()->where('status', TokenStatus::Active)->count(),
                'revoked' => ApiToken::query()->where('status', TokenStatus::Revoked)->count(),
            ],
            'activity' => [
                'uploads_last_24h' => AuditLog::query()->where('action', 'object.upload')->where('created_at', '>=', $since)->count(),
                'downloads_last_24h' => AuditLog::query()->where('action', 'object.download')->where('created_at', '>=', $since)->count(),
                'failed_requests_last_24h' => AuditLog::query()->where('status', AuditStatus::Failure)->where('created_at', '>=', $since)->count(),
            ],
            'recent_uploads' => AuditLog::query()
                ->where('action', 'object.upload')
                ->where('status', AuditStatus::Success)
                ->latest('created_at')
                ->limit(10)
                ->get(['id', 'actor_type', 'actor_id', 'bucket_id', 'object_key', 'created_at']),
        ]);
    }
}
