# FileServer CLI Guide

The FileServer CLI is a command-line client for managing buckets and objects through the FileServer API.

It uses the same scoped bearer API tokens as other API clients. The administrator password is not used by the CLI.

## Requirements

- PHP 8.2 or newer
- Composer
- A reachable FileServer API
- A FileServer API token

## Install from the repository

Use this method when developing or testing the CLI from this repository:

```bash
cd cli
composer install
php bin/fileserver --version
```

## Install globally

For normal use, install the published Composer package:

```bash
composer global require narrasoft/fileserver-cli
```

Ensure Composer's global binary directory is on your `PATH`:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

Then verify the installation:

```bash
fileserver --version
```

If Composer uses a different global directory, find it with:

```bash
composer global config bin-dir --absolute
```

## Create an API token

Create a token in the FileServer dashboard:

1. Open **Tokens -> New token**.
2. Give the token a descriptive name, such as `developer-laptop` or `backup-job`.
3. Select only the required permissions.
4. Restrict the token to a bucket and path prefix when possible.
5. Copy the raw token immediately. FileServer displays it only once.

Common permissions include:

- `bucket:list`
- `bucket:read`
- `bucket:write`
- `bucket:delete`
- `object:list`
- `object:read`
- `object:write`
- `object:delete`

For a read-only download client, grant `bucket:read`, `object:list`, and `object:read`. For an upload client, add `object:write`.

## Configure the CLI

Configure a local FileServer instance:

```bash
fileserver config:set endpoint http://localhost:8000/api/v1
fileserver config:set token YOUR_RAW_API_TOKEN
fileserver config:set default-bucket documents
```

Configure a deployed FileServer instance:

```bash
fileserver config:set endpoint https://files.example.com/api/v1
fileserver config:set token YOUR_RAW_API_TOKEN
fileserver config:set default-bucket documents
```

View the saved configuration:

```bash
fileserver config:list
```

The configuration file is stored at:

```text
~/.fileserver/config.json
```

The CLI creates this file with restricted permissions. Treat it as a secret because it contains the API token.

## Configuration precedence

Each value can be supplied in three ways. The highest-priority value wins:

```text
CLI flag > environment variable > configuration file
```

Environment variables are useful for CI jobs and temporary commands:

```bash
export FILESERVER_ENDPOINT=https://files.example.com/api/v1
export FILESERVER_TOKEN=fsk_your_token
export FILESERVER_BUCKET=documents
```

You can also set them for one command only:

```bash
FILESERVER_ENDPOINT=http://localhost:8000/api/v1 \
FILESERVER_TOKEN=fsk_your_token \
FILESERVER_BUCKET=documents \
fileserver bucket:list
```

Do not commit tokens to shell scripts or source control. Use the CI platform's secret variables instead.

## Check the token

Verify that the endpoint and token work:

```bash
fileserver token:check
```

If this returns an authentication error, check the endpoint, token, token status, and token permissions.

## Bucket commands

List available buckets:

```bash
fileserver bucket:list
```

Create a bucket:

```bash
fileserver bucket:create documents
```

Create a versioned bucket with limits:

```bash
fileserver bucket:create documents \
  --versioning \
  --quota-bytes=53687091200 \
  --max-file-size-bytes=5368709120
```

The token must have the corresponding bucket permissions for bucket operations.

## Upload files

Upload a local file to an object key:

```bash
fileserver upload ./report.pdf documents/reports/2026/report.pdf
```

Upload an image:

```bash
fileserver upload ./vehicle-photo.jpg documents/applicants/42/vehicle-photo.jpg
```

The destination uses this format:

```text
bucket/object-key
```

Object keys can contain folders. Use forward slashes and avoid leading slashes.

## List objects

List objects in a bucket:

```bash
fileserver list documents
```

List one prefix:

```bash
fileserver list documents --prefix=reports/2026/
```

Control pagination:

```bash
fileserver list documents --page=2 --per-page=100
```

## Inspect object metadata

```bash
fileserver info documents/reports/2026/report.pdf
```

Metadata can include the object size, content type, checksum, version information, and timestamps.

## Download files

Download an object to a local destination:

```bash
fileserver download documents/reports/2026/report.pdf ./report.pdf
```

The destination directory must already exist and be writable.

## Create folder markers

FileServer uses object keys rather than physical folders. To create an empty folder marker:

```bash
fileserver mkdir documents reports/2026/
```

Uploading an object with a nested key also creates the logical path as needed.

## Delete files

Delete an object:

```bash
fileserver delete documents/reports/2026/report.pdf
```

Deletion is permanent from the CLI perspective. Confirm the bucket and object key before running this command, especially in automation.

## JSON output and scripting

Add `--json` to receive machine-readable output:

```bash
fileserver --json bucket:list
fileserver --json info documents/reports/2026/report.pdf
```

Suppress non-essential output with `--quiet`:

```bash
fileserver --quiet upload ./report.pdf documents/report.pdf
```

Use verbosity flags when diagnosing a request:

```bash
fileserver -v token:check
fileserver -vv upload ./report.pdf documents/report.pdf
fileserver -vvv download documents/report.pdf ./report.pdf
```

## Exit codes

| Code | Meaning |
| ---: | --- |
| `0` | Command completed successfully |
| `1` | Generic application error |
| `2` | Invalid command usage or arguments |
| `3` | Authentication or authorization failure (`401` or `403`) |
| `4` | Bucket or object was not found (`404`) |
| `5` | Network failure after retries were exhausted |

Example shell handling:

```bash
if fileserver upload ./report.pdf documents/report.pdf; then
    echo "Upload completed"
else
    case "$?" in
        3) echo "Check the API token and permissions" >&2 ;;
        4) echo "Check the bucket or object path" >&2 ;;
        5) echo "Check FileServer availability" >&2 ;;
        *) echo "Upload failed" >&2 ;;
    esac
    exit 1
fi
```

## Common problems

### `Unable to locate the Composer autoloader`

For a repository checkout, install dependencies:

```bash
cd cli
composer install
```

### Authentication error

Check the endpoint and token:

```bash
fileserver config:list
fileserver token:check
```

Make sure the token has permissions for the requested bucket and object path. A token scoped to `documents/incoming/` cannot access `documents/private/`.

### Object not found

Check the exact bucket and key:

```bash
fileserver list documents --prefix=reports/
fileserver info documents/reports/2026/report.pdf
```

Object keys are case-sensitive.

### Network error

Confirm that the API is running and reachable:

```bash
curl https://files.example.com/api/v1/health
```

For a local installation, the default API URL is usually:

```text
http://localhost:8000/api/v1
```

### Upload rejected

Check the FileServer bucket quota, maximum object size, allowed file types, and the token's `object:write` permission.

## Security recommendations

- Use one token per person, service, or automation job.
- Grant the smallest set of permissions required.
- Restrict tokens to a bucket and path prefix.
- Store tokens in `~/.fileserver/config.json` or a secret manager.
- Rotate tokens regularly and revoke unused tokens.
- Do not use the administrator password in CLI scripts.
- Do not enable shell tracing such as `set -x` while a token is present in an environment variable.
- Use HTTPS for remote FileServer instances.

## Related documentation

- [`API_EXAMPLES.md`](./API_EXAMPLES.md) - REST API examples.
- [`INTEGRATION_GUIDE.md`](./INTEGRATION_GUIDE.md) - Integrating FileServer with another application.
- [`../cli/README.md`](../cli/README.md) - CLI package reference.
- [`../api/openapi/openapi.yaml`](../api/openapi/openapi.yaml) - OpenAPI contract.
