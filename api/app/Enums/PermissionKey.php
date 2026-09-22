<?php

namespace App\Enums;

enum PermissionKey: string
{
    case BucketList = 'bucket:list';
    case BucketRead = 'bucket:read';
    case BucketWrite = 'bucket:write';
    case BucketDelete = 'bucket:delete';

    case ObjectList = 'object:list';
    case ObjectRead = 'object:read';
    case ObjectWrite = 'object:write';
    case ObjectDelete = 'object:delete';

    case UsersManage = 'users:manage';
    case TokensManage = 'tokens:manage';
    case AuditRead = 'audit:read';
    case SettingsManage = 'settings:manage';

    public function description(): string
    {
        return match ($this) {
            self::BucketList => 'List buckets',
            self::BucketRead => 'Read bucket metadata and configuration',
            self::BucketWrite => 'Create and update buckets',
            self::BucketDelete => 'Archive and delete buckets',
            self::ObjectList => 'List objects within a bucket',
            self::ObjectRead => 'Download/read objects',
            self::ObjectWrite => 'Upload/overwrite objects',
            self::ObjectDelete => 'Delete objects',
            self::UsersManage => 'Manage platform users',
            self::TokensManage => 'Manage API tokens for any user',
            self::AuditRead => 'View and export audit logs',
            self::SettingsManage => 'Manage global storage settings',
        };
    }

    /**
     * Scopes that may be granted to an API token (a subset of all permissions;
     * admin-only permissions are never assignable to a token).
     *
     * @return array<int, self>
     */
    public static function tokenAssignable(): array
    {
        return [
            self::BucketList,
            self::BucketRead,
            self::BucketWrite,
            self::BucketDelete,
            self::ObjectList,
            self::ObjectRead,
            self::ObjectWrite,
            self::ObjectDelete,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function tokenAssignableValues(): array
    {
        return array_map(fn (self $p) => $p->value, self::tokenAssignable());
    }
}
