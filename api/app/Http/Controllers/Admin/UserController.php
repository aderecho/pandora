<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('role');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        $perPage = min((int) $request->integer('per_page', 20), 100);
        $users = $query->orderBy('name')->paginate($perPage);

        return ApiResponse::paginated(UserResource::collection($users), $users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        ['user' => $user, 'generated_password' => $password] = $this->users->create($request->validated());
        $this->audit('user.create', null, null, AuditStatus::Success, ['user_id' => $user->id]);

        return ApiResponse::success([
            'user' => new UserResource($user->load('role')),
            'generated_password' => $password,
        ], status: 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->users->update($user, $request->validated());
        $this->audit('user.update', null, null, AuditStatus::Success, ['user_id' => $user->id]);

        return ApiResponse::success(new UserResource($user));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->users->delete($user);
        $this->audit('user.delete', null, null, AuditStatus::Success, ['user_id' => $user->id]);

        return ApiResponse::success(['message' => 'User deleted.']);
    }

    public function forcePasswordReset(User $user): JsonResponse
    {
        $password = $this->users->forcePasswordReset($user);
        $this->audit('user.force_password_reset', null, null, AuditStatus::Success, ['user_id' => $user->id]);

        return ApiResponse::success(['generated_password' => $password]);
    }
}
