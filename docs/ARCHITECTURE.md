# Architecture

## System overview

```mermaid
flowchart TB
    subgraph Clients
        CLI["fileserver-cli\n(Composer/Symfony Console)"]
        Browser["Admin browser\n(Vue 3 SPA)"]
        ThirdParty["Third-party API client"]
    end

    subgraph Edge
        Nginx["Nginx\nTLS termination, static SPA, reverse proxy"]
    end

    subgraph App["Laravel application (PHP-FPM)"]
        API["/api/v1 REST API\ncontrollers -> services -> policies"]
        Auth["Auth layer\nSanctum session (SPA) /\nApiTokenAuth (Bearer)"]
        AC["AccessControlService\n(scoped authorization)"]
        Storage["StorageDriverInterface\n(LocalStorageDriver today;\nS3/MinIO drop-in later)"]
        Jobs["Queued jobs\nWriteAuditLog, ScanObjectForViruses,\nPurgeExpiredTokens, PurgeSoftDeletedObjects,\nRecalculateBucketUsage"]
    end

    subgraph Data
        PG[("PostgreSQL\nusers, buckets, tokens,\nstored_objects, audit_logs, ...")]
        Redis[("Redis\ncache, session, queue")]
        Disk[("Local filesystem\nstorage/app/buckets/{bucket}/{key}")]
        ClamAV["ClamAV daemon\n(optional, via VirusScannerInterface)"]
    end

    Worker["Supervisor-managed\nphp artisan queue:work"]
    Scheduler["Cron -> php artisan schedule:run\n(every minute)"]

    CLI -- "Bearer token" --> Nginx
    ThirdParty -- "Bearer token" --> Nginx
    Browser -- "Session cookie + CSRF" --> Nginx
    Nginx --> API
    API --> Auth
    Auth --> AC
    AC --> API
    API --> Storage
    Storage --> Disk
    API --> PG
    API --> Redis
    API -. dispatch .-> Jobs
    Jobs --> PG
    Jobs --> Disk
    Jobs -. optional scan .-> ClamAV
    Worker --> Jobs
    Redis --> Worker
    Scheduler --> Redis
```

## Request flow: authenticated object upload

```mermaid
sequenceDiagram
    participant CLI as fileserver-cli
    participant Nginx
    participant MW as ApiTokenAuth + ScopedPermission
    participant Ctrl as ObjectController
    participant Svc as ObjectService
    participant Drv as LocalStorageDriver
    participant DB as PostgreSQL
    participant Q as Redis queue

    CLI->>Nginx: POST /api/v1/buckets/{bucket}/objects (multipart)
    Nginx->>MW: forward request
    MW->>MW: hash bearer token, look up api_tokens
    MW->>MW: check ability + token_bucket_scopes + path_prefix
    MW->>Ctrl: authorized request
    Ctrl->>Svc: upload(bucket, key, file, user, token)
    Svc->>Svc: quota / extension / size checks
    Svc->>Drv: put(bucket.storage_path, key, file)
    Drv->>Drv: validate path, write file, checksum
    Svc->>DB: upsert stored_objects + object_versions
    Ctrl->>Q: dispatch ScanObjectForViruses (async)
    Ctrl->>Q: dispatch WriteAuditLog (async)
    Ctrl-->>CLI: 201 {data: StoredObject}
```

## Layering

- **Controllers** (`app/Http/Controllers`) — HTTP concerns only: validate via FormRequests, call a service, shape the response via `ApiResponse`/API Resources.
- **Services** (`app/Services`) — business logic: `BucketService`, `ObjectService`, `TokenService`, `UserService`, `QuotaService`, `PresignedUrlService`, `AuditLogService`.
- **Storage abstraction** (`app/Services/Storage`) — `StorageDriverInterface` with `LocalStorageDriver` today; a future `S3StorageDriver`/`MinioStorageDriver` implements the same 6 methods (`put/get/delete/list/exists/makeDirectory`) and is selected per-bucket via `StorageManager`, with zero API/CLI changes required.
- **Authorization** (`app/Services/Authorization/AccessControlService`) — single source of truth for "can this actor do X on bucket/key", consumed by both the `ScopedPermission` route middleware (bucket/object routes) and Policies (`BucketPolicy`, `ObjectPolicy`, `ApiTokenPolicy`).
- **Jobs** (`app/Jobs`) — everything that shouldn't block a request: audit log writes, virus scanning, and scheduled cleanup/recalculation.

See [`ERD.md`](./ERD.md) for the database schema and [`DEPLOYMENT.md`](./DEPLOYMENT.md) for how these pieces map onto real infrastructure.
