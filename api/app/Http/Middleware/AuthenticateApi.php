<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts either a Bearer API token (delegates to ApiTokenAuth) or an
 * authenticated Sanctum SPA session (the 'web' guard, already populated by
 * the statefulApi() cookie middleware for same-site dashboard requests).
 * One request can't be both, so the presence of a bearer token decides
 * which path to take.
 */
class AuthenticateApi
{
    public function __construct(private readonly ApiTokenAuth $tokenAuth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            return $this->tokenAuth->handle($request, $next);
        }

        if (! Auth::guard('web')->check()) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', status: 401);
        }

        $request->setUserResolver(fn () => Auth::guard('web')->user());

        return $next($request);
    }
}
