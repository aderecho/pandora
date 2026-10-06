# Integration Guide

This guide explains how to integrate FileServer with another application, including Laravel and Django applications. FileServer is an HTTP API for buckets and objects; the integrating application does not need direct access to the FileServer database or storage directory.

For endpoint-level examples, see [`API_EXAMPLES.md`](./API_EXAMPLES.md). The complete request and response contract is [`../api/openapi/openapi.yaml`](../api/openapi/openapi.yaml).

## Integration model

The integrating application should:

1. Create a dedicated FileServer API token.
2. Store the token in a server-side secret manager or environment variable.
3. Send the token as a bearer credential for server-to-server requests.
4. Store the returned bucket name and object key in its own database.
5. Request presigned URLs when a browser needs to download or upload a file.

Do not use the FileServer administrator password for application requests. Do not expose an API token in browser JavaScript, mobile binaries, logs, or URLs.

## Prerequisites

- A reachable FileServer URL, for example `https://files.example.com`.
- A bucket, for example `documents`.
- A dedicated API token with only the required permissions.
- Network access from the integrating application to FileServer.
- TLS enabled for production deployments.

The API base URL is the FileServer URL followed by `/api/v1`:

```text
https://files.example.com/api/v1
```

## Create a token

Create the token from the FileServer dashboard under **Tokens -> New token**. Select only the permissions required by the integration.

Typical permissions are:

- `bucket:read` to inspect a bucket.
- `object:list` to list objects.
- `object:read` to download objects or create download URLs.
- `object:write` to upload objects or create upload URLs.
- `object:delete` to delete objects.

Restrict the token to a bucket and, where possible, a path prefix such as `vmis/`. The raw token is shown only once. Save it immediately in the integrating application's secret store.

## Configuration

Add server-side configuration to the integrating application. For a Laravel application, add this to `.env`:

```dotenv
FILESERVER_URL=https://files.example.com/api/v1
FILESERVER_TOKEN=fsk_replace_with_the_raw_token
FILESERVER_BUCKET=documents
FILESERVER_TIMEOUT=30
```

Add the corresponding service configuration in `config/services.php`:

```php
'fileserver' => [
    'url' => rtrim(env('FILESERVER_URL'), '/'),
    'token' => env('FILESERVER_TOKEN'),
    'bucket' => env('FILESERVER_BUCKET', 'documents'),
    'timeout' => (int) env('FILESERVER_TIMEOUT', 30),
],
```

Never commit the real `.env` file or a raw token to source control.

For a Django application, add the same values to `.env` (read with `django-environ` or `python-decouple`) and reference them from `settings.py`:

```python
# settings.py
import environ

env = environ.Env()

FILESERVER_URL = env("FILESERVER_URL", default="https://files.example.com/api/v1")
FILESERVER_TOKEN = env("FILESERVER_TOKEN")
FILESERVER_BUCKET = env("FILESERVER_BUCKET", default="documents")
FILESERVER_TIMEOUT = env.int("FILESERVER_TIMEOUT", default=30)
```

## Check connectivity

Use the unauthenticated health endpoint during deployment checks:

```bash
curl https://files.example.com/api/v1/health
```

A successful response uses the standard response envelope:

```json
{
  "data": {
    "status": "ok"
  },
  "error": null
}
```

## Upload an object

Authenticated multipart upload:

```bash
curl -X POST \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  -F "key=vmis/applicants/42/drivers-license.pdf" \
  -F "file=@./drivers-license.pdf" \
  https://files.example.com/api/v1/buckets/documents/objects
```

The integrating application should save the returned object key, not the file contents. A useful database representation is:

```text
storage_provider: fileserver
storage_bucket: documents
storage_key: vmis/applicants/42/drivers-license.pdf
```

Object keys should use forward slashes, avoid leading slashes, and be treated as opaque identifiers. Do not construct filesystem paths from user input.

## Upload from Laravel

Laravel's HTTP client can send the file as multipart data:

```php
use Illuminate\Support\Facades\Http;

$bucket = config('services.fileserver.bucket');
$key = 'vmis/applicants/'.$applicant->id.'/'.basename($file->getClientOriginalName());

$response = Http::withToken(config('services.fileserver.token'))
    ->acceptJson()
    ->timeout(config('services.fileserver.timeout', 30))
    ->attach(
        'file',
        fopen($file->getRealPath(), 'r'),
        $file->getClientOriginalName()
    )
    ->post(config('services.fileserver.url').'/buckets/'.rawurlencode($bucket).'/objects', [
        'key' => $key,
    ]);

$response->throw();

$object = $response->json('data');
// Persist $bucket and $key in the integrating application's database.
```

Use a unique or application-owned key when replacing files. For example, include the owning record ID and document type. Validate file size and type in the integrating application before uploading; FileServer also applies its configured object limits and validation rules.

## Upload from Django

Use the `requests` library to send the file as multipart data:

