/** Greek prefectures (νομοί) in genitive, uppercase — for Νομός dropdowns. */
export const GREEK_PREFECTURES = [
    'ΑΙΤΩΛΟΑΚΑΡΝΑΝΙΑΣ',
    'ΑΡΓΟΛΙΔΑΣ',
    'ΑΡΚΑΔΙΑΣ',
    'ΑΡΤΑΣ',
    'ΑΤΤΙΚΗΣ',
    'ΑΧΑΪΑΣ',
    'ΒΟΙΩΤΙΑΣ',
    'ΓΡΕΒΕΝΩΝ',
    'ΔΡΑΜΑΣ',
    'ΔΩΔΕΚΑΝΗΣΟΥ',
    'ΕΒΡΟΥ',
    'ΕΥΒΟΙΑΣ',
    'ΕΥΡΥΤΑΝΙΑΣ',
    'ΖΑΚΥΝΘΟΥ',
    'ΗΛΕΙΑΣ',
    'ΗΜΑΘΙΑΣ',
    'ΗΡΑΚΛΕΙΟΥ',
    'ΘΕΣΠΡΩΤΙΑΣ',
    'ΘΕΣΣΑΛΟΝΙΚΗΣ',
    'ΙΩΑΝΝΙΝΩΝ',
    'ΚΑΒΑΛΑΣ',
    'ΚΑΡΔΙΤΣΑΣ',
    'ΚΑΣΤΟΡΙΑΣ',
    'ΚΕΡΚΥΡΑΣ',
    'ΚΕΦΑΛΛΗΝΙΑΣ',
    'ΚΙΛΚΙΣ',
    'ΚΟΖΑΝΗΣ',
    'ΚΟΡΙΝΘΙΑΣ',
    'ΚΥΚΛΑΔΩΝ',
    'ΛΑΚΩΝΙΑΣ',
    'ΛΑΡΙΣΑΣ',
    'ΛΑΣΙΘΙΟΥ',
    'ΛΕΣΒΟΥ',
    'ΛΕΥΚΑΔΑΣ',
    'ΜΑΓΝΗΣΙΑΣ',
    'ΜΕΣΣΗΝΙΑΣ',
    'ΞΑΝΘΗΣ',
    'ΠΕΛΛΑΣ',
    'ΠΙΕΡΙΑΣ',
    'ΠΡΕΒΕΖΑΣ',
    'ΡΕΘΥΜΝΟΥ',
    'ΡΟΔΟΠΗΣ',
    'ΣΑΜΟΥ',
    'ΣΕΡΡΩΝ',
    'ΤΡΙΚΑΛΩΝ',
    'ΦΘΙΩΤΙΔΑΣ',
    'ΦΛΩΡΙΝΑΣ',
    'ΦΩΚΙΔΑΣ',
    'ΧΑΛΚΙΔΙΚΗΣ',
    'ΧΑΝΙΩΝ',
    'ΧΙΟΥ',
];

export const GREEK_PREFECTURE_OPTIONS = GREEK_PREFECTURES.map((name) => ({
    value: name,
    label: name,
}));

/** @param {unknown} value */
export function normalizePrefecture(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const raw = String(value).trim();
    if (!raw) {
        return '';
    }

    const upper = raw.toLocaleUpperCase('el-GR');
    const exact = GREEK_PREFECTURES.find((name) => name === upper);
    if (exact) {
        return exact;
    }

    const folded = upper.normalize('NFD').replace(/\p{M}/gu, '');
    const match = GREEK_PREFECTURES.find(
        (name) => name.normalize('NFD').replace(/\p{M}/gu, '') === folded,
    );

    return match || upper;
}
