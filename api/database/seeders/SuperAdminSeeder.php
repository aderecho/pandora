<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('FILESERVER_SUPER_ADMIN_EMAIL', 'admin@fileserver.local');

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->warn("Super Admin already exists ({$email}); skipping.");

            return;
        }

        $password = env('FILESERVER_SUPER_ADMIN_PASSWORD');
        $generated = $password === null;
        $password ??= Str::password(20);

        $role = Role::query()->where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        User::query()->create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $role->id,
            'status' => UserStatus::Active,
            'must_reset_password' => $generated,
            'email_verified_at' => now(),
        ]);

        $this->command?->info('Super Admin created.');
        $this->command?->line("  Email:    {$email}");

        if ($generated) {
            $this->command?->warn("  Password: {$password}");
            $this->command?->warn('  This password is shown only once and must be changed on first login.');
        } else {
            $this->command?->line('  Password: (set via FILESERVER_SUPER_ADMIN_PASSWORD)');
        }
    }
}
