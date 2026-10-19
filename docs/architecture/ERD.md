# ERD — TeamSuite_ver.3 (πλήρες scope)

PostgreSQL = πηγή αλήθειας. Όλα τα club-scoped entities έχουν `club_id` (UUID) + soft deletes όπου ισχύει.

## Platform / Tenancy

```
LicensePackages 1──* Clubs
Clubs 1──1 ClubSettings
Clubs 1──* ClubBackups
Users *──* Clubs (membership + role)
Users *──* Permissions (Spatie ή custom)
LoginActivities *──1 Users
```

| Table | Key fields |
|-------|------------|
| `users` | id, name, email, password, role defaults, permissions[], finance_own_entries_only |
| `clubs` | id (preserve old sourceClubId), slug, name, branding, license_package_id, enabled_modules |
| `club_settings` | club_id, smtp*, sms*, push*, viva*, stripe*, eurobank*, join/rent flags (*secrets encrypted) |
| `club_user` | club_id, user_id, role, permissions |
| `license_packages` | name, athlete_cap, modules JSON |
| `login_activities` | user_id, ip, user_agent, at |
| `club_backups` | club_id, path, scope, exported_at, source |

## Μητρώο

```
Clubs 1──* Athletes / Coaches / StaffMembers / ClubMembers / Associations / Sports / ClubSeasons
Athletes *──* AcademyClasses (pivots + sports)
Parents (Users) *──* Athletes via ParentAthleteLinks
Athletes 1──* AthleteChangeLogs
```

| Table | Notes |
|-------|-------|
| `athletes` | status active\|inactive\|trial, AMKA encrypted + fingerprint, GDPR, στολή, εκπτώσεις |
| `coaches` | ΓΓΑ, άδειες |
| `staff_members` | ρόλοι προσωπικού |
| `club_members` | μητρώο μελών (+ tombstones via soft delete) |
| `associations` | σωματεία / ενώσεις |
| `sports` | αθλήματα συλλόγου |
| `club_seasons` | ημερομηνίες |
| `academy_classes` | τμήματα |
| `academy_class_athlete` | pivot |
| `parent_athlete_links` | γονέας ↔ αθλητής |
| `athlete_change_logs` | ιστορικό καρτέλας |

## Πρόγραμμα

| Table | Notes |
|-------|-------|
| `schedule_slots` | εβδομαδιαίο πρόγραμμα |
| `trainings` | προπονήσεις |
| `attendances` | παρουσίες |
| `matches` | αγώνες |
| `progress_reports` | αναφορές προόδου |

## Οικονομικά

| Table | Notes |
|-------|-------|
| `fee_charge_templates` | πρότυπα + auto-generate |
| `athlete_transactions` | χρεώσεις/πληρωμές + allocation |
| `receipt_series` / `receipt_issues` | σειρές, αύξων, ακυρώσεις |
| `revenues` / `expenses` | γενικά έσοδα/έξοδα |
| `cash_accounts` | ταμεία |
| `budget_lines` | προϋπολογισμός |
| `closed_finance_months` | κλειδωμένοι μήνες |
| `online_checkouts` | Viva/Stripe/Eurobank pending |
| `payment_settlements` | webhook settlements (idempotent) |
| `discount_reasons` | λόγοι έκπτωσης |

## Αποθήκη / Ενοικίαση

| Table | Notes |
|-------|-------|
| `warehouse_products` | προϊόντα |
| `stock_movements` | κινήσεις |
| `clothing_packages` | πακέτα ρουχισμού |
| `size_charts` | μεγέθη (ανά club) |
| `facilities` | εγκαταστάσεις |
| `rental_settings` | δημόσιο /rent/{slug} |
| `rental_bookings` | κρατήσεις |

## Επικοινωνία / Media / Partners

| Table | Notes |
|-------|-------|
| `announcements` | audience targeting |
| `email_unsubscribes` | ανά club/email |
| `gallery_photos` | GDPR-aware |
| `media_files` | logos, athlete photos, prints |
| `partner_businesses` / `partner_offers` | συμβεβλημένες |

## Δημόσια εγγραφή

| Table | Notes |
|-------|-------|
| `registration_applications` | full\|trial\|waitlist · pending\|approved\|rejected · GDPR snapshot |

## Πρωτόκολλο & GDPR

| Table | Notes |
|-------|-------|
| `document_protocol_entries` | αυτόματος αριθμός |
| `amka_access_logs` | χωρίς plaintext ΑΜΚΑ |
| `gdpr_audit_logs` | DSAR, consent, retention |
| Club legal HTML | terms / DPA / retention σε `club_settings` ή ξεχωριστό |

## Σχέσεις (απλοποιημένο διάγραμμα)

```
                    ┌──────────────┐
                    │ LicensePkg   │
                    └──────┬───────┘
                           │
┌─────────┐          ┌─────▼─────┐         ┌──────────┐
│  Users  │◄────────►│   Clubs   │────────►│ Settings │
└────┬────┘ membership└─────┬─────┘         └──────────┘
     │                      │
     │              ┌───────┼────────┬──────────┬─────────┐
     │              ▼       ▼        ▼          ▼         ▼
     │           Athletes Classes Schedule  Finance   Facilities
     │              │       │        │          │
     └─ParentLinks──┘       └─Attend─┘     Receipts/Payments
```

## Αποδοχή

Κανένα module της παλιάς AppData δεν παραλείπεται από το ERD. Η υλοποίηση γίνεται σε φάσεις 1–11· το schema καλύπτει τα πάντα από τη Φάση 0.
