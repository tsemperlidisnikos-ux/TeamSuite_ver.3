<?php

namespace App\Support;

final class PublicLegalDocuments
{
    /** @return list<array{id: string, label: string}> */
    public static function docs(): array
    {
        return [
            ['id' => 'privacy', 'label' => 'Απόρρητο'],
            ['id' => 'cookies', 'label' => 'Cookies'],
            ['id' => 'breach', 'label' => 'Παραβίαση δεδομένων'],
            ['id' => 'ropa', 'label' => 'RoPA'],
            ['id' => 'payment', 'label' => 'Πληρωμές'],
            ['id' => 'terms', 'label' => 'Όροι'],
        ];
    }

    public static function html(string $doc, ?string $termsHtml = null): string
    {
        return match ($doc) {
            'cookies' => self::cookies(),
            'breach' => self::breach(),
            'ropa' => self::ropa(),
            'payment' => self::payment(),
            'terms' => filled(trim((string) $termsHtml))
                ? (string) $termsHtml
                : '<p>Οι όροι χρήσης ορίζονται από τον σύλλογο στις Ρυθμίσεις.</p>',
            default => self::privacy(),
        };
    }

    public static function resolveDoc(?string $raw): string
    {
        $id = $raw ?: 'privacy';
        $ids = array_column(self::docs(), 'id');

        return in_array($id, $ids, true) ? $id : 'privacy';
    }

    private static function privacy(): string
    {
        return <<<'HTML'
<h1>Πολιτική Απορρήτου — TeamSuite</h1>
<p><em>Τελευταία ενημέρωση: Αύγουστος 2026</em></p>
<p>Η πλατφόρμα TeamSuite παρέχει λογισμικό διαχείρισης αθλητικών συλλόγων. Για τα δεδομένα μελών/αθλητών, <strong>υπεύθυνος επεξεργασίας</strong> είναι ο εκάστοτε σύλλογος. Η πλατφόρμα ενεργεί ως <strong>εκτελών την επεξεργασία</strong> κατ’ εντολή του συλλόγου (DPA).</p>
<h2>1. Κατηγορίες δεδομένων</h2>
<ul>
<li>Ταυτοποίησης και επικοινωνίας (ονοματεπώνυμο, email, τηλέφωνο, διεύθυνση)</li>
<li>Αθλητικά (τμήμα, άθλημα, παρουσίες, προπονήσεις)</li>
<li>Οικονομικά συνδρομών (χρεώσεις, πληρωμές, χωρίς στοιχεία κάρτας)</li>
<li>Ευαίσθητα όπου απαιτείται (ΑΜΚΑ, ιατρικά στοιχεία κάρτας υγείας) με συγκατάθεση και RBAC</li>
<li>Φωτογραφίες/οπτικό υλικό μόνο με σχετική συγκατάθεση</li>
<li>Τεχνικά (συνεδρίες, αρχεία πρόσβασης, συγκατάθεση cookies)</li>
</ul>
<h2>2. Σκοποί &amp; νομικές βάσεις</h2>
<p>Λειτουργία συλλόγου και συμβατική σχέση (άρθρο 6(1)(b) GDPR)· έννομο συμφέρον για ασφάλεια/καταγραφή (6(1)(f))· συγκατάθεση για cookies μη απαραίτητα, επικοινωνίες marketing και φωτογραφίες (6(1)(a))· ειδικές κατηγορίες υγείας όπου εφαρμόζεται άρθρο 9(2)(h) με κατάλληλες εγγυήσεις.</p>
<h2>3. Αποδέκτες / υποεπεξεργαστές</h2>
<p>Hosting/API, αποθήκευση, πάροχος email (SMTP συλλόγου), πάροχος πληρωμών (τα στοιχεία κάρτας δεν αποθηκεύονται στην πλατφόρμα).</p>
<h2>4. Διατήρηση</h2>
<p>Ενεργά μέλη: όσο διαρκεί η σχέση. Ανενεργά: σύμφωνα με την πολιτική του συλλόγου (προεπιλογή 36 μήνες για καθαρισμό ευαίσθητων). Audit logs ΑΜΚΑ: 12 μήνες. Φωτογραφίες χωρίς λόγο διατήρησης: έως 24 μήνες.</p>
<h2>5. Δικαιώματα</h2>
<p>Πρόσβαση, διόρθωση, διαγραφή, περιορισμός, φορητότητα, ανάκληση συγκατάθεσης, καταγγελία στην ΑΠΔΠΧ.</p>
HTML;
    }

    private static function cookies(): string
    {
        return <<<'HTML'
<h1>Πολιτική Cookies — TeamSuite</h1>
<p><em>Τελευταία ενημέρωση: Αύγουστος 2026</em></p>
<ul>
<li><strong>Απαραίτητα:</strong> σύνδεση, ασφάλεια, προτιμήσεις. Δεν απαιτούν συγκατάθεση.</li>
<li><strong>Analytics / Marketing:</strong> μόνο με συγκατάθεση.</li>
</ul>
HTML;
    }

    private static function breach(): string
    {
        return <<<'HTML'
<h1>Σχέδιο Αντιμετώπισης Παραβίασης Δεδομένων</h1>
<ol>
<li>Ανίχνευση και καταγραφή</li>
<li>Περιορισμός πρόσβασης / αλλαγή μυστικών</li>
<li>Αξιολόγηση κινδύνου</li>
<li>Γνωστοποίηση ΑΠΔΠΧ εντός 72 ωρών όπου απαιτείται</li>
<li>Τεκμηρίωση και ανάκαμψη</li>
</ol>
HTML;
    }

    private static function ropa(): string
    {
        return <<<'HTML'
<h1>Αρχείο Δραστηριοτήτων Επεξεργασίας (RoPA)</h1>
<p>Συνοπτικό αρχείο δραστηριοτήτων (άρθρο 30 GDPR) για διαχείριση μελών, κάρτα υγείας/ΑΜΚΑ, συνδρομές, φωτογραφίες, email και cookies.</p>
HTML;
    }

    private static function payment(): string
    {
        return <<<'HTML'
<h1>Ενημέρωση για Δεδομένα Πληρωμών</h1>
<p>Οι πληρωμές με κάρτα διεκπεραιώνονται από πάροχο πληρωμών. Η TeamSuite <strong>δεν αποθηκεύει</strong> αριθμό κάρτας ή CVV. Αποθηκεύονται μόνο κωδικός παραγγελίας, ποσό και κατάσταση.</p>
HTML;
    }
}
