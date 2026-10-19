# BackupPayload → PostgreSQL mapping

**MUST HAVE:** επαναφορά παλιών TeamSuite Club JSON (και platform full).  
Πηγή αναφοράς (read-only): `C:\TeamSuite\src\utils\backupArchive.ts`, `docs\BACKUP-INVENTORY.md`.

## Μορφή αρχείου

- Όνομα: `TeamSuite-{όνομα-συλλόγου}-YYYY-MM-DD-ΩΩ-ΛΛ (ενεργοί).json`
- `scope`: `"club"` | `"platform"`
- Πεδία: `exportedAt`, `sourceClubId?`, `appData?`, `appDataByClub?`, `clubs[]`, `users[]`, `platformConfig?`

Secrets στο αρχείο είναι redacted → **μην αντικαθιστάς** υπάρχοντα secrets αν εισερχόμενο κενό/`********`.

## Επίπεδο 1 — Envelope

| JSON path | Table / action |
|-----------|----------------|
| `scope`, `exportedAt`, `sourceClubId` | `club_backups` metadata + restore job |
| `clubs[]` | `clubs` + `club_settings` (merge secrets) |
| `users[]` | `users` + `club_user` (χωρίς password αν κενό → κράτα υπάρχον) |
| `platformConfig` | platform settings / license packages / branding (μόνο scope=platform) |
| `appData` ή `appDataByClub[clubId]` | ETL ανά συλλογή παρακάτω |

## Επίπεδο 2 — AppData συλλογές

| AppData key | Target table(s) |
|-------------|-----------------|
| `students` | `athletes` (+ AMKA encrypt on import) |
| `coaches` | `coaches` |
| `staff` | `staff_members` |
| `clubMembers` | `club_members` |
| `associations` | `associations` |
| `sports` | `sports` |
| `clubSeasons` | `club_seasons` |
| `classes` | `academy_classes` + pivots |
| `schedule` | `schedule_slots` |
| `trainings` | `trainings` |
| `attendance` | `attendances` |
| `matches` | `matches` |
| `athleteChangeLogs` | `athlete_change_logs` |
| `transactions` | `athlete_transactions` |
| `feeChargeTemplates` | `fee_charge_templates` |
| `feeReminderLogs` | `fee_reminder_logs` |
| `revenues` | `revenues` |
| `expenses` | `expenses` |
| `cashAccounts` | `cash_accounts` |
| `budgets` | `budget_lines` |
| `closedFinanceMonths` / `financeMonthLockRev` | `closed_finance_months` |
| `receiptNumberRanges` / `receiptIssues` / `receiptNextBySeries` | `receipt_series`, `receipt_issues` |
| `onlineCheckouts` | `online_checkouts` |
| `discountReasons` | `discount_reasons` |
| `products` | `warehouse_products` |
| `stockMovements` | `stock_movements` |
| `clothingPackages` | `clothing_packages` |
| `sizeChart` | `size_charts` |
| `facilities` | `facilities` |
| `rentalSettings` | `rental_settings` |
| `rentalBookings` | `rental_bookings` |
| `announcements` | `announcements` |
| `photos` | `gallery_photos` |
| `partnerBusinesses` / `partnerOffers` | `partner_businesses`, `partner_offers` |
| `parentLinks` | `parent_athlete_links` |
| `progressReports` | `progress_reports` |
| `registrationApplications` | `registration_applications` |
| `documentProtocolEntries` | `document_protocol_entries` |
| `amkaAccessLogs` | `amka_access_logs` |
| `gdprAuditLogs` | `gdpr_audit_logs` |
| `emailUnsubscribes` | `email_unsubscribes` |
| `termsOfUseHtml` / `dpaHtml` / `retentionPolicyHtml` / `dataRetentionMonths` | `club_settings` |
| `suppressedFeeChargeKeys` | `suppressed_fee_charge_keys` |
| `deleted*Ids` | soft-delete ή tombstone tables — σεβασμός στο restore |

## Restore flow (αποδοχή)

1. Upload JSON → parse + validate schema.
2. Dry-run: μετρητές (athletes, classes, transactions, members…).
3. Αν λείπει σύλλογος → create με **ίδιο `sourceClubId`**.
4. Αν υπάρχει → UI επιβεβαίωση replace/merge.
5. ETL σε transactions DB (idempotent όπου δυνατόν by legacy id).
6. Preserve secrets (`mergeClubsPreservingSecrets` ισοδύναμο).
7. Feature test με fixture + UI «Επαναφορά από αρχείο TeamSuite».

## Native export (ver.3)

Ξεχωριστό format `TeamSuite_ver.3` club export· το παλιό format παραμένει **εισαγώγιμο για πάντα**.
