# Deployment Guide

Target: a single Ubuntu 22.04/24.04 LTS server running Nginx, PHP-FPM,
PostgreSQL, Redis, and Supervisor. Scale components out to separate hosts
later without changing the application — everything below is 12-factor
(config via environment, no local state outside `storage/` and the DB).

## 1. Prerequisites

```bash
sudo apt update
sudo apt install -y nginx postgresql redis-server supervisor \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd \
  git unzip

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node (for building the admin SPA)
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt install -y nodejs
```

Optional, for virus scanning (`FILESERVER_VIRUS_SCAN_ENABLED=true`):

```bash
sudo apt install -y clamav clamav-daemon
sudo freshclam
sudo systemctl enable --now clamav-daemon
```

## 2. PostgreSQL

```bash
sudo -u postgres psql <<'SQL'
CREATE DATABASE fileserver;
CREATE USER fileserver WITH ENCRYPTED PASSWORD 'change-me';
GRANT ALL PRIVILEGES ON DATABASE fileserver TO fileserver;
ALTER DATABASE fileserver OWNER TO fileserver;
SQL
```

## 3. Application code

```bash
sudo mkdir -p /var/www/fileserver
sudo chown "$USER":www-data /var/www/fileserver
git clone <your-repo-url> /var/www/fileserver
cd /var/www/fileserver
```

### 3a. API (Laravel)

```bash
cd /var/www/fileserver/api
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# Edit .env: DB_*, REDIS_*, APP_URL, FRONTEND_URL,
# FILESERVER_CORS_ALLOWED_ORIGINS, SANCTUM_STATEFUL_DOMAINS (your admin domain),
# FILESERVER_SUPER_ADMIN_EMAIL / FILESERVER_SUPER_ADMIN_PASSWORD (optional).
php artisan migrate --force
php artisan db:seed --force   # prints the generated Super Admin password ONCE — save it
php artisan config:cache
php artisan route:cache
php artisan event:cache

sudo mkdir -p storage/app/buckets
sudo chown -R www-data:www-data storage bootstrap/cache storage/app/buckets
sudo chmod -R 775 storage bootstrap/cache
```

### 3b. Admin dashboard (Vue SPA)

```bash
cd /var/www/fileserver/admin
npm ci
cp .env.example .env
# Edit .env: VITE_API_BASE_URL / VITE_API_ROOT_URL -> https://files.example.com
npm run build   # outputs to dist/ — Nginx serves this directly
```

### 3c. CLI package

Not deployed to the server — this is what *clients* install locally:

```bash
composer global require narrasoft/fileserver-cli
```

## 4. PHP-FPM pool

Create `/etc/php/8.4/fpm/pool.d/fileserver.conf` (or edit `www.conf`):

```ini
[fileserver]
user = www-data
group = www-data
listen = /run/php/php-fileserver.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
php_admin_value[upload_max_filesize] = 5G
php_admin_value[post_max_size] = 5G
php_admin_value[max_execution_time] = 300
```

`upload_max_filesize`/`post_max_size` must be >= the largest
`max_file_size_bytes` you configure on any bucket, or PHP rejects the
upload before Laravel ever sees it.

```bash
sudo systemctl restart php8.4-fpm
```

## 5. Nginx

`/etc/nginx/sites-available/fileserver-api`:

```nginx
server {
    listen 443 ssl http2;
    server_name files.example.com;

    ssl_certificate     /etc/letsencrypt/live/files.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/files.example.com/privkey.pem;

    root /var/www/fileserver/api/public;
    index index.php;

    client_max_body_size 5120M;   # match php.ini upload_max_filesize

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fileserver.sock;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`/etc/nginx/sites-available/fileserver-admin`:

```nginx
server {
    listen 443 ssl http2;
    server_name admin.example.com;

    ssl_certificate     /etc/letsencrypt/live/admin.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/admin.example.com/privkey.pem;

    root /var/www/fileserver/admin/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;   # SPA history-mode fallback
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/fileserver-api /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/fileserver-admin /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Both domains must be reachable over HTTPS with distinct origins for the
Sanctum SPA cookie flow to work correctly (`SANCTUM_STATEFUL_DOMAINS` and
`FILESERVER_CORS_ALLOWED_ORIGINS` in `api/.env` must list the admin
domain).

## 6. Redis

Default install is fine for a single box. For production, set a password
in `/etc/redis/redis.conf` (`requirepass ...`) and put it in `api/.env`'s
`REDIS_PASSWORD`.

## 7. Queue workers (Supervisor)

Copy `deploy/supervisor/fileserver-worker.conf` (adjust the `directory`/
`command` paths to match your deployment path) to
`/etc/supervisor/conf.d/`:

```bash
sudo cp deploy/supervisor/fileserver-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start fileserver-worker:*
```

## 8. Scheduler (cron)

The scheduled jobs in `api/routes/console.php` (`PurgeExpiredTokens`,
`PurgeSoftDeletedObjects`, `RecalculateBucketUsage`) only run when
`schedule:run` is invoked. Add one cron entry:

```cron
* * * * * cd /var/www/fileserver/api && php artisan schedule:run >> /dev/null 2>&1
```

## 9. Log rotation

Laravel logs to `storage/logs/laravel.log` and the worker logs to
`storage/logs/worker.log`. Add `/etc/logrotate.d/fileserver`:

```
/var/www/fileserver/api/storage/logs/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
}
```

## 10. Zero-downtime deploys (outline)

```bash
cd /var/www/fileserver
git pull
cd api && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
sudo supervisorctl restart fileserver-worker:*
cd ../admin && npm ci && npm run build
sudo systemctl reload php8.4-fpm
```

## 11. Checklist

- [ ] `.env` for `api/` has a unique `APP_KEY`, production `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `storage/` and `bootstrap/cache/` are writable by `www-data` only.
- [ ] `storage/app/buckets` lives on a volume with enough capacity/IOPS for expected traffic (or is itself a mounted network filesystem — the local driver doesn't care, as long as normal filesystem semantics apply).
- [ ] TLS certificates valid for both the API and admin domains.
- [ ] `FILESERVER_RATE_LIMIT_API` / `FILESERVER_RATE_LIMIT_AUTH` tuned for expected load.
- [ ] Postgres and Redis are not reachable from the public internet.
- [ ] Backups: `pg_dump` on a schedule, plus whatever backup story your `storage/app/buckets` filesystem/volume provides.
