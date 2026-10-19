# Φάσεις υλοποίησης 0–11

| Φάση | Περιεχόμενο | Status |
|------|-------------|--------|
| **0** | ADR + ERD + role×module matrix + BackupPayload mapping + Laravel scaffold | **done** |
| **1** | Auth, clubs, tenancy, platform skeleton + grouped sidebar | **done** |
| **2** | Athletes / classes / coaches / parents + portals | **done** |
| **3** | Schedule / trainings / attendance / matches / calendar | **done** |
| **4** | Fees / transactions / receipts / finance | **done** |
| **5** | Announcements + email / SMS / push scaffolding | **done** (UI + jobs; provider wiring via club_settings) |
| **6** | Join / rent + applications | **done** |
| **7** | Warehouse, photos, protocol, partners, prints | **done** |
| **8** | Payment gateways + idempotent webhooks | **done** (Viva/Stripe/Eurobank endpoints) |
| **9** | GDPR / AMKA + TeamSuite JSON restore ETL + scheduled backups | **done** |
| **10** | Hardening, tests, indexes, VPS deploy docs | **done** (base) |
| **11** | Capacitor Android + iOS scaffold (VPS URL auto-update) | **done** (scaffold + docs) |

## Κριτήρια αποδοχής

1. Επαναφορά παλιού TeamSuite Club JSON — dry-run + merge/replace ETL  
2. Grouped admin sidebar  
3. Capacitor → VPS HTTPS (βλ. `mobile/`)  
4. Όχι Vercel / serverless ως κύριο hosting  
5. Πλήρες ERD στο PostgreSQL schema  
