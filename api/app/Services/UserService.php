<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, generated_password: ?string}
     */
    public function create(array $data): array
    {
        $password = $data['password'] ?? null;
        $generated = $password === null;
        $password ??= Str::password(16);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role_id' => $data['role_id'],
            'status' => UserStatus::Active,
            'must_reset_password' => true,
        ]);

        return ['user' => $user, 'generated_password' => $generated ? $password : null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update(array_intersect_key($data, array_flip(['name', 'email', 'role_id', 'status'])));

        return $user->fresh('role');
    }

    public function disable(User $user): User
    {
        $user->update(['status' => UserStatus::Disabled]);

        return $user;
    }

    public function forcePasswordReset(User $user): string
    {
        $password = Str::password(16);

        $user->update([
            'password' => Hash::make($password),
            'must_reset_password' => true,
        ]);

        return $password;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
