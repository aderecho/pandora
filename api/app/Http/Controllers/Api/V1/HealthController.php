<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = ! in_array(false, $checks, true);

        return ApiResponse::success([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toAtomString(),
        ], status: $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            return Redis::connection()->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        return is_dir(config('fileserver.storage_root')) && is_writable(config('fileserver.storage_root'));
    }
}