```python
import os
import requests
from django.conf import settings

def upload_to_fileserver(applicant_id, uploaded_file):
    key = f"vmis/applicants/{applicant_id}/{os.path.basename(uploaded_file.name)}"

    response = requests.post(
        f"{settings.FILESERVER_URL}/buckets/{settings.FILESERVER_BUCKET}/objects",
        headers={"Authorization": f"Bearer {settings.FILESERVER_TOKEN}"},
        data={"key": key},
        files={"file": (uploaded_file.name, uploaded_file, uploaded_file.content_type)},
        timeout=settings.FILESERVER_TIMEOUT,
    )
    response.raise_for_status()

    object_data = response.json()["data"]
    # Persist settings.FILESERVER_BUCKET and key on the model instance.
    return object_data
```

In a view or DRF serializer, call this after validating the incoming `UploadedFile` and before saving the model:

```python
from django.http import JsonResponse
from django.views import View

class ApplicantDocumentUploadView(View):
    def post(self, request, applicant_id):
        uploaded_file = request.FILES["file"]
        # Validate size/type here before calling FileServer.
        object_data = upload_to_fileserver(applicant_id, uploaded_file)

        Document.objects.create(
            applicant_id=applicant_id,
            storage_provider="fileserver",
            storage_bucket=settings.FILESERVER_BUCKET,
            storage_key=object_data["key"],
        )
        return JsonResponse({"status": "ok"})
```

If the FileServer request raises `requests.HTTPError` or `requests.Timeout`, catch it and return an error response rather than creating the `Document` row, so the database is never left pointing at an object that failed to upload.

## List and inspect objects

List objects under a prefix:

```bash
curl -G \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  --data-urlencode "prefix=vmis/applicants/42/" \
  https://files.example.com/api/v1/buckets/documents/objects
```

Get metadata and version history for an object:

```bash
curl \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  https://files.example.com/api/v1/buckets/documents/objects/vmis/applicants/42/drivers-license.pdf/metadata
```

When constructing URLs in application code, URL-encode each path segment as appropriate. Never concatenate an untrusted key into a URL without encoding and validation.

## Download an object through the server

For server-side processing, send the bearer token and stream the response:

```bash
curl -L \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  -o drivers-license.pdf \
  https://files.example.com/api/v1/buckets/documents/objects/vmis/applicants/42/drivers-license.pdf
```

This approach keeps the object private and routes the file through the integrating server.

## Generate a browser download URL

For browser downloads, ask FileServer for a temporary signed URL:

```bash
curl -X POST \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  https://files.example.com/api/v1/buckets/documents/objects/vmis/applicants/42/drivers-license.pdf/presigned-download
```

The response contains a URL similar to:

```json
{
  "data": {
    "url": "https://files.example.com/api/v1/public/objects/download?...",
    "method": "GET"
  },
  "error": null
}
```

Return that URL from the integrating application's own authenticated endpoint or redirect the user to it. The browser does not need the bearer token. Presigned URLs expire, so generate them when needed instead of storing them permanently.

## Direct browser upload

For large files, ask FileServer for a temporary upload URL:

```bash
curl -X POST \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"key":"vmis/applicants/42/vehicle-photo.jpg","max_size_bytes":10485760}' \
  https://files.example.com/api/v1/buckets/documents/presigned-upload
```

Then upload the file with the method and URL returned by FileServer:

```bash
curl -X PUT \
  --data-binary @vehicle-photo.jpg \
  "https://files.example.com/api/v1/public/objects/upload?..."
```

The integrating application must authorize the user before issuing the presigned URL. A presigned URL is a temporary capability; do not log it or give it a longer lifetime than necessary.

## Delete an object

Delete only after the integrating application's database update strategy is defined. A common order is to delete the FileServer object first, then remove the local reference after a successful response.

```bash
curl -X DELETE \
  -H "Authorization: Bearer $FILESERVER_TOKEN" \
  https://files.example.com/api/v1/buckets/documents/objects/vmis/applicants/42/drivers-license.pdf
```

For important data, use a retryable cleanup job for failures rather than silently losing the local reference.

## Error handling

Every response is wrapped as either:

```json
{
  "data": {},
  "error": null
}
```

or:

```json
{
  "data": null,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable message",
    "details": {}
  }
}
```

Recommended behavior:

- Treat `401` as an invalid, expired, revoked, or missing token.
- Treat `403` as an insufficient token permission or bucket/path scope.
- Treat `404` as a missing bucket or object.
- Treat `413` as a file-size or quota violation.
- Treat `422` as invalid request data.
- Retry transient `429`, `502`, `503`, and `504` responses with exponential backoff.
- Do not retry validation, authorization, or not-found errors without changing the request.

Log the FileServer error code and your own request identifier, but never log bearer tokens, passwords, or presigned URLs.

## Production checklist

- [ ] FileServer is served over HTTPS.
- [ ] The integration has its own API token.
- [ ] The token has the smallest required permissions and bucket scope.
- [ ] The token is stored outside source control.
- [ ] The integrating database stores bucket and object key values.
- [ ] Browser access uses presigned URLs rather than bearer tokens.
- [ ] Uploads validate size, MIME type, and ownership before issuing or sending files.
- [ ] Timeouts, retries, and failed-upload cleanup are implemented.
- [ ] Health checks and FileServer error codes are monitored.
- [ ] Token rotation and revocation procedures are documented.
