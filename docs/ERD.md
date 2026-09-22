# Database ERD

```mermaid
erDiagram
    ROLES ||--o{ USERS : "assigned to"
    ROLES ||--o{ PERMISSION_ROLE : has
    PERMISSIONS ||--o{ PERMISSION_ROLE : has
    USERS ||--o{ BUCKETS : creates
    USERS ||--o{ BUCKET_USER_PERMISSIONS : "granted on"
    BUCKETS ||--o{ BUCKET_USER_PERMISSIONS : "grants for"
    USERS ||--o{ API_TOKENS : owns
    API_TOKENS ||--o{ TOKEN_BUCKET_SCOPES : scoped_to
    BUCKETS ||--o{ TOKEN_BUCKET_SCOPES : "scoped by"
    BUCKETS ||--o{ STORED_OBJECTS : contains
    USERS ||--o{ STORED_OBJECTS : uploads
    API_TOKENS ||--o{ STORED_OBJECTS : "uploads via"
    STORED_OBJECTS ||--o{ OBJECT_VERSIONS : has
    USERS ||--o{ OBJECT_VERSIONS : uploads
    STORED_OBJECTS }o--|| OBJECT_VERSIONS : "current_version_id ->"
    BUCKETS ||--o{ AUDIT_LOGS : "concerns"

    ROLES {
        bigint id PK
        string name
        string slug UK
        text description
    }

    PERMISSIONS {
        bigint id PK
        string key UK "bucket:list, object:write, ..."
        text description
    }

    PERMISSION_ROLE {
        bigint id PK
        bigint role_id FK
        bigint permission_id FK
    }

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        bigint role_id FK
        string status "active|disabled"
        boolean must_reset_password
        timestamp last_login_at
        string last_login_ip
        timestamp deleted_at
    }

    BUCKETS {
        bigint id PK
        string name UK
        string slug UK
        string storage_path
        string driver "local|s3|minio"
        bigint quota_bytes
        bigint max_file_size_bytes
        json allowed_extensions
        json allowed_mime_types
        boolean versioning_enabled
        boolean public_access
        string status "active|archived"
        bigint created_by FK
        timestamp deleted_at
    }

    BUCKET_USER_PERMISSIONS {
        bigint id PK
        bigint bucket_id FK
        bigint user_id FK
        boolean can_list
        boolean can_read
        boolean can_write
        boolean can_delete
        string path_prefix
    }

    API_TOKENS {
        bigint id PK
        bigint user_id FK
        string name
        string token_hash UK "sha256, never the raw token"
        string token_prefix
        json abilities
        string status "active|revoked"
        timestamp expires_at
        json allowed_ips
        timestamp last_used_at
        string last_used_ip
        bigint created_by FK
    }

    TOKEN_BUCKET_SCOPES {
        bigint id PK
        bigint api_token_id FK
        bigint bucket_id FK
        string path_prefix
        json permissions
    }

    STORED_OBJECTS {
        bigint id PK
        bigint bucket_id FK
        string key
        bigint size_bytes
        string mime_type
        string checksum_sha256
        string etag
        bigint current_version_id FK
        bigint uploaded_by FK
        bigint uploaded_via_token_id FK
        json metadata
        timestamp deleted_at
    }

    OBJECT_VERSIONS {
        bigint id PK
        bigint stored_object_id FK
        int version_number
        bigint size_bytes
        string checksum_sha256
        string storage_relative_path
        bigint uploaded_by FK
        boolean is_current
        timestamp created_at
    }

    AUDIT_LOGS {
        bigint id PK
        string actor_type "user|token|system"
        bigint actor_id
        string action
        bigint bucket_id FK
        string object_key
        string ip_address
        string user_agent
        string status "success|failure"
        json metadata
        timestamp created_at
    }

    STORAGE_SETTINGS {
        bigint id PK
        string key UK
        json value
        string description
    }
```

## Notes

- **`stored_objects(bucket_id, key)`** has a *partial* unique index (`WHERE deleted_at IS NULL`) — only one live object may occupy a key at a time, but a soft-deleted key can be re-uploaded immediately (mirrors S3 semantics). See migration `2026_09_22_000009_create_stored_objects_table`.
- **`api_tokens.token_hash`** is a SHA-256 hash; the raw token is never persisted and is only ever shown once, at creation or rotation time.
- **`audit_logs`** has no foreign key to `users`/`api_tokens` on `actor_id` — `actor_type` + `actor_id` is a lightweight polymorphic pair, since the actor may be a user, a token, or `system` (background jobs).
- Indexes explicitly added per the spec: `buckets.name`, `stored_objects(bucket_id, key)`, `api_tokens.token_hash`, `users.email`, and `audit_logs(created_at, actor_type+actor_id, bucket_id, action, status)`.
