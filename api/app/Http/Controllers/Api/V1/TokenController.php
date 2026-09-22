<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTokenRequest;
use App\Http\Requests\Api\V1\UpdateTokenRequest;
use App\Http\Resources\TokenResource;
use App\Http\Responses\ApiResponse;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Authorization\AccessControlService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly AccessControlService $accessControl,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiToken::class);

        $user = $request->user();
        $query = ApiToken::query()->with(['user', 'bucketScopes.bucket']);

        if (! $this->accessControl->isAdminTier($user) && ! $user->hasPermission(PermissionKey::TokensManage->value)) {
            $query->where('user_id', $user->id);
        }

        $perPage = min((int) $request->integer('per_page', 20), 100);
        $tokens = $query->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::paginated(TokenResource::collection($tokens), $tokens);
    }

    public function store(StoreTokenRequest $request): JsonResponse
    {
        $this->authorize('create', ApiToken::class);

        $data = $request->validated();
        $owner = $request->user();

        if (isset($data['user_id']) && (int) $data['user_id'] !== $owner->id) {
            if (! $owner->hasPermission(PermissionKey::TokensManage->value)) {
                return ApiResponse::error('FORBIDDEN', 'You cannot create tokens for other users.', status: 403);
            }
            $owner = User::query()->findOrFail($data['user_id']);
        }

        ['token' => $token, 'raw' => $raw] = $this->tokens->create($owner, $data, $request->user());
        $this->audit('token.create', null, null, AuditStatus::Success, ['token_id' => $token->id, 'owner_id' => $owner->id]);

        return ApiResponse::success([
            'token' => new TokenResource($token),
            'raw_token' => $raw,
        ], status: 201);
    }

    public function update(UpdateTokenRequest $request, ApiToken $token): JsonResponse
    {
        $this->authorize('update', $token);

        $token = $this->tokens->update($token, $request->validated());
        $this->audit('token.update', null, null, AuditStatus::Success, ['token_id' => $token->id]);

        return ApiResponse::success(new TokenResource($token));
    }

    public function rotate(ApiToken $token): JsonResponse
    {
        $this->authorize('rotate', $token);

        ['token' => $token, 'raw' => $raw] = $this->tokens->rotate($token);
        $this->audit('token.rotate', null, null, AuditStatus::Success, ['token_id' => $token->id]);

        return ApiResponse::success([
            'token' => new TokenResource($token),
            'raw_token' => $raw,
        ]);
    }

    public function revoke(ApiToken $token): JsonResponse
    {
        $this->authorize('revoke', $token);

        $this->tokens->revoke($token);
        $this->audit('token.revoke', null, null, AuditStatus::Success, ['token_id' => $token->id]);

        return ApiResponse::success(['message' => 'Token revoked.']);
    }
}
