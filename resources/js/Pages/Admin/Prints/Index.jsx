import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/portalFormat';
import { downloadSimpleXlsx } from '@/utils/downloadSimpleXlsx';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const PRINT_TYPES = [
    { id: 'athletes-registry', title: 'Λίστα αθλητών' },
    { id: 'athlete-balances', title: 'Υπόλοιπα αθλητών' },
    { id: 'attendance-sheet', title: 'Παρουσιολόγιο' },
    { id: 'training-attendance', title: 'Παρουσιολόγιο προπόνησης' },
    { id: 'registration-apps', title: 'Αιτήσεις εγγραφής' },
    { id: 'health-expiries', title: 'Λήξεις ιατρικών πιστοποιητικών' },
    { id: 'period-collections', title: 'Εισπράξεις περιόδου' },
    { id: 'debtors', title: 'Οφειλέτες' },
    { id: 'month-subscription', title: 'Συνδρομή μήνα' },
    { id: 'legal-forms', title: 'Νομικά έντυπα' },
    { id: 'progress-report', title: 'Αναφορά προόδου' },
    { id: 'classes-catalog', title: 'Κατάλογος τμημάτων' },
    { id: 'medical-data', title: 'Ιατρικά στοιχεία' },
    { id: 'finance-report', title: 'Οικονομική αναφορά' },
    { id: 'charges-payments', title: 'Χρεώσεις / πληρωμές' },
    { id: 'training-schedule', title: 'Πρόγραμμα προπονήσεων' },
];

const MONTH_OPTIONS = [
    { month: 1, label: 'Ιανουάριος' },
    { month: 2, label: 'Φεβρουάριος' },
    { month: 3, label: 'Μάρτιος' },
    { month: 4, label: 'Απρίλιος' },
    { month: 5, label: 'Μάιος' },
    { month: 6, label: 'Ιούνιος' },
    { month: 7, label: 'Ιούλιος' },
    { month: 8, label: 'Αύγουστος' },
    { month: 9, label: 'Σεπτέμβριος' },
    { month: 10, label: 'Οκτώβριος' },
    { month: 11, label: 'Νοέμβριος' },
    { month: 12, label: 'Δεκέμβριος' },
];

const COMPARE_OPS = ['=', '<', '>', '<=', '>='];
const CLOTHING_PACKAGE_NONE = '__none__';
const PAYMENT_METHODS = [
    { value: 'cash', label: 'Μετρητά' },
    { value: 'transfer', label: 'Κατάθεση' },
    { value: 'card', label: 'POS' },
    { value: 'viva', label: 'VIVA' },
    { value: 'eurobank', label: 'Eurobank' },
    { value: 'stripe', label: 'Stripe' },
];

/** Types that open ResultsModal-style results in a new window. */
const NEW_WINDOW_TYPES = [
    'athletes-registry',
    'month-subscription',
    'athlete-balances',
    'attendance-sheet',
    'debtors',
    'health-expiries',
    'training-attendance',
    'registration-apps',
    'period-collections',
];

function pad2(n) {
    return String(n).padStart(2, '0');
}

