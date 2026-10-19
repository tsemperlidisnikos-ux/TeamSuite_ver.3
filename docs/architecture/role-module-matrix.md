# Role × Module Matrix — TeamSuite_ver.3

Ρόλοι: `platform_admin` · `admin` · `secretariat` · `coach` · `staff` · `doctor` · `athlete` · `parent`  
Επιπλέον: `permissions[]` ανά χρήστη · `finance_own_entries_only`.

Legend: **F** = full · **R** = read · **O** = own/limited · **—** = none · **P** = platform only

| Module / Screen | PA | Admin | Secr. | Coach | Staff | Doctor | Athlete | Parent |
|-----------------|----|-------|-------|-------|-------|--------|---------|--------|
| Platform (clubs, packages, waitlist, branding, diagnostics) | F | — | — | — | — | — | — | — |
| Dashboard συλλόγου | R | F | F | R | R | R | — | — |
| **ΜΗΤΡΩΟ** Αθλητές | F | F | F | R/O | R | R | O | O* |
| Γονείς / Parent links | F | F | F | — | — | — | — | O |
| Προπονητές | F | F | F | O | — | — | — | — |
| Προσωπικό | F | F | F | — | O | — | — | — |
| Μέλη | F | F | F | — | — | — | — | — |
| Τμήματα / Sports / Seasons | F | F | F | R | — | — | — | — |
| **ΠΡΟΓΡΑΜΜΑ** Ημερολόγιο | F | F | F | R | R | — | R | R |
| Προπονήσεις | F | F | F | F/O | — | — | R | R |
| Αγώνες | F | F | F | R | — | — | R | R |
| Πρόγραμμα (slots) | F | F | F | R | — | — | R | R |
| Παρουσίες | F | F | F | F | — | — | R | R |
| Progress reports | F | F | F | F | — | — | R | R |
| **ΟΙΚΟΝΟΜΙΚΑ** Συνδρομές/Πληρωμές | F | F | F† | — | — | — | O | O |
| Συναλλαγές | F | F | F† | — | — | — | — | — |
| Οικονομικά (έσοδα/έξοδα/ταμεία) | F | F | F† | — | — | — | — | — |
| Αποθήκη | F | F | F | — | R | — | — | — |
| Ενοικίαση (admin) | F | F | F | — | — | — | — | — |
| **ΕΠΙΚΟΙΝΩΝΙΑ** Ανακοινώσεις | F | F | F | R | R | — | R | R |
| Φωτογραφίες | F | F | F | R | — | — | — | R |
| Εκτυπώσεις / PDF | F | F | F | R | — | — | — | — |
| Συμβεβλημένες επιχειρήσεις | F | F | F | — | — | — | — | R |
| Πρωτόκολλο | F | F | F | — | — | — | — | — |
| Ρυθμίσεις συλλόγου | F | F | R‡ | — | — | — | — | — |
| Backup / Restore | F | F | F§ | — | — | — | — | — |
| GDPR / ΑΜΚΑ audit | F | F | F | — | — | R | DSAR | DSAR |
| Portals `/app/parent` `/app/coach` athlete | — | — | — | Coach portal | — | — | Athlete | Parent |
| Public `/join/{slug}` `/rent/{slug}` | public | public | | | | | | |

\* Parent: μόνο συνδεδεμένοι αθλητές.  
† `finance_own_entries_only` περιορίζει σε δικές του καταχωρήσεις.  
‡ Secretariat: users/SMTP μπορεί να είναι περιορισμένα από permissions.  
§ Restore club JSON: admin + secretariat + PA· platform full: μόνο PA.

## Portals (όχι admin sidebar)

| Portal | Bottom tabs (ενδεικτικά) |
|--------|--------------------------|
| Parent | Αρχική · Πρόγραμμα · Οφειλές · Ανακοινώσεις · Προφίλ |
| Coach | Αρχική · Πρόγραμμα · Παρουσίες · Ανακοινώσεις · Προφίλ |
| Athlete | Αρχική · Πρόγραμμα · Κάρτα · Ανακοινώσεις · Προφίλ |
