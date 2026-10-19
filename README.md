# TeamSuite_ver.3

Ελληνική πλατφόρμα SaaS διαχείρισης αθλητικών συλλόγων / ακαδημιών.  
**Brand UI:** TeamSuite · **Project:** TeamSuite_ver.3

PostgreSQL (production) = πηγή αλήθειας. **Όχι Vercel / Vapor / serverless billing.**

## Stack

- Laravel 13 + PHP 8.3 + Inertia.js + React
- PostgreSQL (prod) · SQLite OK για τοπικό scaffold
- Redis + Supervisor queues · system cron scheduler
- Capacitor Android/iOS → ίδιο VPS HTTPS origin (Φάση 11)

## Αρχιτεκτονική (Φάση 0)

Δες `docs/architecture/`:

- [ADR-0001 Stack & Hosting](docs/architecture/ADR-0001-stack-and-hosting.md) + εκτίμηση κόστους VPS
- [ERD](docs/architecture/ERD.md)
- [Role × Module matrix](docs/architecture/role-module-matrix.md)
- [BackupPayload mapping](docs/architecture/backup-payload-mapping.md)
- [Φάσεις 0–11](docs/architecture/phases.md)

## Local setup

```bash
# Απαιτείται PHP 8.3+, Composer, Node 20+
composer install
cp .env.example .env   # αν χρειάζεται
php artisan key:generate

# SQLite (default) ή PostgreSQL:
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=teamsuite
# DB_USERNAME=...
# DB_PASSWORD=...

touch database/database.sqlite   # μόνο για sqlite
php artisan migrate --seed
npm install --legacy-peer-deps
npm run build
php artisan serve
```

### Accounts (μετά το seed)

| Email | Password | Ρόλος |
|-------|----------|--------|
| `tsemperlidis.nikos@gmail.com` | *(ορισμένο στο seed)* | **platform_admin** |
| `admin@teamsuite.local` | `password` | admin συλλόγου |
| `platform@teamsuite.local` | `password` | platform_admin (demo) |

## Backup / Restore (κριτήριο go-live)

UI: **Ρυθμίσεις → Επαναφορά** (`/admin/settings/backup`)  
Fixture: `tests/fixtures/teamsuite-club-backup-minimal.json`  
Υποστήριξη παλιού TeamSuite Club JSON (`scope: club`) + platform full — ETL πλήρες στη Φάση 9.

```bash
php artisan test --filter=TeamSuiteBackupRestoreTest
```

## VPS (σύνοψη)

Nginx + PHP-FPM + PostgreSQL + Redis + Supervisor (`queue:work`) + cron (`schedule:run`).  
Storage: τοπικός δίσκος · optional S3-compatible αργότερα.  
Εκτίμηση: **€5–40/μήνα** ανάλογα με μέγεθος (βλ. ADR-0001).

## Capacitor

Φάση 11: apps φορτώνουν το VPS URL → κάθε deploy = άμεσο UI update χωρίς store review.

## Φάσεις

Τώρα: **Φάση 0 + 1** (scaffold, auth, clubs, tenancy, grouped sidebar, backup dry-run stub).  
Επόμενο: **Φάση 2** — αθλητές / τμήματα / γονείς / portals.
