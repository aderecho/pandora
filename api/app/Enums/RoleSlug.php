<?php

namespace App\Enums;

enum RoleSlug: string
{
    case SuperAdmin = 'super_admin';
    case StorageAdmin = 'storage_admin';
    case Developer = 'developer';
    case ReadOnlyAuditor = 'read_only_auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::StorageAdmin => 'Storage Admin',
            self::Developer => 'Developer/Client',
            self::ReadOnlyAuditor => 'Read-Only Auditor',
        };
    }
}
