<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        return ApiResponse::success(RoleResource::collection($roles));
    }
}
