# FileServer

An enterprise-grade, self-hosted, S3-style file storage platform: a
Laravel REST API, a Vue 3 admin dashboard, and a Composer CLI package,
built around a storage-driver abstraction so the local filesystem backend
can later be swapped for S3 or MinIO without touching the API or CLI.

- **`api/`** — Laravel 13 REST API (buckets, objects, tokens, audit logs, admin)
- **`admin/`** — Vue 3 + TypeScript + Tailwind admin dashboard (SPA)
- **`cli/`** — `narrasoft/fileserver-cli`, the Composer-installable CLI client
- **`docs/`** — architecture, ERD, deployment guide, API examples
- **`deploy/`** — Nginx, PHP-FPM, and Supervisor config files referenced by `docs/DEPLOYMENT.md`

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for a diagram of how
these pieces fit together and [`docs/ERD.md`](docs/ERD.md) for the
database schema.

## Quick start (local development)

Prerequisites: PHP 8.4+, Composer, Node 22+, PostgreSQL, Redis.

```bash
git clone <repo-url> fileserver && cd fileserver

# --- API ---
cd api
composer install
cp .env.example .env
php artisan key:generate
createdb fileserver               # or point DB_* at an existing database
php artisan migrate --seed        # prints a one-time Super Admin password — save it
php artisan serve                 # http://localhost:8000
```

In a second terminal, run the queue worker (audit logs, virus scanning,
scheduled cleanup jobs all go through Redis):

```bash
cd api && php artisan queue:work
```

```bash
# --- Admin dashboard ---
cd admin
npm install
cp .env.example .env              # defaults already point at localhost:8000
npm run dev                       # http://localhost:5173
```

```bash
# --- CLI ---
cd cli
composer install
php bin/fileserver config:set endpoint http://localhost:8000/api/v1
php bin/fileserver config:set token <a-client-token-from-the-dashboard>
php bin/fileserver bucket:list
```

## Admin setup

1. Run `php artisan migrate --seed` (above) and copy the printed Super
   Admin email/password — it is shown **once**.
2. Open the dashboard (`http://localhost:5173`) and sign in. You'll be
   redirected to **Change password** immediately, since the seeded
   account has `must_reset_password = true`.
3. Create a bucket (**Buckets → New bucket**) or use the seeded
   `documents` sample bucket.
4. Issue an API token (**Tokens → New token**): pick abilities and a
   bucket scope (with an optional path prefix), then copy the raw token
   from the one-time dialog — it's needed for the CLI or any API client
   and cannot be retrieved again.
5. Invite collaborators via **Users → New user**; they'll get a
   one-time generated password shown to you to relay securely, and are
   forced to change it on first login too.

## Token generation walkthrough (API)

```bash
curl -b cookies.txt -H "X-XSRF-TOKEN: $XSRF" -H "Content-Type: application/json" \
  -d '{
        "name": "CI deploy token",
        "abilities": ["object:read", "object:write"],
        "bucket_scopes": [{"bucket": "documents", "path_prefix": "builds/", "permissions": ["object:read", "object:write"]}]
      }' \
  http://localhost:8000/api/v1/tokens
```

See [`docs/API_EXAMPLES.md`](docs/API_EXAMPLES.md) for the full CSRF
handshake and more endpoint examples, or [`/docs`](http://localhost:8000/docs)
for interactive Swagger UI once the API is running.

## CLI usage

```bash
fileserver config:set endpoint https://files.example.com/api/v1
fileserver config:set token YOUR_CLIENT_TOKEN
fileserver config:set default-bucket documents

fileserver bucket:list
fileserver bucket:create documents --versioning
fileserver upload ./report.pdf documents/reports/2026/q3.pdf
fileserver list documents --prefix=reports/2026/
fileserver info documents/reports/2026/q3.pdf
fileserver download documents/reports/2026/q3.pdf ./q3.pdf
fileserver delete documents/reports/2026/q3.pdf
fileserver mkdir documents/archive
fileserver token:check
```

Every command supports `--json`, `-q`/`--quiet`, and `-v`/`-vv`/`-vvv`.
Full reference: [`cli/README.md`](cli/README.md).

## Testing

```bash
cd api  && php artisan test        # 67 tests: auth, buckets, objects, tokens,
                                    # admin, audit logs, storage driver, policies,
                                    # presigned URLs, background jobs
cd cli  && composer test           # 30 tests: config, retry, API client
cd admin && npm run build          # type-checks (vue-tsc) then builds
```

## Security recommendations

- **Rotate the Super Admin password immediately** after first login (the
  dashboard forces this via `must_reset_password`).
- **Tokens are shown once.** If one is lost, rotate it
  (`POST /tokens/{id}/rotate`) rather than trying to retrieve it — the
  raw value is never stored, only its SHA-256 hash.
- **Scope tokens narrowly.** Grant only the buckets/prefixes/abilities a
  client actually needs (`token_bucket_scopes`); a token with no bucket
  scope rows has no bucket access at all, even if it holds the ability.
- **Put the API and admin dashboard on separate origins** in production
  and keep `FILESERVER_CORS_ALLOWED_ORIGINS`/`SANCTUM_STATEFUL_DOMAINS`
  scoped to exactly that origin — never `*` with credentials enabled.
- **Set `FILESERVER_VIRUS_SCAN_ENABLED=true`** with a real ClamAV daemon
  in any environment accepting uploads from untrusted users; uploads are
  accepted immediately and scanned asynchronously, so infected files are
  quarantined shortly after acceptance rather than blocking the upload.
- **Tune `FILESERVER_RATE_LIMIT_API` / `FILESERVER_RATE_LIMIT_AUTH`** and
  keep them in front of a reverse proxy that itself rate-limits/blocks
  abusive IPs — Laravel's limiter is a second line of defense, not a
  substitute for network-level protection.
- **Configure `FILESERVER_RETENTION_DAYS`** deliberately: deleting a
  non-versioned object removes its bytes immediately (only metadata is
  retained for the audit trail); versioned buckets keep recoverable
  history for that many days before `PurgeSoftDeletedObjects` purges it.
- **Never commit `.env` files.** Each app ships an `.env.example`
  (`api/`, `admin/`, `cli/`) — copy, don't rename.

## Deployment

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the full Nginx +
PHP-FPM + PostgreSQL + Redis + Supervisor server setup, with ready-to-use
config files under [`deploy/`](deploy/).
