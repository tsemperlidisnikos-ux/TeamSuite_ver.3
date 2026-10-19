# ADR-0001 — Stack & Hosting (VPS, όχι serverless)

**Status:** Accepted  
**Date:** 2026-10-15  
**Product:** TeamSuite_ver.3 (UI brand: TeamSuite)

## Context

Η παλιά TeamSuite στο Vercel έχει υψηλό και απρόβλεπτο κόστος (serverless + Blob + Cron). Χρειαζόμαστε πλήρη ισοδυναμία λειτουργιών με προβλέψιμο χαμηλό κόστος.

## Decision

| Layer | Choice |
|-------|--------|
| Backend | Laravel (τελευταία σταθερή) + PHP 8.3+ |
| DB | PostgreSQL (μοναδική πηγή αλήθειας) |
| Cache / queues | Redis + Supervisor workers |
| Scheduler | system cron → `php artisan schedule:run` |
| Frontend | Inertia.js + React (ίδιο origin / ένα deploy) |
| Auth | Session (web) + Sanctum όπου χρειάζεται API/mobile |
| Storage | τοπικός δίσκος VPS · optional S3-compatible αργότερα |
| Mobile | Capacitor Android/iOS → HTTPS origin του VPS |
| Hosting | **Απαγορεύεται** Vercel / Vercel Blob / Vapor / serverless-first |

## Architecture (υψηλό επίπεδο)

```
[ Browser / Capacitor apps ]
          │ HTTPS
          ▼
[ Nginx ] → [ PHP-FPM (Laravel + Inertia) ]
          │
          ├── PostgreSQL
          ├── Redis (cache, queues, sessions optional)
          ├── Supervisor → queue:work
          └── cron → schedule:run
          │
          └── storage/app (local)  [optional S3 later]
```

Multi-tenancy: κάθε domain entity scoped με `club_id` + middleware / global scopes.  
`platform_admin` βλέπει όλους τους συλλόγους.

## VPS cost estimate (μηνιαία, ενδεικτικά 2026)

| Setup | Spec | Εκτίμηση |
|-------|------|----------|
| Starter (demo / ≤5 clubs) | Hetzner CX22 / Contabo ~2–4 vCPU, 4–8 GB RAM | **€5–12** |
| Production (πολλοί σύλλογοι) | 4–8 vCPU, 16 GB, 160+ GB SSD | **€15–40** |
| Managed Postgres (optional) | ξεχωριστό | +€15–30 |
| Object storage (optional) | Hetzner/Backblaze/MinIO | +€0–10 |
| Domains + SSL | Let's Encrypt | €0–15/έτος |

**Στόχος:** < €50/μήνα για πλήρη production stack χωρίς usage-billing spikes.  
**Αποφυγή:** Vercel + Blob + Cron που κλιμακώνουν με sync traffic.

## Consequences

- Προβλέψιμο κόστος, πλήρης έλεγχος cron/queues/storage.
- Χρειάζεται ops: Nginx, SSL, backups Postgres, Supervisor.
- Capacitor φορτώνει το ίδιο URL → κάθε VPS deploy = instant UI update χωρίς store review.
