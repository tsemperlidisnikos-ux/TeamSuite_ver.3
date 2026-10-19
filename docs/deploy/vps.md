# VPS Deploy — TeamSuite_ver.3

**Απαγορεύεται:** Vercel, Vercel Blob, Vercel Cron, Laravel Vapor, serverless usage-billing ως κύριο hosting.

## Στόχος

Ένα (ή λίγα) πάντα-ενεργά servers:

- Nginx + PHP-FPM 8.3
- PostgreSQL 16+
- Redis
- Supervisor → `php artisan queue:work`
- Cron → `* * * * * php /var/www/teamsuite/artisan schedule:run`
- SSL (Let's Encrypt)
- Storage: `storage/app` στον δίσκο · optional S3-compatible αργότερα

## Εκτίμηση κόστους

| Τάξη | Μηνιαία |
|------|---------|
| Starter | €5–12 |
| Production | €15–40 |
| + object storage | €0–10 |

Λεπτομέρειες: `docs/architecture/ADR-0001-stack-and-hosting.md`.

---

## 1. Server (Ubuntu 24.04 παράδειγμα)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx postgresql redis-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl
```

Node 20+ για build (ή build τοπικά και ανέβασε `public/build`).

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 2. PostgreSQL

```bash
sudo -u postgres psql <<'SQL'
CREATE USER teamsuite WITH PASSWORD 'CHANGE_ME_STRONG';
CREATE DATABASE teamsuite OWNER teamsuite;
GRANT ALL PRIVILEGES ON DATABASE teamsuite TO teamsuite;
SQL
```

## 3. App deploy

```bash
sudo mkdir -p /var/www/teamsuite
sudo chown $USER:www-data /var/www/teamsuite
cd /var/www/teamsuite
git clone <YOUR_REPO_URL> .
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Ρύθμισε `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.yourdomain.gr

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=teamsuite
DB_USERNAME=teamsuite
DB_PASSWORD=CHANGE_ME_STRONG

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

FILESYSTEM_DISK=local
```

```bash
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force   # αν χρειάζεται platform admin
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

## 4. Nginx

`/etc/nginx/sites-available/teamsuite`:

```nginx
server {
    listen 80;
    server_name app.yourdomain.gr;
    root /var/www/teamsuite/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 64M;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/teamsuite /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d app.yourdomain.gr
```

## 5. Queue worker (Supervisor)

`/etc/supervisor/conf.d/teamsuite-worker.conf`:

```ini
[program:teamsuite-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/teamsuite/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/teamsuite/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start teamsuite-worker:*
```

## 6. Scheduler (cron)

```bash
sudo crontab -u www-data -e
```

Πρόσθεσε:

```
* * * * * cd /var/www/teamsuite && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Nightly DB backup

```bash
sudo mkdir -p /var/backups/teamsuite
sudo tee /etc/cron.daily/teamsuite-pgdump >/dev/null <<'EOF'
#!/bin/bash
set -euo pipefail
STAMP=$(date +%F)
export PGPASSWORD='CHANGE_ME_STRONG'
pg_dump -U teamsuite -h 127.0.0.1 teamsuite | gzip > "/var/backups/teamsuite/teamsuite-$STAMP.sql.gz"
find /var/backups/teamsuite -type f -mtime +14 -delete
EOF
sudo chmod +x /etc/cron.daily/teamsuite-pgdump
```

## 8. Ενημέρωση (deploy νέας έκδοσης)

```bash
cd /var/www/teamsuite
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart teamsuite-worker:*
```

## 8b. PHP upload limits (μεγάλα Club JSON)

Τα backup συλλόγων συχνά είναι 10–20MB+. Στο `php.ini` (FPM + CLI):

```ini
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 512M
```

Μετά: `sudo systemctl reload php8.3-fpm` (και επανεκκίνηση `php artisan serve` τοπικά).

## 9. Restore JSON συλλόγου μετά το go-live

1. Σύνδεση ως admin του συλλόγου
2. **Ρυθμίσεις → Επαναφορά από αρχείο TeamSuite**
3. Upload του `.json` → Προεπισκόπηση → Συγχώνευση/Αντικατάσταση
4. Έλεγχος μετρητών (αθλητές, τμήματα, συναλλαγές)

## Capacitor

Τα Android/iOS apps φορτώνουν το HTTPS origin του VPS. Κάθε deploy = άμεση ενημέρωση UI χωρίς store review.

## Checklist

- [ ] PHP 8.3 + extensions
- [ ] PostgreSQL + Redis
- [ ] `.env` production + `APP_KEY`
- [ ] `migrate --force`
- [ ] Nginx + SSL
- [ ] Supervisor queue workers
- [ ] Cron scheduler
- [ ] Nightly `pg_dump`
- [ ] Platform admin user seeded
- [ ] Test login + backup restore με fixture ή πραγματικό JSON
