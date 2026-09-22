<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActorType;
use App\Enums\AuditStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            $this->auditLog->record(ActorType::User, null, 'auth.login', null, null, $request->ip() ?? 'unknown', $request->userAgent(), AuditStatus::Failure, ['email' => $email]);

            return ApiResponse::error('INVALID_CREDENTIALS', 'These credentials do not match our records.', status: 422);
        }

        if (! $user->isActive()) {
            $this->auditLog->record(ActorType::User, $user->id, 'auth.login', null, null, $request->ip() ?? 'unknown', $request->userAgent(), AuditStatus::Failure, ['reason' => 'disabled']);

            return ApiResponse::error('ACCOUNT_DISABLED', 'This account has been disabled.', status: 403);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->auditLog->record(ActorType::User, $user->id, 'auth.login', null, null, $request->ip() ?? 'unknown', $request->userAgent(), AuditStatus::Success);

        return ApiResponse::success([
            'user' => new UserResource($user->load('role')),
        ]);
    }

    /**
     * Lets the SPA restore session state on page load/refresh without
     * re-submitting credentials.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => new UserResource($request->user()->load('role')),
        ]);
    }

    /**
     * Self-service password change — completes the loop for both
     * admin-generated first-login passwords and force-password-reset
     * (must_reset_password), neither of which the user could otherwise
     * act on themselves.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return ApiResponse::error('INVALID_CREDENTIALS', 'Current password is incorrect.', status: 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->string('new_password')->toString()),
            'must_reset_password' => false,
        ])->save();

        $this->auditLog->record(ActorType::User, $user->id, 'auth.password_changed', null, null, $request->ip() ?? 'unknown', $request->userAgent(), AuditStatus::Success);

        return ApiResponse::success(['message' => 'Password updated.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->auditLog->record(ActorType::User, $userId, 'auth.logout', null, null, $request->ip() ?? 'unknown', $request->userAgent(), AuditStatus::Success);

        return ApiResponse::success(['message' => 'Logged out.']);
    }
}