function todayIso() {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function seasonStartIso() {
    const d = new Date();
    const year = d.getMonth() + 1 >= 8 ? d.getFullYear() : d.getFullYear() - 1;
    return `${year}-08-01`;
}

function monthStartIso() {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-01`;
}

function monthsAgoIso(months) {
    const d = new Date();
    d.setMonth(d.getMonth() - months);
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function FilterRow({ label, children }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[180px_1fr] sm:items-center">
            <span className="text-xs font-medium text-ts-muted">{label}</span>
            <div>{children}</div>
        </div>
    );
}

function TriState({ value, onChange, yesLabel = 'Ναι' }) {
    return (
        <select
            className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="">Όλα</option>
            <option value="yes">{yesLabel}</option>
            <option value="no">Όχι</option>
        </select>
    );
}

function OpNumber({ op, value, onOp, onValue, placeholder, min, step, ops = COMPARE_OPS }) {
    return (
        <div className="flex gap-2">
            <select
                className="w-auto rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                value={op}
                onChange={(e) => onOp(e.target.value)}
                aria-label="Συντελεστής"
            >
                {ops.map((o) => (
                    <option key={o} value={o}>
                        {o}
                    </option>
                ))}
            </select>
            <input
                type="number"
                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                value={value}
                min={min}
                step={step}
                placeholder={placeholder}
                onChange={(e) => onValue(e.target.value)}
            />
        </div>
    );
}

function matchTri(value, flag) {
    if (value === 'yes') return !!flag;
    if (value === 'no') return !flag;
    return true;
}

function compareNumber(value, op, target) {
    const t = Number(target);
    if (target === '' || target == null || Number.isNaN(t)) return true;
    if (value == null || Number.isNaN(Number(value))) return false;
    const v = Number(value);
    switch (op) {
        case '<':
            return v < t;
        case '>':
            return v > t;
        case '<=':
            return v <= t;
        case '>=':
            return v >= t;
        default:
            return v === t;
    }
}

function athleteBalance(a) {
    if (a.balance != null && a.balance !== '') return Number(a.balance);
    return Number(a.month_remaining ?? 0);
}

function athletePresentCount(a) {
    if (a.attendance_present_count != null) return Number(a.attendance_present_count);
    return a.has_attendance ? 1 : 0;
}

function athleteAttendanceTotal(a) {
    if (a.attendance_total != null) return Number(a.attendance_total);
    const present = athletePresentCount(a);
    const absent =
        a.attendance_absent_count != null ? Number(a.attendance_absent_count) : 0;
    return present + absent;
}

function athleteHasRegistrationCard(a) {
    return String(a.registration_number || '').trim().length > 0;
}

function readQuery(url) {
    const raw = url.includes('?') ? url.split('?')[1] : '';
    return new URLSearchParams(raw);
}

function typeDescription(selected) {
    switch (selected) {
        case 'athletes-registry':
            return 'Φίλτρα αναζήτησης και εκτύπωση στο προφίλ. Η αναζήτηση ανοίγει τα αποτελέσματα σε νέο παράθυρο.';
        case 'athlete-balances':
            return 'Εκκρεμείς χρεώσεις ανά αθλητή. Φίλτρα ανά άθλημα, τμήμα, φύλο, ενεργότητα, ποσό υπολοίπου και σωματείο.';
        case 'attendance-sheet':
            return 'Παρουσίες και απουσίες ανά αθλητή για επιλεγμένη περίοδο.';
        case 'training-attendance':
            return 'Φύλλο παρουσίας για επιλεγμένη προπόνηση ή λίστα τμήματος.';
        case 'registration-apps':
            return 'Εκτύπωση εκκρεμών, δοκιμαστικών και λίστας αναμονής.';
        case 'health-expiries':
            return 'Αθλητές με ληγμένη ή προσεχώς ληγμένη ιατρική βεβαίωση.';
        case 'period-collections':
            return 'Χρεώσεις και πληρωμές για επιλεγμένη περίοδο.';
        case 'debtors':
            return 'Αθλητές με εκκρεμείς χρεώσεις και στοιχεία επικοινωνίας.';
        case 'month-subscription':
            return 'Ποιοι έχουν πληρώσει τη μηνιαία συνδρομή συγκεκριμένου μήνα και ποιοι όχι. Η αναζήτηση ανοίγει τα αποτελέσματα σε νέο παράθυρο.';
        case 'legal-forms':
            return 'Εκτύπωση φόρμας συναίνεσης GDPR και φόρμας εγγραφής αθλητή.';
        case 'progress-report':
            return 'Εκτύπωση αναφοράς προόδου αθλητή για επιλεγμένη περίοδο.';
        case 'classes-catalog':
            return 'Όνομα και κατηγορία κάθε τμήματος. Φίλτρο ανά άθλημα.';
        case 'medical-data':
            return 'Πιστοποιητικά, τραυματισμοί και σημειώσεις.';
        case 'finance-report':
            return 'Μηνιαία έσοδα, έξοδα και υπόλοιπο.';
        case 'charges-payments':
            return 'Κατάσταση χρεώσεων και πληρωμών αθλητών.';
        case 'training-schedule':
            return 'Ημερομηνία, ώρα, τοποθεσία και τμήμα.';
        default:
            return 'Φίλτρα · Αναζήτηση · Εξαγωγή XLSX · Εκτύπωση';
    }
}

export default function Index({
    athletes = [],
    classes = [],
    sports = [],
    associations = [],
    clothingPackages = [],
    sizeOptions = [],
    customChargeLabel = '',
    trainings = [],
    registrationApplications = [],
    periodTransactions = [],
    filters = {},
    yearOptions = [],
}) {
    const page = usePage();
    const query = useMemo(() => readQuery(page.url), [page.url]);
    const isResultsView = query.get('view') === 'results';
    const inputCls = 'w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm';

    const [selected, setSelected] = useState(
        () => query.get('type') || 'athletes-registry',
    );

    // Shared athlete filters
    const [sport, setSport] = useState(() => query.get('sport') || '');
    const [teamId, setTeamId] = useState(() => query.get('team_id') || '');
    const [gender, setGender] = useState(() => query.get('gender') || '');
    const [active, setActive] = useState(() => {
        if (query.has('active')) return query.get('active') || '';
        const type = query.get('type') || 'athletes-registry';
        if (type === 'athletes-registry') return '';
        if (type === 'health-expiries' || type === 'debtors' || type === 'month-subscription') {
            return 'yes';
        }
        return '';
    });
    const [monthlyCharge, setMonthlyCharge] = useState(
        () => query.get('monthly_charge') || '',
    );
    const [clubName, setClubName] = useState(() => query.get('club_name') || '');
    const [dateFrom, setDateFrom] = useState(() => {
        if (query.get('date_from')) return query.get('date_from');
        if (filters.date_from) return filters.date_from;
        const type = query.get('type') || 'athletes-registry';
        if (type === 'attendance-sheet') return seasonStartIso();
        if (type === 'period-collections') return monthStartIso();
        if (type === 'progress-report') return monthsAgoIso(3);
        return '';
    });
    const [dateTo, setDateTo] = useState(() => {
        if (query.get('date_to')) return query.get('date_to');
        if (filters.date_to) return filters.date_to;
        return todayIso();
    });

    // Registry-only
    const [registrationFee, setRegistrationFee] = useState(
        () => query.get('registration_charge') || '',
    );
    const [hasPhoto, setHasPhoto] = useState(() => query.get('has_photo') || '');
    const [healthCard, setHealthCard] = useState(() => query.get('health_card') || '');
    const [seasonTicket, setSeasonTicket] = useState(
        () => query.get('season_ticket') || '',
    );
    const [customCharge, setCustomCharge] = useState(
        () => query.get('custom_charge') || '',
    );
    const [clothingPackageId, setClothingPackageId] = useState(
        () => query.get('clothing_package_id') || '',
    );
    const [hasRegistrationCard, setHasRegistrationCard] = useState(
        () => query.get('has_registration_card') || '',
    );
    const [trainingPresence, setTrainingPresence] = useState(
        () => query.get('training_presence') || '',
    );
    const [trainingPresenceOp, setTrainingPresenceOp] = useState(
        () => query.get('training_presence_op') || '>=',
    );
    const [uniformReceived, setUniformReceived] = useState(
        () => query.get('uniform_received') || '',
    );
    const [uniformSize, setUniformSize] = useState(
        () => query.get('uniform_size') || '',
    );
    const [birthYearOp, setBirthYearOp] = useState(
        () => query.get('birth_year_op') || '=',
    );
    const [birthYear, setBirthYear] = useState(() => query.get('birth_year') || '');

    // Balances
    const [hasBalance, setHasBalance] = useState(
        () => query.get('has_balance') || 'yes',
    );
    const [balanceOp, setBalanceOp] = useState(() => query.get('balance_op') || '>=');
    const [balanceAmount, setBalanceAmount] = useState(
        () => query.get('balance_amount') || '',
    );

    // Attendance sheet
    const [hasAttendance, setHasAttendance] = useState(
        () => query.get('has_attendance') || '',
    );
    const [presenceOp, setPresenceOp] = useState(
        () => query.get('presence_op') || '>=',
    );
    const [presenceCount, setPresenceCount] = useState(
        () => query.get('presence_count') || '',
    );
    const [rateOp, setRateOp] = useState(() => query.get('rate_op') || '>=');
    const [rateValue, setRateValue] = useState(() => query.get('rate_value') || '');

    // Training attendance sheet
    const [trainingDate, setTrainingDate] = useState(
        () => query.get('training_date') || todayIso(),
    );
    const [trainingId, setTrainingId] = useState(
        () => query.get('training_id') || '',
    );

    // Registration apps
    const [appCategory, setAppCategory] = useState(
        () => query.get('app_category') || 'pending',
    );
    const [appType, setAppType] = useState(() => query.get('app_type') || '');

    // Health expiries
    const [refDate, setRefDate] = useState(
        () => query.get('ref_date') || todayIso(),
    );
    const [healthWindow, setHealthWindow] = useState(
        () => query.get('health_window') || '30',
    );

    // Period collections
    const [amountMin, setAmountMin] = useState(() => query.get('amount_min') || '');
    const [amountMax, setAmountMax] = useState(() => query.get('amount_max') || '');
    const [paymentMethod, setPaymentMethod] = useState(
        () => query.get('payment_method') || '',
    );
    const [entryType, setEntryType] = useState(() => query.get('entry_type') || '');
    const [athleteQuery, setAthleteQuery] = useState(
        () => query.get('athlete_q') || '',
    );

    // Debtors
    const [minBalance, setMinBalance] = useState(
        () => query.get('min_balance') || '',
    );

    // Month subscription
    const [month, setMonth] = useState(
        () => Number(query.get('month') || filters.month || new Date().getMonth() + 1),
    );
    const [year, setYear] = useState(
        () => Number(query.get('year') || filters.year || new Date().getFullYear()),
    );
    const [payStatus, setPayStatus] = useState(() => query.get('pay_status') || '');

    // Legal / progress / charges
    const [legalMode, setLegalMode] = useState(
        () => query.get('legal_mode') || 'gdpr',
    );
    const [athleteId, setAthleteId] = useState(() => query.get('athlete_id') || '');
    const [registrationAppId, setRegistrationAppId] = useState(
        () => query.get('registration_app_id') || '',
    );
    const [paymentStatusFilter, setPaymentStatusFilter] = useState(
        () => query.get('payment_status') || '',
    );

    const [showResults, setShowResults] = useState(
        () => isResultsView && !NEW_WINDOW_TYPES.includes(query.get('type') || ''),
    );

    const current = PRINT_TYPES.find((t) => t.id === selected) || PRINT_TYPES[0];
    const opensResultsInNewWindow = NEW_WINDOW_TYPES.includes(selected);
    const customChargeYesLabel = (customChargeLabel || '').trim() || 'Ναι';

    const sportOptions = useMemo(() => {
        if (sports?.length) return sports;
        return [...new Set(athletes.map((a) => a.sport).filter(Boolean))].sort((a, b) =>
            a.localeCompare(b, 'el'),
        );
    }, [sports, athletes]);

    const teamOptions = useMemo(() => {
        if (!sport) return classes;
        return classes.filter((c) => (c.sport || '') === sport);
    }, [classes, sport]);

    const dayTrainings = useMemo(() => {
        if (!teamId || !Array.isArray(trainings)) return [];
        return trainings.filter(
            (t) =>
                (t.class_id || t.classId) === teamId &&
                (t.date || '') === trainingDate,
        );
    }, [trainings, teamId, trainingDate]);

    const athleteOptions = useMemo(() => {
        return [...athletes]
            .filter((a) => !sport || a.sport === sport)
            .sort((a, b) =>
                `${a.last_name} ${a.first_name}`.localeCompare(
                    `${b.last_name} ${b.first_name}`,
                    'el',
                ),
            );
    }, [athletes, sport]);

    const filteredClasses = useMemo(() => {
        if (selected !== 'classes-catalog') return [];
        return (classes || []).filter((c) => !sport || (c.sport || '') === sport);
    }, [classes, sport, selected]);

    const filteredTrainings = useMemo(() => {
        if (selected !== 'training-schedule') return [];
        return (trainings || []).filter((t) => {
            const classId = t.class_id || t.classId || '';
            if (teamId && classId !== teamId) return false;
            if (!sport) return true;
            const cls = classes.find((c) => c.id === classId);
            return (cls?.sport || '') === sport;
        });
    }, [trainings, teamId, sport, classes, selected]);

    const filteredApps = useMemo(() => {
        if (selected !== 'registration-apps') return [];
        return (registrationApplications || []).filter((app) => {
            const day = String(app.created_at || app.createdAt || '').slice(0, 10);
            if (dateFrom && day && day < dateFrom) return false;
            if (dateTo && day && day > dateTo) return false;
            const classId = app.class_id || app.classId || '';
            if (teamId && classId !== teamId) return false;
            if (sport && classId) {
                const cls = classes.find((c) => c.id === classId);
                if ((cls?.sport || '') !== sport) return false;
            }
            const kind = app.kind || app.type || '';
            const status = app.status || '';
            if (appType && kind !== appType) return false;
            if (appCategory === 'pending' && status !== 'pending') return false;
            if (appCategory === 'trial' && kind !== 'trial') return false;
            if (appCategory === 'waitlist' && kind !== 'waitlist') return false;
            return true;
        });
    }, [
        registrationApplications,
        selected,
        dateFrom,
        dateTo,
        teamId,
        sport,
        classes,
        appType,
        appCategory,
    ]);

    const athleteById = useMemo(() => {
        const map = new Map();
        for (const a of athletes || []) {
            map.set(a.id, a);
        }
        return map;
    }, [athletes]);

    const filteredPeriodTransactions = useMemo(() => {
        if (selected !== 'period-collections') return [];
        const needle = athleteQuery.trim().toLowerCase();
        const min = amountMin === '' ? null : Number(amountMin);
        const max = amountMax === '' ? null : Number(amountMax);
        return (periodTransactions || []).filter((tx) => {
            const day = String(tx.created_at || '').slice(0, 10);
            if (dateFrom && day && day < dateFrom) return false;
            if (dateTo && day && day > dateTo) return false;
            if (entryType && tx.type !== entryType) return false;
            if (paymentMethod && (tx.payment_method || '') !== paymentMethod) return false;
            const amount = Number(tx.amount || 0);
            if (min != null && !Number.isNaN(min) && amount < min) return false;
            if (max != null && !Number.isNaN(max) && amount > max) return false;
            const athlete = athleteById.get(tx.athlete_id);
            if (sport && athlete && athlete.sport !== sport) return false;
            if (teamId && athlete && !(athlete.class_ids || []).includes(teamId)) return false;
            if (needle) {
                const name = athlete
                    ? `${athlete.last_name} ${athlete.first_name}`.toLowerCase()
                    : '';
                if (!name.includes(needle)) return false;
            }
            return true;
        });
    }, [
        selected,
        periodTransactions,
        athleteQuery,
        amountMin,
        amountMax,
        dateFrom,
        dateTo,
        entryType,
        paymentMethod,
        athleteById,
        sport,
        teamId,
    ]);

    const filtered = useMemo(() => {
        return (athletes || []).filter((a) => {
            if (sport && a.sport !== sport) return false;
            if (teamId && !(a.class_ids || []).includes(teamId)) return false;
            if (gender && a.gender !== gender) return false;
            if (active === 'yes' && a.status !== 'active') return false;
            if (active === 'no' && a.status === 'active') return false;
            if (!matchTri(monthlyCharge, a.monthly_charge !== false)) return false;
            if (clubName && (a.club_name || '') !== clubName) return false;

            if (selected === 'athletes-registry') {
                if (!matchTri(registrationFee, a.registration_charge)) return false;
                if (!matchTri(hasPhoto, a.has_photo)) return false;
                if (!matchTri(healthCard, a.health_card)) return false;
                if (!matchTri(seasonTicket, a.season_ticket)) return false;
                if (!matchTri(customCharge, a.custom_charge)) return false;
                if (clothingPackageId === CLOTHING_PACKAGE_NONE) {
                    if ((a.clothing_package_ids || []).length > 0) return false;
                } else if (
                    clothingPackageId &&
                    !(a.clothing_package_ids || []).includes(clothingPackageId)
                ) {
                    return false;
                }
                if (!matchTri(hasRegistrationCard, athleteHasRegistrationCard(a))) {
                    return false;
                }
                if (
                    trainingPresence &&
                    !compareNumber(
                        athletePresentCount(a),
                        trainingPresenceOp,
                        trainingPresence,
                    )
                ) {
                    return false;
                }
                if (!matchTri(uniformReceived, a.uniform_received)) return false;
                if (uniformSize && (a.uniform_size || '') !== uniformSize) return false;
                if (dateFrom && a.enrolled_at && a.enrolled_at < dateFrom) return false;
                if (dateTo && a.enrolled_at && a.enrolled_at > dateTo) return false;
                if ((dateFrom || dateTo) && !a.enrolled_at) return false;
                if (birthYear) {
                    if (!compareNumber(a.birth_year, birthYearOp, birthYear)) return false;
                }
            }

            if (selected === 'athlete-balances') {
                const bal = athleteBalance(a);
                if (hasBalance === 'yes' && bal <= 0) return false;
                if (hasBalance === 'no' && bal > 0) return false;
                if (balanceAmount && !compareNumber(bal, balanceOp, balanceAmount)) {
                    return false;
                }
            }

            if (selected === 'attendance-sheet') {
                const present = athletePresentCount(a);
                const total = athleteAttendanceTotal(a);
                const rate = total ? (present / total) * 100 : 0;
                if (hasAttendance === 'yes' && present <= 0) return false;
                if (hasAttendance === 'no' && present > 0) return false;
                if (
                    presenceCount &&
                    !compareNumber(present, presenceOp, presenceCount)
                ) {
                    return false;
                }
                if (rateValue && !compareNumber(rate, rateOp, rateValue)) return false;
            }

            if (selected === 'training-attendance') {
                if (!teamId) return false;
                if (!(a.class_ids || []).includes(teamId)) return false;
            }

            if (selected === 'health-expiries') {
                const expires = String(a.health_card_expires || '').slice(0, 10);
                const ref = String(refDate || todayIso()).slice(0, 10);
                if (healthWindow === 'all') {
                    // keep
                } else if (healthWindow === 'none') {
                    if (a.health_card || expires) return false;
                } else if (!expires) {
                    return false;
                } else if (healthWindow === 'expired') {
                    if (expires >= ref) return false;
                } else {
                    const days = Number(healthWindow);
                    if (!Number.isFinite(days)) return false;
                    const refMs = Date.parse(`${ref}T12:00:00`);
                    const expMs = Date.parse(`${expires}T12:00:00`);
                    if (Number.isNaN(refMs) || Number.isNaN(expMs)) return false;
                    const diffDays = Math.ceil((expMs - refMs) / 86400000);
                    if (diffDays < 0 || diffDays > days) return false;
                }
            }

            if (selected === 'debtors') {
                const bal = athleteBalance(a);
                if (bal <= 0) return false;
                if (minBalance && bal < Number(minBalance)) return false;
            }

            if (selected === 'month-subscription') {
                const expected = a.status !== 'inactive' && a.monthly_charge !== false;
                if (a.month_pay_status === 'no_charge' && !expected) return false;
                if (payStatus && a.month_pay_status !== payStatus) return false;
            }

            if (selected === 'medical-data') {
                // sport + team already applied
            }

            if (selected === 'progress-report' || selected === 'legal-forms') {
                if (athleteId && a.id !== athleteId) return false;
            }

            if (selected === 'charges-payments') {
                if (paymentStatusFilter === 'paid' && a.month_pay_status !== 'paid') {
                    return false;
                }
                if (
                    paymentStatusFilter === 'pending' &&
                    a.month_pay_status === 'paid'
                ) {
                    return false;
                }
            }

            return true;
        });
    }, [
        athletes,
        sport,
        teamId,
        gender,
        active,
        monthlyCharge,
        clubName,
        selected,
        registrationFee,
        hasPhoto,
        healthCard,
        seasonTicket,
        customCharge,
        clothingPackageId,
        hasRegistrationCard,
        trainingPresence,
        trainingPresenceOp,
        uniformReceived,
        uniformSize,
        dateFrom,
        dateTo,
        birthYear,
        birthYearOp,
        hasBalance,
        balanceOp,
        balanceAmount,
        hasAttendance,
        presenceOp,
        presenceCount,
        rateOp,
        rateValue,
        refDate,
        healthWindow,
        minBalance,
        payStatus,
        athleteId,
        paymentStatusFilter,
    ]);

    const monthSummary = useMemo(() => {
        if (selected !== 'month-subscription') return '';
        let paid = 0;
        let unpaid = 0;
        let noCharge = 0;
        let owed = 0;
        filtered.forEach((a) => {
            if (a.month_pay_status === 'paid') paid += 1;
            else if (a.month_pay_status === 'unpaid') {
                unpaid += 1;
                owed += Number(a.month_remaining || 0);
            } else noCharge += 1;
        });
        const label = MONTH_OPTIONS.find((m) => m.month === Number(month))?.label || month;
        return `${label} ${year} · Πληρωμένες: ${paid} · Ανεξόφλητες: ${unpaid} · Χωρίς χρέωση: ${noCharge}${
            unpaid > 0 ? ` · Οφειλή μήνα: ${formatCurrency(owed)}` : ''
        }`;
    }, [filtered, selected, month, year]);

    const monthTotals = useMemo(() => {
        if (selected !== 'month-subscription') return null;
        return filtered.reduce(
            (acc, a) => ({
                charged: acc.charged + Number(a.month_charged || 0),
                paid: acc.paid + Number(a.month_paid || 0),
                remaining: acc.remaining + Number(a.month_remaining || 0),
            }),
            { charged: 0, paid: 0, remaining: 0 },
        );
    }, [filtered, selected]);

    const exportXlsx = () => {
        if (selected === 'classes-catalog') {
            downloadSimpleXlsx({
                headers: ['#', 'Όνομα', 'Άθλημα'],
                rows: filteredClasses.map((c, i) => [i + 1, c.name || '', c.sport || '']),
                sheetName: current.title,
                filename: `${current.id}.xlsx`,
            });
            return;
        }
        if (selected === 'training-schedule') {
            downloadSimpleXlsx({
                headers: ['#', 'Ημερομηνία', 'Ώρα', 'Τοποθεσία', 'Τμήμα'],
                rows: filteredTrainings.map((t, i) => {
                    const classId = t.class_id || t.classId || '';
                    const cls = classes.find((c) => c.id === classId);
                    return [
                        i + 1,
                        t.date || '',
                        `${t.start_time || t.startTime || ''}–${t.end_time || t.endTime || ''}`,
                        t.location || '',
                        cls?.name || '',
                    ];
                }),
                sheetName: current.title,
                filename: `${current.id}.xlsx`,
            });
            return;
        }
        if (selected === 'registration-apps') {
            downloadSimpleXlsx({
                headers: ['#', 'Όνομα', 'Γονέας', 'Τηλέφωνο', 'Τμήμα', 'Τύπος', 'Κατάσταση', 'Ημ/νία'],
                rows: filteredApps.map((app, i) => {
                    const classId = app.class_id || app.classId || '';
                    const cls = classes.find((c) => c.id === classId);
                    return [
                        i + 1,
                        `${app.last_name || app.lastName || ''} ${app.first_name || app.firstName || ''}`.trim(),
                        app.guardian_name || app.guardianName || '',
                        app.guardian_phone || app.guardianPhone || '',
                        cls?.name || '',
                        app.kind || app.type || '',
                        app.status || '',
                        String(app.created_at || app.createdAt || '').slice(0, 10),
                    ];
                }),
                sheetName: current.title,
                filename: `${current.id}.xlsx`,
            });
            return;
        }

        const isMonth = selected === 'month-subscription';
        const isBalances = selected === 'athlete-balances' || selected === 'debtors';
        const isAttendance = selected === 'attendance-sheet';
        let headers = ['Επώνυμο', 'Όνομα', 'Άθλημα', 'Τμήμα'];
        if (isMonth) {
            headers = [
                'Επώνυμο',
                'Όνομα',
                'Τμήμα',
                'Email πατρός',
                'Email μητρός',
                'Κατάσταση',
                'Χρέωση',
                'Πληρωμή',
                'Υπόλοιπο',
                'Τηλ. γονέα',
            ];
        } else if (isBalances) {
            headers = ['Επώνυμο', 'Όνομα', 'Τμήμα', 'Υπόλοιπο'];
            if (selected === 'debtors') {
                headers = ['Επώνυμο', 'Όνομα', 'Τηλέφωνο', 'Τηλ. γονέα', 'Υπόλοιπο'];
            }
        } else if (isAttendance) {
            headers = [
                'Επώνυμο',
                'Όνομα',
                'Προπονήσεις',
                'Παρουσίες',
                'Απουσίες',
                'Ποσοστό',
            ];
        }

        const rows = filtered.map((a) => {
            if (isMonth) {
                return [
                    a.last_name || '',
                    a.first_name || '',
                    a.class_names || '',
                    a.father_email || '',
                    a.mother_email || '',
                    a.month_pay_status_label || '',
                    a.month_charged ?? '',
                    a.month_paid ?? '',
                    a.month_remaining ?? '',
                    a.guardian_phone || a.phone || '',
                ];
            }
            if (selected === 'debtors') {
                return [
                    a.last_name || '',
                    a.first_name || '',
                    a.phone || '',
                    a.guardian_phone || '',
                    athleteBalance(a),
                ];
            }
            if (isBalances) {
                return [
                    a.last_name || '',
                    a.first_name || '',
                    a.class_names || '',
                    athleteBalance(a),
                ];
            }
            if (isAttendance) {
                const present = athletePresentCount(a);
                const total = athleteAttendanceTotal(a);
                const absent =
                    a.attendance_absent_count != null
                        ? Number(a.attendance_absent_count)
                        : Math.max(0, total - present);
                const rate = total ? Math.round((present / total) * 100) : 0;
                return [
                    a.last_name || '',
                    a.first_name || '',
                    total,
                    present,
                    absent,
                    `${rate}%`,
                ];
            }
            return [a.last_name || '', a.first_name || '', a.sport || '', a.class_names || ''];
        });

        downloadSimpleXlsx({
            headers,
            rows,
            sheetName: current?.title || 'Εκτύπωση',
            filename: `${current?.id || 'export'}.xlsx`,
        });
    };

    const openResultsWindow = (params) => {
        window.open(
            `${route('admin.prints.index')}?${params.toString()}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const buildCommonParams = () => {
        const params = new URLSearchParams({
            type: selected,
            view: 'results',
            sport,
            team_id: teamId,
            gender,
            active,
            monthly_charge: monthlyCharge,
            club_name: clubName,
            date_from: dateFrom,
            date_to: dateTo,
        });
        return params;
    };

    const openTypeResults = () => {
        const params = buildCommonParams();

        if (selected === 'month-subscription') {
            params.set('month', String(month));
            params.set('year', String(year));
            params.set('pay_status', payStatus);
        }
        if (selected === 'athletes-registry') {
            params.set('registration_charge', registrationFee);
            params.set('has_photo', hasPhoto);
            params.set('health_card', healthCard);
            params.set('season_ticket', seasonTicket);
            params.set('custom_charge', customCharge);
            params.set('clothing_package_id', clothingPackageId);
            params.set('has_registration_card', hasRegistrationCard);
            params.set('training_presence', trainingPresence);
            params.set('training_presence_op', trainingPresenceOp);
            params.set('uniform_received', uniformReceived);
            params.set('uniform_size', uniformSize);
            params.set('birth_year_op', birthYearOp);
            params.set('birth_year', birthYear);
        }
        if (selected === 'athlete-balances') {
            params.set('has_balance', hasBalance);
            params.set('balance_op', balanceOp);
            params.set('balance_amount', balanceAmount);
        }
        if (selected === 'attendance-sheet') {
            params.set('has_attendance', hasAttendance);
            params.set('presence_op', presenceOp);
            params.set('presence_count', presenceCount);
            params.set('rate_op', rateOp);
            params.set('rate_value', rateValue);
        }
        if (selected === 'debtors') {
            params.set('min_balance', minBalance);
        }
        if (selected === 'health-expiries') {
            params.set('ref_date', refDate);
            params.set('health_window', healthWindow);
        }
        if (selected === 'training-attendance') {
            params.set('training_date', trainingDate);
            params.set('training_id', trainingId);
        }
        if (selected === 'registration-apps') {
            params.set('app_category', appCategory);
            params.set('app_type', appType);
        }
        if (selected === 'period-collections') {
            params.set('amount_min', amountMin);
            params.set('amount_max', amountMax);
            params.set('payment_method', paymentMethod);
            params.set('entry_type', entryType);
            params.set('athlete_q', athleteQuery);
        }

        openResultsWindow(params);
    };

    const runSearch = () => {
        if (selected === 'training-attendance' && !teamId) return;
        if (selected === 'legal-forms') {
            window.print();
            return;
        }
        if (opensResultsInNewWindow) {
            openTypeResults();
            return;
        }
        setShowResults(true);
    };

    const showInlineResults =
        !opensResultsInNewWindow &&
        (showResults || selected === 'finance-report');

    const resultsTitleFor = (type) => {
        const title = PRINT_TYPES.find((t) => t.id === type)?.title || 'Εκτύπωση';
        return `${title} — Αποτελέσματα`;
    };

    const renderAthleteRows = () => {
        const isMonth = selected === 'month-subscription';
        const isBalances = selected === 'athlete-balances';
        const isDebtors = selected === 'debtors';
        const isAttendance = selected === 'attendance-sheet';
        const isHealth = selected === 'health-expiries';
        const isTrainingAtt = selected === 'training-attendance';

        let colSpan = 5;
        if (isMonth) colSpan = 11;
        else if (isBalances) colSpan = 5;
        else if (isDebtors) colSpan = 6;
        else if (isAttendance) colSpan = 7;
        else if (isHealth) colSpan = 5;
        else if (isTrainingAtt) colSpan = 7;

        return (
            <div className="overflow-x-auto">
                {isMonth && monthSummary ? (
                    <p className="mb-3 text-sm text-ts-muted">{monthSummary}</p>
                ) : null}
                <table className="min-w-full text-sm print:text-black">
                    <thead>
                        <tr className="text-left text-xs uppercase text-ts-muted">
                            <th className="py-1 pe-3">#</th>
                            {isMonth || isBalances || isDebtors || isAttendance || isHealth || isTrainingAtt ? (
                                <>
                                    <th className="py-1 pe-3">Επώνυμο</th>
                                    <th className="py-1 pe-3">Όνομα</th>
                                </>
                            ) : (
                                <>
                                    <th className="py-1 pe-3">Επώνυμο</th>
                                    <th className="py-1 pe-3">Όνομα</th>
                                    <th className="py-1 pe-3">Άθλημα</th>
                                    <th className="py-1">Τμήμα</th>
                                </>
                            )}
                            {isMonth ? (
                                <>
                                    <th className="py-1 pe-3">Τμήμα</th>
                                    <th className="py-1 pe-3">Email πατρός</th>
                                    <th className="py-1 pe-3">Email μητρός</th>
                                    <th className="py-1 pe-3">Κατάσταση</th>
                                    <th className="py-1 pe-3">Χρέωση</th>
                                    <th className="py-1 pe-3">Πληρωμή</th>
                                    <th className="py-1 pe-3">Υπόλοιπο μήνα</th>
                                    <th className="py-1">Τηλ. γονέα</th>
                                </>
                            ) : null}
                            {isBalances ? (
                                <>
                                    <th className="py-1 pe-3">Τμήμα</th>
                                    <th className="py-1">Υπόλοιπο</th>
                                </>
                            ) : null}
                            {isDebtors ? (
                                <>
                                    <th className="py-1 pe-3">Τηλέφωνο</th>
                                    <th className="py-1 pe-3">Τηλ. γονέα</th>
                                    <th className="py-1">Υπόλοιπο</th>
                                </>
                            ) : null}
                            {isAttendance ? (
                                <>
                                    <th className="py-1 pe-3">Προπονήσεις</th>
                                    <th className="py-1 pe-3">Παρουσίες</th>
                                    <th className="py-1 pe-3">Απουσίες</th>
                                    <th className="py-1">Ποσοστό</th>
                                </>
                            ) : null}
                            {isHealth ? (
                                <>
                                    <th className="py-1 pe-3">Τμήμα</th>
                                    <th className="py-1">Κατάσταση</th>
                                </>
                            ) : null}
                            {isTrainingAtt ? (
                                <>
                                    <th className="py-1 pe-3">Παρών</th>
                                    <th className="py-1 pe-3">Απών</th>
                                    <th className="py-1 pe-3">Αργία</th>
                                    <th className="py-1">Αιτιολόγηση</th>
                                </>
                            ) : null}
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((a, index) => {
                            const present = athletePresentCount(a);
                            const total = athleteAttendanceTotal(a);
                            const absent =
                                a.attendance_absent_count != null
                                    ? Number(a.attendance_absent_count)
                                    : Math.max(0, total - present);
                            const rate = total ? Math.round((present / total) * 100) : 0;
                            return (
                                <tr key={a.id} className="border-t border-ts-border">
                                    <td className="py-1.5 pe-3">{index + 1}</td>
                                    <td className="py-1.5 pe-3">
                                        <a
                                            href={route('admin.athletes.show', a.id)}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-medium text-ts-accent hover:underline"
                                        >
                                            {a.last_name}
                                        </a>
                                    </td>
                                    <td className="py-1.5 pe-3">
                                        <a
                                            href={route('admin.athletes.show', a.id)}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-medium text-ts-accent hover:underline"
                                        >
                                            {a.first_name}
                                        </a>
                                    </td>
                                    {isMonth ? (
                                        <>
                                            <td className="py-1.5 pe-3">{a.class_names || '—'}</td>
                                            <td className="py-1.5 pe-3">{a.father_email || '—'}</td>
                                            <td className="py-1.5 pe-3">{a.mother_email || '—'}</td>
                                            <td className="py-1.5 pe-3">{a.month_pay_status_label}</td>
                                            <td className="py-1.5 pe-3">
                                                {formatCurrency(a.month_charged || 0)}
                                            </td>
                                            <td className="py-1.5 pe-3">
                                                {formatCurrency(a.month_paid || 0)}
                                            </td>
                                            <td className="py-1.5 pe-3">
                                                {formatCurrency(a.month_remaining || 0)}
                                            </td>
                                            <td className="py-1.5">
                                                {a.guardian_phone || a.phone || ''}
                                            </td>
                                        </>
                                    ) : null}
                                    {isBalances ? (
                                        <>
                                            <td className="py-1.5 pe-3">{a.class_names || '—'}</td>
                                            <td className="py-1.5">
                                                {formatCurrency(athleteBalance(a))}
                                            </td>
                                        </>
                                    ) : null}
                                    {isDebtors ? (
                                        <>
                                            <td className="py-1.5 pe-3">{a.phone || ''}</td>
                                            <td className="py-1.5 pe-3">{a.guardian_phone || ''}</td>
                                            <td className="py-1.5">
                                                {formatCurrency(athleteBalance(a))}
                                            </td>
                                        </>
                                    ) : null}
                                    {isAttendance ? (
                                        <>
                                            <td className="py-1.5 pe-3">{total}</td>
                                            <td className="py-1.5 pe-3">{present}</td>
                                            <td className="py-1.5 pe-3">{absent}</td>
                                            <td className="py-1.5">{rate}%</td>
                                        </>
                                    ) : null}
                                    {isHealth ? (
                                        <>
                                            <td className="py-1.5 pe-3">{a.class_names || '—'}</td>
                                            <td className="py-1.5">
                                                {a.health_card ? 'Ναι' : 'Όχι'}
                                            </td>
                                        </>
                                    ) : null}
                                    {isTrainingAtt ? (
                                        <>
                                            <td className="py-1.5 pe-3" />
                                            <td className="py-1.5 pe-3" />
                                            <td className="py-1.5 pe-3" />
                                            <td className="py-1.5" />
                                        </>
                                    ) : null}
                                    {!isMonth &&
                                    !isBalances &&
                                    !isDebtors &&
                                    !isAttendance &&
                                    !isHealth &&
                                    !isTrainingAtt ? (
                                        <>
                                            <td className="py-1.5 pe-3">{a.sport || '—'}</td>
                                            <td className="py-1.5">{a.class_names || '—'}</td>
                                        </>
                                    ) : null}
                                </tr>
                            );
                        })}
                        {!filtered.length && (
                            <tr>
                                <td
                                    colSpan={colSpan}
                                    className="py-8 text-center text-ts-muted"
                                >
                                    Δεν βρέθηκαν εγγραφές με αυτά τα φίλτρα.
                                </td>
                            </tr>
                        )}
                    </tbody>
                    {isMonth && filtered.length > 0 && monthTotals && (
                        <tfoot>
                            <tr className="border-t-2 border-ts-border bg-ts-surface-2/60 font-semibold text-ts-fg">
                                <td className="py-2 pe-3" colSpan={7}>
                                    Σύνολα ({filtered.length})
                                </td>
                                <td className="py-2 pe-3">
                                    {formatCurrency(monthTotals.charged)}
                                </td>
                                <td className="py-2 pe-3">
                                    {formatCurrency(monthTotals.paid)}
                                </td>
                                <td className="py-2 pe-3">
                                    {formatCurrency(monthTotals.remaining)}
                                </td>
                                <td className="py-2" />
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        );
    };

    const resultsTable = (() => {
        if (selected === 'classes-catalog') {
            return (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase text-ts-muted">
                                <th className="py-1 pe-3">#</th>
                                <th className="py-1 pe-3">Όνομα</th>
                                <th className="py-1">Άθλημα</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredClasses.map((c, i) => (
                                <tr key={c.id} className="border-t border-ts-border">
                                    <td className="py-1.5 pe-3">{i + 1}</td>
                                    <td className="py-1.5 pe-3">{c.name}</td>
                                    <td className="py-1.5">{c.sport || '—'}</td>
                                </tr>
                            ))}
                            {!filteredClasses.length && (
                                <tr>
                                    <td colSpan={3} className="py-8 text-center text-ts-muted">
                                        Δεν βρέθηκαν τμήματα.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            );
        }
        if (selected === 'training-schedule') {
            return (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase text-ts-muted">
                                <th className="py-1 pe-3">#</th>
                                <th className="py-1 pe-3">Ημερομηνία</th>
                                <th className="py-1 pe-3">Ώρα</th>
                                <th className="py-1 pe-3">Τοποθεσία</th>
                                <th className="py-1">Τμήμα</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredTrainings.map((t, i) => {
                                const classId = t.class_id || t.classId || '';
                                const cls = classes.find((c) => c.id === classId);
                                return (
                                    <tr key={t.id || i} className="border-t border-ts-border">
                                        <td className="py-1.5 pe-3">{i + 1}</td>
                                        <td className="py-1.5 pe-3">{t.date || '—'}</td>
                                        <td className="py-1.5 pe-3">
                                            {t.start_time || t.startTime || '—'}–
                                            {t.end_time || t.endTime || '—'}
                                        </td>
                                        <td className="py-1.5 pe-3">{t.location || '—'}</td>
                                        <td className="py-1.5">{cls?.name || '—'}</td>
                                    </tr>
                                );
                            })}
                            {!filteredTrainings.length && (
                                <tr>
                                    <td colSpan={5} className="py-8 text-center text-ts-muted">
                                        Δεν βρέθηκαν προπονήσεις
                                        {!trainings?.length
                                            ? ' (λείπουν δεδομένα trainings από τον server).'
                                            : '.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            );
        }
        if (selected === 'registration-apps') {
            return (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase text-ts-muted">
                                <th className="py-1 pe-3">#</th>
                                <th className="py-1 pe-3">Όνομα</th>
                                <th className="py-1 pe-3">Γονέας</th>
                                <th className="py-1 pe-3">Τηλέφωνο</th>
                                <th className="py-1 pe-3">Τμήμα</th>
                                <th className="py-1 pe-3">Τύπος</th>
                                <th className="py-1 pe-3">Κατάσταση</th>
                                <th className="py-1">Ημ/νία</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredApps.map((app, i) => {
                                const classId = app.class_id || app.classId || '';
                                const cls = classes.find((c) => c.id === classId);
                                return (
                                    <tr key={app.id || i} className="border-t border-ts-border">
                                        <td className="py-1.5 pe-3">{i + 1}</td>
                                        <td className="py-1.5 pe-3">
                                            {`${app.last_name || app.lastName || ''} ${app.first_name || app.firstName || ''}`.trim() ||
                                                '—'}
                                        </td>
                                        <td className="py-1.5 pe-3">
                                            {app.guardian_name || app.guardianName || '—'}
                                        </td>
                                        <td className="py-1.5 pe-3">
                                            {app.guardian_phone || app.guardianPhone || '—'}
                                        </td>
                                        <td className="py-1.5 pe-3">{cls?.name || '—'}</td>
                                        <td className="py-1.5 pe-3">{app.kind || app.type || '—'}</td>
                                        <td className="py-1.5 pe-3">{app.status || '—'}</td>
                                        <td className="py-1.5">
                                            {String(app.created_at || app.createdAt || '').slice(
                                                0,
                                                10,
                                            ) || '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                            {!filteredApps.length && (
                                <tr>
                                    <td colSpan={8} className="py-8 text-center text-ts-muted">
                                        Δεν βρέθηκαν αιτήσεις
                                        {!registrationApplications?.length
                                            ? ' (λείπουν δεδομένα registrationApplications από τον server).'
                                            : '.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            );
        }
        if (selected === 'period-collections') {
            return (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm print:text-black">
                        <thead>
                            <tr className="text-left text-xs uppercase text-ts-muted">
                                <th className="py-1 pe-3">#</th>
                                <th className="py-1 pe-3">Ημ/νία</th>
                                <th className="py-1 pe-3">Αθλητής</th>
                                <th className="py-1 pe-3">Τύπος</th>
                                <th className="py-1 pe-3">Ποσό</th>
                                <th className="py-1 pe-3">Τρόπος</th>
                                <th className="py-1">Απόδειξη</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredPeriodTransactions.map((tx, index) => {
                                const athlete = athleteById.get(tx.athlete_id);
                                const methodLabel =
                                    PAYMENT_METHODS.find((m) => m.value === tx.payment_method)
                                        ?.label ||
                                    tx.payment_method ||
                                    '—';
                                return (
                                    <tr key={tx.id} className="border-t border-ts-border">
                                        <td className="py-1.5 pe-3">{index + 1}</td>
                                        <td className="py-1.5 pe-3">{tx.created_at || '—'}</td>
                                        <td className="py-1.5 pe-3">
                                            {athlete
                                                ? `${athlete.last_name} ${athlete.first_name}`
                                                : '—'}
                                        </td>
                                        <td className="py-1.5 pe-3">
                                            {tx.type === 'charge' ? 'Χρέωση' : 'Πληρωμή'}
                                        </td>
                                        <td className="py-1.5 pe-3">
                                            {formatCurrency(tx.amount || 0)}
                                        </td>
                                        <td className="py-1.5 pe-3">{methodLabel}</td>
                                        <td className="py-1.5">{tx.receipt_number || '—'}</td>
                                    </tr>
                                );
                            })}
                            {!filteredPeriodTransactions.length && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="py-8 text-center text-ts-muted"
                                    >
                                        Δεν βρέθηκαν κινήσεις με αυτά τα φίλτρα.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            );
        }
        if (selected === 'finance-report') {
            return (
                <p className="py-8 text-center text-sm text-ts-muted">
                    Οικονομική αναφορά χωρίς φίλτρα. Τα συγκεντρωτικά ποσά θα εμφανιστούν όταν
                    παρέχονται έσοδα/έξοδα από τον server.
                </p>
            );
        }
        if (selected === 'progress-report') {
            const athlete = athletes.find((a) => a.id === athleteId);
            return (
                <div className="space-y-2">
                    <h3 className="font-semibold text-ts-fg">
                        Αναφορά προόδου —{' '}
                        {athlete
                            ? `${athlete.last_name} ${athlete.first_name}`
                            : '—'}
                    </h3>
                    <p className="text-sm text-ts-muted">
                        Περίοδος {dateFrom || '—'} – {dateTo || '—'}
                    </p>
                    <p className="text-sm text-ts-muted">
                        Δεν υπάρχουν καταχωρήσεις αναφοράς προόδου για την επιλεγμένη περίοδο
                        (ή λείπουν δεδομένα progress reports από τον server).
                    </p>
                </div>
            );
        }
        return renderAthleteRows();
    })();

    const sportSelect = (
        <FilterRow label="Άθλημα">
            <select
                className={inputCls}
                value={sport}
                onChange={(e) => {
                    setSport(e.target.value);
                    setTeamId('');
                    setTrainingId('');
                    setAthleteId('');
                }}
            >
                <option value="">Όλα τα αθλήματα</option>
                {sportOptions.map((name) => (
                    <option key={name} value={name}>
                        {name}
                    </option>
                ))}
            </select>
        </FilterRow>
    );

    const teamSelect = (allLabel = 'Όλα τα τμήματα') => (
        <FilterRow label="Τμήματα">
            <select
                className={inputCls}
                value={teamId}
                onChange={(e) => {
                    setTeamId(e.target.value);
                    setTrainingId('');
                }}
            >
                <option value="">{allLabel}</option>
                {teamOptions.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </select>
        </FilterRow>
    );

    const genderSelect = (
        <FilterRow label="Φύλο">
            <select
                className={inputCls}
                value={gender}
                onChange={(e) => setGender(e.target.value)}
            >
                <option value="">Όλα</option>
                <option value="boy">Αγόρι</option>
                <option value="girl">Κορίτσι</option>
            </select>
        </FilterRow>
    );

    const associationSelect = (
        <FilterRow label="Σωματείο">
            <select
                className={inputCls}
                value={clubName}
                onChange={(e) => setClubName(e.target.value)}
            >
                <option value="">Όλα</option>
                {associations.map((name) => (
                    <option key={name} value={name}>
                        {name}
                    </option>
                ))}
            </select>
        </FilterRow>
    );

    const renderFilterPanel = () => {
        switch (selected) {
            case 'athletes-registry':
                return (
                    <>
                        <FilterRow label="Από Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Έως Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                        <FilterRow label="Έτος Γέννησης">
                            <OpNumber
                                op={birthYearOp}
                                value={birthYear}
                                onOp={setBirthYearOp}
                                onValue={setBirthYear}
                                placeholder="YYYY"
                            />
                        </FilterRow>
                        {genderSelect}
                        <FilterRow label="Χρέωση Εγγραφής">
                            <TriState value={registrationFee} onChange={setRegistrationFee} />
                        </FilterRow>
                        <FilterRow label="Χρέωση μήνα">
                            <TriState value={monthlyCharge} onChange={setMonthlyCharge} />
                        </FilterRow>
                        <FilterRow label="Φωτογραφία">
                            <TriState value={hasPhoto} onChange={setHasPhoto} />
                        </FilterRow>
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                        <FilterRow label="Κάρτα Υγείας">
                            <TriState value={healthCard} onChange={setHealthCard} />
                        </FilterRow>
                        {associationSelect}
                        <FilterRow label="Εισιτήριο Διαρκείας">
                            <TriState value={seasonTicket} onChange={setSeasonTicket} />
                        </FilterRow>
                        <FilterRow label="Τίτλος προσαρμοσμένης χρέωσης">
                            <TriState
                                value={customCharge}
                                onChange={setCustomCharge}
                                yesLabel={customChargeYesLabel}
                            />
                        </FilterRow>
                        <FilterRow label="Πακέτο ρουχισμού">
                            <select
                                className={inputCls}
                                value={clothingPackageId}
                                onChange={(e) => setClothingPackageId(e.target.value)}
                            >
                                <option value="">Όλα</option>
                                <option value={CLOTHING_PACKAGE_NONE}>Χωρίς πακέτο</option>
                                {clothingPackages.map((pkg) => (
                                    <option key={pkg.id} value={pkg.id}>
                                        {pkg.name}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                        <FilterRow label="Αρ. Δελτίου">
                            <TriState
                                value={hasRegistrationCard}
                                onChange={setHasRegistrationCard}
                            />
                        </FilterRow>
                        <FilterRow label="Παρουσία σε προπόνηση">
                            <OpNumber
                                op={trainingPresenceOp}
                                value={trainingPresence}
                                onOp={setTrainingPresenceOp}
                                onValue={setTrainingPresence}
                                min={0}
                                ops={['>=', '=', '<', '>']}
                            />
                        </FilterRow>
                        <FilterRow label="Παραλαβή στολής">
                            <TriState value={uniformReceived} onChange={setUniformReceived} />
                        </FilterRow>
                        <FilterRow label="Μέγεθος Στολής">
                            <select
                                className={inputCls}
                                value={uniformSize}
                                onChange={(e) => setUniformSize(e.target.value)}
                            >
                                <option value="">Όλα</option>
                                {sizeOptions.map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                    </>
                );

            case 'athlete-balances':
                return (
                    <>
                        <FilterRow label="Έως Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                        {genderSelect}
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                        <FilterRow label="Χρέωση μήνα">
                            <TriState value={monthlyCharge} onChange={setMonthlyCharge} />
                        </FilterRow>
                        <FilterRow label="Έχει υπόλοιπο">
                            <TriState value={hasBalance} onChange={setHasBalance} />
                        </FilterRow>
                        <FilterRow label="Υπόλοιπο">
                            <OpNumber
                                op={balanceOp}
                                value={balanceAmount}
                                onOp={setBalanceOp}
                                onValue={setBalanceAmount}
                                min={0}
                                step="0.01"
                            />
                        </FilterRow>
                        {associationSelect}
                    </>
                );

            case 'attendance-sheet':
                return (
                    <>
                        <FilterRow label="Από Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Έως Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                        {genderSelect}
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                        <FilterRow label="Χρέωση μήνα">
                            <TriState value={monthlyCharge} onChange={setMonthlyCharge} />
                        </FilterRow>
                        <FilterRow label="Έχει παρουσίες">
                            <TriState value={hasAttendance} onChange={setHasAttendance} />
                        </FilterRow>
                        <FilterRow label="Παρουσίες">
                            <OpNumber
                                op={presenceOp}
                                value={presenceCount}
                                onOp={setPresenceOp}
                                onValue={setPresenceCount}
                                min={0}
                            />
                        </FilterRow>
                        <FilterRow label="Ποσοστό παρουσίας (%)">
                            <OpNumber
                                op={rateOp}
                                value={rateValue}
                                onOp={setRateOp}
                                onValue={setRateValue}
                                min={0}
                                placeholder="%"
                            />
                        </FilterRow>
                        {associationSelect}
                    </>
                );

            case 'training-attendance':
                return (
                    <>
                        {sportSelect}
                        {teamSelect('Επιλέξτε τμήμα')}
                        <FilterRow label="Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={trainingDate}
                                onChange={(e) => {
                                    setTrainingDate(e.target.value);
                                    setTrainingId('');
                                }}
                            />
                        </FilterRow>
                        <FilterRow label="Προπονήσεις">
                            <select
                                className={inputCls}
                                value={trainingId}
                                disabled={!teamId}
                                onChange={(e) => setTrainingId(e.target.value)}
                            >
                                <option value="">
                                    Μόνο λίστα τμήματος (χωρίς συγκεκριμένη προπόνηση)
                                </option>
                                {dayTrainings.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.start_time || t.startTime || '—'} ·{' '}
                                        {t.location || '—'}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                    </>
                );

            case 'registration-apps':
                return (
                    <>
                        <FilterRow label="Από Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Έως Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Κατηγορία αιτήσεων">
                            <select
                                className={inputCls}
                                value={appCategory}
                                onChange={(e) => setAppCategory(e.target.value)}
                            >
                                <option value="pending">Εκκρεμείς</option>
                                <option value="trial">Δοκιμαστικές</option>
                                <option value="waitlist">Λίστα αναμονής</option>
                                <option value="all">Όλα</option>
                            </select>
                        </FilterRow>
                        <FilterRow label="Τύπος αίτησης">
                            <select
                                className={inputCls}
                                value={appType}
                                onChange={(e) => setAppType(e.target.value)}
                            >
                                <option value="">Όλα</option>
                                <option value="full">Πλήρης εγγραφή</option>
                                <option value="trial">Δοκιμαστική προπόνηση</option>
                                <option value="waitlist">Λίστα αναμονής</option>
                            </select>
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                    </>
                );

            case 'health-expiries':
                return (
                    <>
                        <FilterRow label="Ημερομηνία αναφοράς">
                            <input
                                className={inputCls}
                                type="date"
                                value={refDate}
                                onChange={(e) => setRefDate(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Λήξη πιστοποιητικού">
                            <select
                                className={inputCls}
                                value={healthWindow}
                                onChange={(e) => setHealthWindow(e.target.value)}
                            >
                                <option value="expired">Ληγμένη</option>
                                <option value="7">Λήγει εντός 7 ημερών</option>
                                <option value="30">Λήγει εντός 30 ημερών</option>
                                <option value="60">Λήγει εντός 60 ημερών</option>
                                <option value="90">Λήγει εντός 90 ημερών</option>
                                <option value="none">Χωρίς βεβαίωση</option>
                                <option value="all">Όλα</option>
                            </select>
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                        {genderSelect}
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                    </>
                );

            case 'period-collections':
                return (
                    <>
                        <FilterRow label="Από Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Έως Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                        {sportSelect}
                        <FilterRow label="Ποσό από">
                            <input
                                className={inputCls}
                                type="number"
                                min={0}
                                step="0.01"
                                value={amountMin}
                                onChange={(e) => setAmountMin(e.target.value)}
                                placeholder="π.χ. 10"
                            />
                        </FilterRow>
                        <FilterRow label="Ποσό έως">
                            <input
                                className={inputCls}
                                type="number"
                                min={0}
                                step="0.01"
                                value={amountMax}
                                onChange={(e) => setAmountMax(e.target.value)}
                                placeholder="π.χ. 100"
                            />
                        </FilterRow>
                        <FilterRow label="Τρόπος πληρωμής">
                            <select
                                className={inputCls}
                                value={paymentMethod}
                                onChange={(e) => setPaymentMethod(e.target.value)}
                            >
                                <option value="">Όλα</option>
                                {PAYMENT_METHODS.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                        <FilterRow label="Τύπος κίνησης">
                            <select
                                className={inputCls}
                                value={entryType}
                                onChange={(e) => setEntryType(e.target.value)}
                            >
                                <option value="">Όλα</option>
                                <option value="charge">Χρέωση</option>
                                <option value="payment">Πληρωμή</option>
                            </select>
                        </FilterRow>
                        <FilterRow label="Αθλητής">
                            <input
                                className={inputCls}
                                type="text"
                                value={athleteQuery}
                                onChange={(e) => setAthleteQuery(e.target.value)}
                                placeholder="Επώνυμο ή όνομα"
                            />
                        </FilterRow>
                        {teamSelect()}
                    </>
                );

            case 'debtors':
                return (
                    <>
                        <FilterRow label="Έως Ημερομηνία">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                        {genderSelect}
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                        <FilterRow label="Χρέωση μήνα">
                            <TriState value={monthlyCharge} onChange={setMonthlyCharge} />
                        </FilterRow>
                        <FilterRow label="Ελάχιστο υπόλοιπο">
                            <input
                                className={inputCls}
                                type="number"
                                min={0}
                                step="0.01"
                                value={minBalance}
                                onChange={(e) => setMinBalance(e.target.value)}
                            />
                        </FilterRow>
                        {associationSelect}
                    </>
                );

            case 'month-subscription':
                return (
                    <>
                        <FilterRow label="Μήνας">
                            <select
                                className={inputCls}
                                value={month}
                                onChange={(e) => {
                                    const next = Number(e.target.value);
                                    setMonth(next);
                                    router.get(
                                        route('admin.prints.index'),
                                        { month: next, year },
                                        { preserveState: true, replace: true },
                                    );
                                }}
                            >
                                {MONTH_OPTIONS.map((m) => (
                                    <option key={m.month} value={m.month}>
                                        {m.label}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                        <FilterRow label="Έτος">
                            <select
                                className={inputCls}
                                value={year}
                                onChange={(e) => {
                                    const next = Number(e.target.value);
                                    setYear(next);
                                    router.get(
                                        route('admin.prints.index'),
                                        { month, year: next },
                                        { preserveState: true, replace: true },
                                    );
                                }}
                            >
                                {(yearOptions.length
                                    ? yearOptions
                                    : [year - 1, year, year + 1]
                                ).map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                        {sportSelect}
                        {teamSelect()}
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                        <FilterRow label="Κατάσταση πληρωμής">
                            <select
                                className={inputCls}
                                value={payStatus}
                                onChange={(e) => setPayStatus(e.target.value)}
                            >
                                <option value="">Όλοι</option>
                                <option value="paid">Πληρωμένες</option>
                                <option value="unpaid">Ανεξόφλητες</option>
                                <option value="no_charge">Χωρίς χρέωση</option>
                            </select>
                        </FilterRow>
                    </>
                );

            case 'legal-forms':
                return (
                    <>
                        <div className="mb-2 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    legalMode === 'gdpr'
                                        ? 'bg-ts-accent text-ts-accent-fg'
                                        : 'border border-ts-border hover:bg-ts-hover'
                                }`}
                                onClick={() => setLegalMode('gdpr')}
                            >
                                Φόρμα GDPR
                            </button>
                            <button
                                type="button"
                                className={`rounded-md px-3 py-1.5 text-sm ${
                                    legalMode === 'registration'
                                        ? 'bg-ts-accent text-ts-accent-fg'
                                        : 'border border-ts-border hover:bg-ts-hover'
                                }`}
                                onClick={() => setLegalMode('registration')}
                            >
                                Φόρμα εγγραφής
                            </button>
                        </div>
                        {legalMode === 'gdpr' ? (
                            <>
                                {sportSelect}
                                <FilterRow label="Αθλητής">
                                    <select
                                        className={inputCls}
                                        value={athleteId}
                                        onChange={(e) => setAthleteId(e.target.value)}
                                    >
                                        <option value="">Επιλέξτε αθλητή</option>
                                        {athleteOptions.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.last_name} {s.first_name}
                                            </option>
                                        ))}
                                    </select>
                                </FilterRow>
                            </>
                        ) : (
                            <FilterRow label="Αιτήσεις Εγγραφής">
                                <select
                                    className={inputCls}
                                    value={registrationAppId}
                                    onChange={(e) => setRegistrationAppId(e.target.value)}
                                >
                                    <option value="">Επιλέξτε αίτηση</option>
                                    {(registrationApplications || []).map((app) => (
                                        <option key={app.id} value={app.id}>
                                            {`${app.last_name || app.lastName || ''} ${app.first_name || app.firstName || ''}`.trim() ||
                                                app.id}
                                        </option>
                                    ))}
                                </select>
                            </FilterRow>
                        )}
                    </>
                );

            case 'progress-report':
                return (
                    <>
                        {sportSelect}
                        <FilterRow label="Αθλητής">
                            <select
                                className={inputCls}
                                value={athleteId}
                                onChange={(e) => setAthleteId(e.target.value)}
                            >
                                <option value="">Επίλεξε αθλητή...</option>
                                {athleteOptions.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.last_name} {s.first_name}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                        <FilterRow label="Από">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </FilterRow>
                        <FilterRow label="Έως">
                            <input
                                className={inputCls}
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </FilterRow>
                    </>
                );

            case 'classes-catalog':
                return <>{sportSelect}</>;

            case 'medical-data':
                return (
                    <>
                        {sportSelect}
                        {teamSelect()}
                    </>
                );

            case 'finance-report':
                return null;

            case 'charges-payments':
                return (
                    <>
                        {sportSelect}
                        <FilterRow label="Κατάσταση">
                            <select
                                className={inputCls}
                                value={paymentStatusFilter}
                                onChange={(e) => setPaymentStatusFilter(e.target.value)}
                            >
                                <option value="">Όλα</option>
                                <option value="pending">Εκκρεμεί</option>
                                <option value="paid">Πληρωμένο</option>
                            </select>
                        </FilterRow>
                    </>
                );

            case 'training-schedule':
                return (
                    <>
                        {sportSelect}
                        {teamSelect()}
                    </>
                );

            default:
                return null;
        }
    };

    if (isResultsView && NEW_WINDOW_TYPES.includes(selected)) {
        const resultsTitle = resultsTitleFor(selected);
        return (
            <AdminLayout title={resultsTitle}>
                <Head title={resultsTitle} />
                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-ts-fg">{resultsTitle}</h1>
                            <p className="text-sm text-ts-muted">
                                Αποτελέσματα αναζήτησης σε νέο παράθυρο.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={exportXlsx}
                                className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                            >
                                Εξαγωγή XLSX
                            </button>
                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            >
                                Εκτύπωση
                            </button>
                        </div>
                    </div>
                    {resultsTable}
                </section>
            </AdminLayout>
        );
    }

    const searchDisabled =
        (selected === 'training-attendance' && !teamId) ||
        (selected === 'legal-forms' && legalMode === 'gdpr' && !athleteId) ||
        (selected === 'progress-report' && !athleteId);

    return (
        <AdminLayout title="Εκτύπωση">
            <Head title="Εκτύπωση" />

            <h1 className="mb-4 text-xl font-semibold text-ts-fg">Εκτύπωση</h1>

            <div className="mb-4 grid gap-4 lg:grid-cols-[240px_1fr]">
                <aside className="rounded-lg border border-ts-border bg-ts-surface p-2">
                    <nav className="space-y-0.5" aria-label="Εκτύπωση">
                        {PRINT_TYPES.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => {
                                    setSelected(t.id);
                                    setShowResults(false);
                                    if (t.id === 'athletes-registry') {
                                        setDateTo((prev) => prev || todayIso());
                                        setActive('');
                                    } else if (
                                        t.id === 'health-expiries' ||
                                        t.id === 'debtors' ||
                                        t.id === 'month-subscription'
                                    ) {
                                        setActive('yes');
                                    } else {
                                        setActive('');
                                    }
                                    if (t.id === 'attendance-sheet') {
                                        setDateFrom((prev) => prev || seasonStartIso());
                                        setDateTo((prev) => prev || todayIso());
                                    }
                                    if (t.id === 'period-collections') {
                                        setDateFrom((prev) => prev || monthStartIso());
                                        setDateTo((prev) => prev || todayIso());
                                    }
                                    if (t.id === 'progress-report') {
                                        setDateFrom((prev) => prev || monthsAgoIso(3));
                                        setDateTo((prev) => prev || todayIso());
                                    }
                                    if (
                                        t.id === 'athlete-balances' ||
                                        t.id === 'debtors' ||
                                        t.id === 'health-expiries'
                                    ) {
                                        setDateTo((prev) => prev || todayIso());
                                    }
                                }}
                                className={`block w-full rounded-md px-2 py-1.5 text-left text-sm ${
                                    selected === t.id
                                        ? 'border-l-2 border-ts-accent bg-ts-accent-soft text-ts-accent'
                                        : 'text-ts-fg-soft hover:bg-ts-hover'
                                }`}
                            >
                                {t.title}
                            </button>
                        ))}
                    </nav>
                </aside>

                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-ts-fg">{current.title}</h2>
                            <p className="text-sm text-ts-muted">{typeDescription(selected)}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {selected !== 'legal-forms' ? (
                                <button
                                    type="button"
                                    onClick={exportXlsx}
                                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                                >
                                    Εξαγωγή XLSX
                                </button>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => window.print()}
                                disabled={
                                    selected === 'legal-forms' &&
                                    legalMode === 'gdpr' &&
                                    !athleteId
                                }
                                className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg disabled:opacity-50"
                            >
                                Εκτύπωση
                            </button>
                        </div>
                    </div>

                    <div className="mb-4 space-y-2 rounded-md border border-ts-border bg-ts-bg/40 p-3">
                        {renderFilterPanel()}

                        <div className="pt-1">
                            <button
                                type="button"
                                className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg disabled:opacity-50"
                                onClick={runSearch}
                                disabled={searchDisabled}
                            >
                                {selected === 'legal-forms' ? 'Εκτύπωση' : 'Αναζήτηση'}
                            </button>
                            {opensResultsInNewWindow ? (
                                <p className="mt-2 text-xs text-ts-muted">
                                    Τα αποτελέσματα ανοίγουν σε νέο παράθυρο.
                                </p>
                            ) : null}
                        </div>
                    </div>

                    {showInlineResults ? (
                        resultsTable
                    ) : (
                        <div className="rounded-md border border-dashed border-ts-border px-4 py-10 text-center text-sm text-ts-muted">
                            {opensResultsInNewWindow
                                ? 'Ορίστε τα φίλτρα και πατήστε Αναζήτηση για να ανοίξουν τα αποτελέσματα σε νέο παράθυρο.'
                                : selected === 'legal-forms'
                                  ? 'Επιλέξτε έντυπο και πατήστε Εκτύπωση.'
                                  : `Προεπισκόπηση «${current.title}» — πατήστε Αναζήτηση.`}
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
