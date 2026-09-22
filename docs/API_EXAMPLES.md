# API Examples

Base URL used below: `https://files.example.com/api/v1`. Full reference:
[`/docs`](http://localhost:8000/docs) (Swagger UI) or
[`api/openapi/openapi.yaml`](../api/openapi/openapi.yaml).

Every response is wrapped: `{"data": ..., "error": null}` on success,
`{"data": null, "error": {"code","message","details"}}` on failure.

## Health check

```bash
curl https://files.example.com/api/v1/health
```

## Admin login (session-based — used by the dashboard, not typical API clients)

```bash
curl -c cookies.txt https://files.example.com/sanctum/csrf-cookie

XSRF=$(grep XSRF-TOKEN cookies.txt | awk '{print $7}' | python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.stdin.read()))")

curl -b cookies.txt -c cookies.txt \
  -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $XSRF" \
  -d '{"email":"admin@fileserver.local","password":"..."}' \
  https://files.example.com/api/v1/auth/login
```

## Creating an API token (as an authenticated admin)

```bash
curl -b cookies.txt -H "X-XSRF-TOKEN: $XSRF" -H "Content-Type: application/json" \
  -d '{
        "name": "CI deploy token",
        "abilities": ["object:read", "object:write"],
        "bucket_scopes": [
          {"bucket": "documents", "path_prefix": "builds/", "permissions": ["object:read", "object:write"]}
        ]
      }' \
  https://files.example.com/api/v1/tokens
```

Response includes `data.raw_token` — **shown only once**. From here on,
examples use that raw token as a Bearer credential, exactly like the CLI
does.

```bash
export TOKEN="fsk_...redacted..."
```

## Buckets

```bash
# List
curl -H "Authorization: Bearer $TOKEN" https://files.example.com/api/v1/buckets

# Create
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name": "documents", "versioning_enabled": true}' \
  https://files.example.com/api/v1/buckets

# Get one (with usage stats in meta)
curl -H "Authorization: Bearer $TOKEN" https://files.example.com/api/v1/buckets/documents

# Update (rename is just changing `name`; storage_path is untouched)
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"quota_bytes": 53687091200}' \
  https://files.example.com/api/v1/buckets/documents

# Delete
curl -X DELETE -H "Authorization: Bearer $TOKEN" https://files.example.com/api/v1/buckets/documents
```

## Objects

```bash
# Upload
curl -X POST -H "Authorization: Bearer $TOKEN" \
  -F "key=reports/2026/q3.pdf" -F "file=@./q3.pdf" \
  https://files.example.com/api/v1/buckets/documents/objects

# Create an empty folder marker
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"key": "archive/"}' \
  https://files.example.com/api/v1/buckets/documents/objects

# List (S3-style, one level under a prefix)
curl -H "Authorization: Bearer $TOKEN" \
  "https://files.example.com/api/v1/buckets/documents/objects?prefix=reports/2026/"

# Download
curl -H "Authorization: Bearer $TOKEN" -OJ \
  https://files.example.com/api/v1/buckets/documents/objects/reports/2026/q3.pdf

# Metadata + version history
curl -H "Authorization: Bearer $TOKEN" \
  https://files.example.com/api/v1/buckets/documents/objects/reports/2026/q3.pdf/metadata

# Delete
curl -X DELETE -H "Authorization: Bearer $TOKEN" \
  https://files.example.com/api/v1/buckets/documents/objects/reports/2026/q3.pdf
```

## Presigned URLs (no bearer token needed to consume them)

```bash
# Ask for a temporary download link (15 min default TTL)
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://files.example.com/api/v1/buckets/documents/objects/reports/2026/q3.pdf/presigned-download

# -> {"data": {"url": "https://files.example.com/api/v1/public/objects/download?...", "method": "GET", ...}}
curl "https://files.example.com/api/v1/public/objects/download?bucket=documents&key=reports%2F2026%2Fq3.pdf&expires=...&signature=..."

# Ask for a temporary upload link
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"key": "incoming/upload.bin", "max_size_bytes": 104857600}' \
  https://files.example.com/api/v1/buckets/documents/presigned-upload

# -> {"data": {"url": "https://files.example.com/api/v1/public/objects/upload?...", "method": "PUT", ...}}
curl -X PUT --data-binary @./upload.bin \
  "https://files.example.com/api/v1/public/objects/upload?bucket=documents&key=incoming%2Fupload.bin&expires=...&max_size=104857600&signature=..."
```

## Audit logs (requires the `audit:read` permission)

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "https://files.example.com/api/v1/audit-logs?status=failure&from=2026-01-01"

curl -H "Authorization: Bearer $TOKEN" \
  https://files.example.com/api/v1/audit-logs/export -o audit-log.csv
```

## Equivalent CLI commands

Every example above has a CLI equivalent — see [`cli/README.md`](../cli/README.md):

```bash
fileserver bucket:create documents --versioning
fileserver upload ./q3.pdf documents/reports/2026/q3.pdf
fileserver list documents --prefix=reports/2026/
fileserver info documents/reports/2026/q3.pdf
fileserver download documents/reports/2026/q3.pdf ./q3.pdf
fileserver delete documents/reports/2026/q3.pdf
```
