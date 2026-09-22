<?php

namespace Database\Seeders;

use App\Enums\PermissionKey;
use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission keys granted to each seeded role.
     *
     * @return array<string, array<int, PermissionKey>>
     */
    private function roleMatrix(): array
    {
        return [
            RoleSlug::SuperAdmin->value => PermissionKey::cases(),
            RoleSlug::StorageAdmin->value => [
                PermissionKey::BucketList,
                PermissionKey::BucketRead,
                PermissionKey::BucketWrite,
                PermissionKey::BucketDelete,
                PermissionKey::ObjectList,
                PermissionKey::ObjectRead,
                PermissionKey::ObjectWrite,
                PermissionKey::ObjectDelete,
                PermissionKey::TokensManage,
                PermissionKey::AuditRead,
            ],
            RoleSlug::Developer->value => [
                PermissionKey::BucketList,
                PermissionKey::BucketRead,
                PermissionKey::ObjectList,
                PermissionKey::ObjectRead,
                PermissionKey::ObjectWrite,
            ],
            RoleSlug::ReadOnlyAuditor->value => [
                PermissionKey::BucketList,
                PermissionKey::BucketRead,
                PermissionKey::ObjectList,
                PermissionKey::ObjectRead,
                PermissionKey::AuditRead,
            ],
        ];
    }

    public function run(): void
    {
        $permissions = collect(PermissionKey::cases())->mapWithKeys(
            fn (PermissionKey $case) => [
                $case->value => Permission::query()->updateOrCreate(
                    ['key' => $case->value],
                    ['description' => $case->description()]
                ),
            ]
        );

        foreach (RoleSlug::cases() as $slug) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug->value],
                ['name' => $slug->label()]
            );

            $permissionIds = collect($this->roleMatrix()[$slug->value])
                ->map(fn (PermissionKey $key) => $permissions[$key->value]->id);

            $role->permissions()->sync($permissionIds);
        }
    }
}
