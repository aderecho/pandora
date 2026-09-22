<?php

namespace App\Http\Middleware;

use App\Events\AccessDenied;
use App\Http\Responses\ApiResponse;
use App\Models\Bucket;
use App\Services\Authorization\AccessControlService;
use App\Support\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('scope:object:write'). Resolves {bucket}
 * and {key} route parameters (if present) and delegates the actual
 * allow/deny decision to AccessControlService, so the same rule applies
 * whether the caller is an admin dashboard session or a CLI API token.
 */
class ScopedPermission
{
    public function __construct(private readonly AccessControlService $accessControl)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', status: 401);
        }

        $bucket = $request->route('bucket');
        $bucket = $bucket instanceof Bucket ? $bucket : null;

        $key = $request->route('key');
        $key = is_string($key) ? $key : null;

        $token = CurrentActor::token();

        $allowed = $this->accessControl->authorize($user, $token, $permission, $bucket, $key);

        if (! $allowed) {
            event(new AccessDenied(
                actorType: CurrentActor::actorType(),
                actorId: CurrentActor::actorId(),
                permission: $permission,
                bucketId: $bucket?->id,
                objectKey: $key,
                ipAddress: $request->ip() ?? 'unknown',
                userAgent: $request->userAgent(),
            ));

            return ApiResponse::error(
                'FORBIDDEN',
                "You do not have the [{$permission}] permission for this resource.",
                status: 403
            );
        }

        return $next($request);
    }
}
