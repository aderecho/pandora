# fileserver-cli

Official command-line client for the self-hosted FileServer platform.

## Install

```bash
composer global require narrasoft/fileserver-cli
# ensure Composer's global bin dir is on your PATH, e.g.:
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

For local development against this monorepo:

```bash
cd cli
composer install
php bin/fileserver --version
```

## Configure

```bash
fileserver config:set endpoint https://files.example.com/api/v1
fileserver config:set token YOUR_CLIENT_TOKEN
fileserver config:set default-bucket documents
fileserver config:list
```

Settings are stored at `~/.fileserver/config.json` (chmod 600). Every value
can be overridden per-invocation via environment variables or flags, in this
order of precedence: `--flag` > env var > config file.

```bash
FILESERVER_ENDPOINT=https://files.example.com/api/v1
FILESERVER_TOKEN=...
FILESERVER_BUCKET=documents
```

## Commands

```bash
fileserver bucket:list
fileserver bucket:create <bucket> [--quota-bytes=N] [--max-file-size-bytes=N] [--versioning] [--public]

fileserver upload <local-file> <bucket>/<object-key>
fileserver download <bucket>/<object-key> <destination>
fileserver list <bucket> [--prefix=folder/] [--page=1] [--per-page=50]
fileserver info <bucket>/<object-key>
fileserver delete <bucket>/<object-key>
fileserver mkdir <bucket>/<prefix>

fileserver token:check
fileserver config:list
```

Every command accepts `--json` (machine-readable output), `-q`/`--quiet`
(suppress non-essential output), and `-v`/`-vv`/`-vvv` (increasing verbosity).

## Exit codes

| Code | Meaning                              |
|------|---------------------------------------|
| 0    | Success                                |
| 1    | Generic error                          |
| 2    | Usage error (bad arguments)             |
| 3    | Authentication error (401/403)          |
| 4    | Not found (404)                         |
| 5    | Network error (retries exhausted)       |

## Testing

```bash
composer test
```
