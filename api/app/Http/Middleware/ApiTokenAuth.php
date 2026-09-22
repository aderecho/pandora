<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates Bearer <raw-token> requests against the api_tokens table.
 * On success, $request->user() resolves to the token's owning User (so
 * downstream code that only cares "who is this" needs no changes) and the
 * ApiToken itself is attached to the request for scope-aware authorization
 * (see CurrentActor / AccessControlService / ScopedPermission).
 */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken();

        if ($raw === null || $raw === '') {
            return ApiResponse::error('UNAUTHENTICATED', 'An API token is required.', status: 401);
        }

        $token = ApiToken::query()
            ->where('token_hash', ApiToken::hashToken($raw))
            ->with('user')
            ->first();

        if ($token === null || ! $token->isActive() || ! $token->user?->isActive()) {
            return ApiResponse::error('UNAUTHENTICATED', 'Invalid or expired API token.', status: 401);
        }

        if (! $token->isIpAllowed($request->ip())) {
            return ApiResponse::error('IP_NOT_ALLOWED', 'This token is not permitted from your IP address.', status: 403);
        }

        $token->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();

        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
