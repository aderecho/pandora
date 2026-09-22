<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Standard response envelope used by every /api/v1 endpoint:
 * {"data": ..., "meta": {...}, "error": null} on success, or
 * {"data": null, "error": {"code","message","details"}} on failure.
 */
class ApiResponse
{
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $body = ['data' => $data, 'error' => null];

        if (! empty($meta)) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function paginated(mixed $data, LengthAwarePaginator $paginator, array $extraMeta = []): JsonResponse
    {
        return self::success($data, array_merge([
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $extraMeta));
    }

    public static function error(string $code, string $message, array $details = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'data' => null,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ], fn ($v) => $v !== null),
        ], $status);
    }
}
