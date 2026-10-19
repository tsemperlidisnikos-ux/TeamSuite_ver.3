import AdminLayout from '@/Layouts/AdminLayout';
import { migrateGuardianNameIntoParentFields } from '@/lib/greekGivenName';
import {
    athletePayloadForHealthCard,
    openAthleteHealthCardPreview,
} from '@/lib/healthCard/preview';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const TABS = [
    { id: 'personal', label: 'Προσωπικά Στοιχεία' },
    { id: 'identity', label: 'AMKA & Ταυτοποίηση' },
    { id: 'health', label: 'Κάρτα Υγείας' },
    { id: 'announcements', label: 'Ανακοινώσεις' },
    { id: 'fees', label: 'Συνδρομές / Οφειλές' },
    { id: 'gdpr', label: 'Συγκαταθέσεις (GDPR)' },
    { id: 'progress', label: 'Πρόοδος' },
    { id: 'history', label: 'Ιστορικό' },
];

const STATUS_LABEL = {
    active: 'ΕΝΕΡΓΟΣ',
    trial: 'ΔΟΚΙΜΗ',
    inactive: 'ΑΝΕΝΕΡΓΟΣ',
};

const BLOOD = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

const AMKA_CONSENT_TITLE = 'Συγκατάθεση Επεξεργασίας ΑΜΚΑ';
const AMKA_CONSENT_LABEL =
    'Δηλώνω ότι συναινώ στη συλλογή και επεξεργασία του ΑΜΚΑ μου (ή του παιδιού μου, εφόσον είμαι γονέας) αποκλειστικά για τον σκοπό έκδοσης και σφράγισης της κάρτας υγείας από εξουσιοδοτημένο ιατρό, σύμφωνα με τον GDPR και τον Ν. 4624/2019. Η συγκατάθεση μπορεί να ανακληθεί οποτεδήποτε.';
const AMKA_CONSENT_CHECKBOX = 'Συμφωνώ';
const AMKA_PRIVACY = {
    collectionTitle: 'Συλλογή και Επεξεργασία ΑΜΚΑ',
    collectionBody:
        'Η εφαρμογή συλλέγει τον Αριθμό Μητρώου Κοινωνικής Ασφάλισης (ΑΜΚΑ) αποκλειστικά για τον σκοπό έκδοσης και σφράγισης της κάρτας υγείας αθλητή από εξουσιοδοτημένο ιατρό. Ο ΑΜΚΑ δεν χρησιμοποιείται για κανέναν άλλο σκοπό και δεν κοινοποιείται σε τρίτους εκτός του συνεργαζόμενου ιατρού που εκδίδει την κάρτα υγείας.',
    legalTitle: 'Νομική Βάση',
    legalBody:
        'Η επεξεργασία βασίζεται στο άρθρο 6(1)(b) GDPR (εκτέλεση σύμβασης/παροχή υπηρεσίας) και στο άρθρο 9(2)(h) GDPR (ιατρική διάγνωση και παροχή υπηρεσιών υγείας).',
    securityTitle: 'Ασφάλεια Δεδομένων',
    securityBody:
        'Ο ΑΜΚΑ αποθηκεύεται κρυπτογραφημένος με ισχυρά πρότυπα (AES-256). Η πρόσβαση περιορίζεται αυστηρά σε εξουσιοδοτημένους χρήστες (διαχειριστές συλλόγου, γραμματεία και ιατρούς). Όλες οι προσβάσεις καταγράφονται σε audit logs.',
};

const ATTENDANCE_STATUS = {
    present: 'Παρών',
    absent: 'Απών',
    late: 'Καθυστέρηση',
    excused: 'Δικαιολογημένος',
};

function Field({ label, children, className = '', inline = false }) {
    if (inline) {
        return (
            <div
                className={`flex flex-wrap items-center gap-x-4 gap-y-2 text-sm ${className}`}
            >
                <span className="shrink-0 text-ts-muted">{label}</span>
                <div className="min-w-0 flex-1">{children}</div>
            </div>
        );
    }

    return (
        <div className={`block text-sm ${className}`}>
            <span className="text-ts-muted">{label}</span>
            <div className="mt-1">{children}</div>
        </div>
    );
}

function inputClass() {
    return 'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
}

function Card({ title, children, className = '' }) {
    return (
        <section
            className={`rounded-lg border border-ts-border bg-ts-surface p-4 ${className}`}
        >
            {title && (
                <h3 className="mb-3 text-sm font-semibold tracking-wide text-ts-fg">
                    {title}
                </h3>
            )}
            {children}
        </section>
    );
}

function withCurrentOption(options, current) {
    const list = [...(options || [])];
    const value = (current || '').toString().trim();
    if (value && !list.some((o) => String(o).toLowerCase() === value.toLowerCase())) {
        list.unshift(value);
    }
    return list;
}

const NOTIFY_EMAIL_CHECKS = [
    { id: 'father', label: 'Email πατρός' },
    { id: 'mother', label: 'Email μητρός' },
    { id: 'athlete', label: 'Email αθλητή' },
];

/** Legacy «both» → τικ σε πατέρα + μητέρα. Χωρίς ξεχωριστή επιλογή «και τα δύο». */
function decodeNotifyPreference(value) {
    const raw = String(value || 'both').trim().toLowerCase();
    if (raw === 'both' || raw === '') {
        return { father: true, mother: true, athlete: false };
    }
    if (raw === 'father' || raw === 'mother' || raw === 'athlete') {
        return {
            father: raw === 'father',
            mother: raw === 'mother',
            athlete: raw === 'athlete',
        };
    }
    const parts = raw.split(/[,\s]+/).map((p) => p.trim()).filter(Boolean);
    return {
        father: parts.includes('father') || parts.includes('both'),
        mother: parts.includes('mother') || parts.includes('both'),
        athlete: parts.includes('athlete'),
    };
}

function encodeNotifyPreference(checks) {
    const father = !!checks.father;
    const mother = !!checks.mother;
    const athlete = !!checks.athlete;
    if (father && mother && !athlete) {
        return 'both';
    }
    if (father && !mother && !athlete) {
        return 'father';
    }
    if (!father && mother && !athlete) {
        return 'mother';
    }
    if (!father && !mother && athlete) {
        return 'athlete';
    }
    const parts = [];
    if (father) {
        parts.push('father');
    }
    if (mother) {
        parts.push('mother');
    }
    if (athlete) {
        parts.push('athlete');
    }
    return parts.join(',') || 'father';
}

function NotifyEmailChecks({ value, disabled, onChange }) {
    const checks = decodeNotifyPreference(value);

    const toggle = (id) => {
        if (disabled) {
            return;
        }
        const next = { ...checks, [id]: !checks[id] };
        if (!next.father && !next.mother && !next.athlete) {
            // Κρατάμε πάντα τουλάχιστον μία επιλογή.
            next[id] = true;
        }
        onChange(encodeNotifyPreference(next));
    };

    return (
        <div className="flex flex-wrap items-center gap-x-5 gap-y-1">
            {NOTIFY_EMAIL_CHECKS.map((option) => (
                <label
                    key={option.id}
                    className={`inline-flex items-center gap-2 text-sm leading-none ${
                        disabled ? 'cursor-default opacity-60' : 'cursor-pointer'
                    }`}
                >
                    <input
                        type="checkbox"
                        className="h-4 w-4 shrink-0 rounded border-ts-border text-teal-700 focus:ring-teal-600"
                        checked={!!checks[option.id]}
                        disabled={disabled}
                        onChange={() => toggle(option.id)}
                    />
                    <span className="text-ts-fg">{option.label}</span>
                </label>
            ))}
        </div>
    );
}

export default function Show({
    athlete,
    canAmka,
    classes,
    sportOptions = [],
    associationOptions = [],
    sizeOptions = [],
    clothingPackages = [],
    transactions,
    attendances,
    attendanceTotals,
    progressReports,
    changeLogs,
    announcements,
    prevId,
    nextId,
    balance,
    lastPayment = null,
    nextTraining = null,
    joinUrl = null,
}) {
    const page = usePage();
    const flash = page.props.flash;
    const pageUrl = page.url;
    const currentClub = page.props.currentClub;
    const platformBranding = page.props.platformBranding;
    const query = useMemo(() => {
        const q = pageUrl.includes('?') ? pageUrl.split('?')[1] : '';
        return new URLSearchParams(q);
    }, [pageUrl]);
    const initialTab = TABS.some((t) => t.id === query.get('tab')) ? query.get('tab') : 'personal';
    const [tab, setTab] = useState(initialTab);
    const [editing, setEditing] = useState(query.get('edit') === '1');
    const [actionsOpen, setActionsOpen] = useState(false);
    const [healthCardPreviewBusy, setHealthCardPreviewBusy] = useState(false);

    const healthCardTemplates =
        platformBranding?.healthCardTemplatesBySport || {};

    const initial = useMemo(() => {
        const base = {
            ...athlete,
            class_ids: athlete.class_ids || [],
            primary_class_id: athlete.primary_class_id || athlete.class_ids?.[0] || '',
            sports: athlete.sports || (athlete.sport ? [athlete.sport] : []),
            gdpr_items: athlete.gdpr_items || {
                personalData: false,
                photoUse: false,
                gallery: false,
                communication: false,
                medical: false,
                amkaHealthCard: false,
            },
            amka: athlete.amka === '********' ? '' : athlete.amka || '',
            clear_amka: false,
            health_card: !!athlete.health_card,
            uniform_received: !!athlete.uniform_received,
            uniform_size: athlete.uniform_size || '',
            clothing_package_ids: athlete.clothing_package_ids || [],
            club_name: athlete.club_name || '',
            auto_renewal: !!athlete.auto_renewal,
            registration_charge: athlete.registration_charge !== false,
            monthly_charge: athlete.monthly_charge !== false,
            custom_charge: !!athlete.custom_charge,
            season_ticket: !!athlete.season_ticket,
            subscription_discount: !!athlete.subscription_discount,
        };

        // Legacy «Όνομα κηδεμόνα» → Πατρώνυμο / Μητρώνυμο (και απόκρυψη πεδίου).
        return migrateGuardianNameIntoParentFields(base);
    }, [athlete]);

    const form = useForm(initial);

    const openHealthCardPreview = async () => {
        if (!canAmka) {
            alert(
                'Η προεπισκόπηση κάρτας υγείας με ΑΜΚΑ είναι διαθέσιμη μόνο σε διαχειριστή, γραμματεία ή ιατρό.',
            );
            return;
        }
        if (!form.data.health_card) {
            alert('Ενεργοποιήστε πρώτα την κάρτα υγείας για τον αθλητή.');
            return;
        }
        setHealthCardPreviewBusy(true);
        try {
            const payload = athletePayloadForHealthCard(
                form.data,
                currentClub?.name || '',
            );
            const result = await openAthleteHealthCardPreview(
                payload,
                healthCardTemplates,
            );
            if (!result.success) {
                alert(result.error || 'Αποτυχία προεπισκόπησης κάρτας υγείας');
            }
        } finally {
            setHealthCardPreviewBusy(false);
        }
    };

    const sportSelectOptions = useMemo(
        () => withCurrentOption(sportOptions, form.data.sport || athlete.sport),
        [sportOptions, form.data.sport, athlete.sport],
    );
    const associationSelectOptions = useMemo(
        () => withCurrentOption(associationOptions, form.data.club_name || athlete.club_name),
        [associationOptions, form.data.club_name, athlete.club_name],
    );
    const sizeSelectOptions = useMemo(
        () => withCurrentOption(sizeOptions, form.data.uniform_size || athlete.uniform_size),
        [sizeOptions, form.data.uniform_size, athlete.uniform_size],
    );
    const clothingSelectOptions = useMemo(() => {
        const list = [...(clothingPackages || [])];
        const currentId = form.data.clothing_package_ids?.[0];
        if (currentId && !list.some((p) => p.id === currentId)) {
            list.unshift({ id: currentId, name: `Πακέτο ${currentId.slice(0, 8)}` });
        }
        return list;
    }, [clothingPackages, form.data.clothing_package_ids]);

    const progressForm = useForm({
        report_date: new Date().toISOString().slice(0, 10),
        title: '',
        rating: '3',
        body: '',
    });

    const owed = Number(balance.charges || 0) - Number(balance.payments || 0);

    const save = (e) => {
        e.preventDefault();
        form.patch(route('admin.athletes.update', athlete.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const setGdprItem = (key, val) => {
        form.setData('gdpr_items', { ...form.data.gdpr_items, [key]: val });
    };

    const statusBadge =
        athlete.status === 'active'
            ? 'bg-teal-50 text-teal-800'
            : athlete.status === 'trial'
              ? 'bg-amber-50 text-amber-800'
              : 'bg-slate-100 text-ts-muted';

    const athleteCode =
        athlete.registration_number ||
        athlete.legacy_id ||
        String(athlete.id || '').slice(0, 8);
    const primaryClassName =
        classes.find((c) => c.id === (athlete.primary_class_id || athlete.class_ids?.[0]))?.name ||
        athlete.class_names ||
        '—';
    const ageYears = athlete.birth_date
        ? Math.max(
              0,
              Math.floor(
                  (Date.now() - new Date(athlete.birth_date).getTime()) /
                      (365.25 * 24 * 60 * 60 * 1000),
              ),
          )
        : null;
    const euro = (n) =>
        Number(n || 0).toLocaleString('el-GR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const personalFit = tab === 'personal';

    return (
        <AdminLayout
            title={`${athlete.last_name} ${athlete.first_name}`}
            compact={personalFit}
        >
            <Head title={`${athlete.last_name} ${athlete.first_name}`} />

            <div
                className={
                    personalFit
                        ? 'flex min-h-0 flex-1 flex-col overflow-hidden'
                        : 'contents'
                }
            >
            {flash?.success && (
                <div className="mb-3 shrink-0 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-2 shrink-0 text-sm text-ts-muted">
                <Link href={route('admin.athletes.index')} className="text-ts-accent hover:underline">
                    Αθλητές
                </Link>
                <span className="mx-1">›</span>
                Προφίλ Αθλητή
            </div>

            <div className="mb-3 flex shrink-0 flex-wrap items-start justify-between gap-3 rounded-lg border border-ts-border bg-ts-surface p-4">
                <div className="flex min-w-0 flex-1 gap-4">
                    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-slate-200 text-lg font-semibold text-ts-muted">
                        {(athlete.last_name?.[0] || '?') + (athlete.first_name?.[0] || '')}
                    </div>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-xl font-semibold uppercase tracking-wide text-ts-fg sm:text-2xl">
                                {athlete.last_name} {athlete.first_name}
                            </h2>
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusBadge}`}>
                                {STATUS_LABEL[athlete.status] || athlete.status}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-ts-muted">
                            Κωδ. Αθλητή: {athleteCode}
                            {athlete.enrolled_at
                                ? ` · Ημ/νία Εγγραφής: ${new Date(athlete.enrolled_at).toLocaleDateString('el-GR', { day: 'numeric', month: 'short', year: 'numeric' })}`
                                : ''}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ts-fg-soft">
                            {athlete.birth_date && (
                                <span>
                                    {new Date(athlete.birth_date).toLocaleDateString('el-GR', {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                    {ageYears != null ? ` (${ageYears} ετών)` : ''}
                                </span>
                            )}
                            {(canAmka || athlete.amka) && (
                                <span>AMKA {athlete.amka || '—'}</span>
                            )}
                            {athlete.sport && <span>{athlete.sport}</span>}
                            {primaryClassName ? <span>{primaryClassName}</span> : null}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-2 text-xs">
                            <Link
                                href={route('admin.fees.index')}
                                className="rounded-full bg-sky-50 px-2.5 py-1 text-sky-800"
                            >
                                Οφειλή {euro(owed)} €
                            </Link>
                            <Link
                                href={route('admin.transactions.index', { athlete_id: athlete.id })}
                                className="rounded-full bg-slate-100 px-2.5 py-1 text-ts-fg-soft hover:bg-ts-hover"
                            >
                                Τελευταία πληρωμή{' '}
                                {lastPayment
                                    ? `${euro(lastPayment.amount)} € · ${new Date(lastPayment.paid_at || lastPayment.created_at || Date.now()).toLocaleDateString('el-GR', { day: 'numeric', month: 'short', year: 'numeric' })}`
                                    : '—'}
                            </Link>
                            <Link
                                href={
                                    nextTraining?.academy_class_id
                                        ? route('admin.attendance.index', {
                                              class_id: nextTraining.academy_class_id,
                                              date: nextTraining.starts_at?.slice(0, 10),
                                          })
                                        : route('admin.trainings.index')
                                }
                                className="rounded-full bg-sky-50 px-2.5 py-1 text-sky-800"
                            >
                                Επόμενη προπόνηση{' '}
                                {nextTraining?.starts_at
                                    ? new Date(nextTraining.starts_at).toLocaleString('el-GR', {
                                          day: 'numeric',
                                          month: 'short',
                                          year: 'numeric',
                                          hour: '2-digit',
                                          minute: '2-digit',
                                      })
                                    : '—'}
                            </Link>
                        </div>
                    </div>
                </div>
                <div className="flex flex-wrap items-start gap-2">
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setActionsOpen((v) => !v)}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg"
                        >
                            Ενέργειες ▾
                        </button>
                        {actionsOpen && (
                            <div className="absolute end-0 z-10 mt-1 w-56 rounded-md border border-ts-border bg-ts-surface py-1 shadow-lg">
                                <button
                                    type="button"
                                    className="block w-full px-3 py-1.5 text-left text-sm text-ts-fg-soft hover:bg-ts-hover"
                                    onClick={() => {
                                        setEditing(true);
                                        setActionsOpen(false);
                                    }}
                                >
                                    Επεξεργασία
                                </button>
                                <button
                                    type="button"
                                    className="block w-full px-3 py-1.5 text-left text-sm text-ts-fg-soft hover:bg-ts-hover"
                                    onClick={() => {
                                        setActionsOpen(false);
                                        const reg = String(athlete.registration_number || '').trim();
                                        const athleteCode = reg
                                            ? reg.toUpperCase()
                                            : String(athlete.id || '')
                                                  .slice(-8)
                                                  .toUpperCase();
                                        const payload = {
                                            v: 1,
                                            clubName: currentClub?.name || '',
                                            season: '',
                                            lastName: athlete.last_name || '',
                                            firstName: athlete.first_name || '',
                                            birthDate: athlete.birth_date || '',
                                            athleteCode,
                                            logoUrl:
                                                currentClub?.logo_url ||
                                                platformBranding?.appLogoUrl ||
                                                null,
                                        };
                                        const encoded = btoa(
                                            unescape(encodeURIComponent(JSON.stringify(payload))),
                                        );
                                        const url = `${window.location.origin}/id/card#${encoded}`;
                                        const w = window.open(url, '_blank', 'width=480,height=720');
                                        if (!w) {
                                            alert('Επίτρεψε τα pop-up για την προεπισκόπηση QR κάρτας');
                                        }
                                    }}
                                >
                                    Προεπισκόπηση QR κάρτας
                                </button>
                                {joinUrl && (
                                    <a
                                        href={joinUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block w-full px-3 py-1.5 text-left text-sm text-ts-fg-soft hover:bg-ts-hover"
                                        onClick={() => setActionsOpen(false)}
                                    >
                                        Φόρμα δημόσιας εγγραφής
                                    </a>
                                )}
                                <button
                                    type="button"
                                    className="block w-full px-3 py-1.5 text-left text-sm text-ts-fg-soft hover:bg-ts-hover disabled:opacity-50"
                                    disabled={healthCardPreviewBusy}
                                    onClick={() => {
                                        setActionsOpen(false);
                                        void openHealthCardPreview();
                                    }}
                                >
                                    {healthCardPreviewBusy
                                        ? 'Προεπισκόπηση…'
                                        : 'Προεπισκόπηση κάρτας υγείας'}
                                </button>
                                <Link
                                    href={route('admin.transactions.index', {
                                        athlete_id: athlete.id,
                                    })}
                                    className="block w-full px-3 py-1.5 text-left text-sm text-ts-fg-soft hover:bg-ts-hover"
                                    onClick={() => setActionsOpen(false)}
                                >
                                    Συναλλαγή
                                </Link>
                                <button
                                    type="button"
                                    className="block w-full px-3 py-1.5 text-left text-sm text-ts-fg-soft hover:bg-ts-hover"
                                    onClick={() => {
                                        setActionsOpen(false);
                                        if (!confirm('Διαγραφή φωτογραφίας;')) {
                                            return;
                                        }
                                        form.setData('photo_url', '');
                                        form.patch(route('admin.athletes.update', athlete.id), {
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    Διαγραφή φωτογραφίας
                                </button>
                                <button
                                    type="button"
                                    className="block w-full px-3 py-1.5 text-left text-sm text-rose-600 hover:bg-ts-hover"
                                    onClick={() => {
                                        setActionsOpen(false);
                                        if (confirm('Διαγραφή αθλητή;')) {
                                            router.delete(route('admin.athletes.destroy', athlete.id));
                                        }
                                    }}
                                >
                                    Διαγραφή
                                </button>
                            </div>
                        )}
                    </div>
                    {prevId ? (
                        <Link
                            href={route('admin.athletes.show', prevId)}
                            className="rounded-md border border-ts-border px-2.5 py-1.5 text-sm text-ts-fg-soft"
                        >
                            ‹
                        </Link>
                    ) : (
                        <span className="rounded-md border border-ts-border px-2.5 py-1.5 text-sm text-ts-muted opacity-40">
                            ‹
                        </span>
                    )}
                    {nextId ? (
                        <Link
                            href={route('admin.athletes.show', nextId)}
                            className="rounded-md border border-ts-border px-2.5 py-1.5 text-sm text-ts-fg-soft"
                        >
                            ›
                        </Link>
                    ) : (
                        <span className="rounded-md border border-ts-border px-2.5 py-1.5 text-sm text-ts-muted opacity-40">
                            ›
                        </span>
                    )}
                </div>
            </div>

            <div className="mb-3 flex shrink-0 flex-wrap gap-1 border-b border-ts-border">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`border-b-2 px-3 py-2 text-sm ${
                            tab === t.id
                                ? 'border-teal-600 text-teal-700'
                                : 'border-transparent text-ts-muted hover:text-ts-fg'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <form
                onSubmit={save}
                className={
                    personalFit
                        ? 'flex min-h-0 flex-1 flex-col overflow-hidden'
                        : 'space-y-4'
                }
            >
                {tab === 'personal' && (
                    <div className="grid min-h-0 flex-1 gap-4 overflow-y-auto lg:grid-cols-2">
                        <div className="space-y-4">
                        <Card title="Βασικά Στοιχεία">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="ΑΜΚΑ">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.amka || ''}
                                        onChange={(e) =>
                                            form.setData('amka', e.target.value)
                                        }
                                        placeholder="π.χ. 12345678901"
                                    />
                                </Field>
                                <Field label="ΑΔΤ">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.adt || ''}
                                        onChange={(e) => form.setData('adt', e.target.value)}
                                    />
                                </Field>
                                <Field label="Επώνυμο">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.last_name || ''}
                                        onChange={(e) =>
                                            form.setData('last_name', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Όνομα">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.first_name || ''}
                                        onChange={(e) =>
                                            form.setData('first_name', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Ημερομηνία γέννησης">
                                    <input
                                        type="date"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.birth_date || ''}
                                        onChange={(e) =>
                                            form.setData('birth_date', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Φύλο">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.gender || ''}
                                        onChange={(e) =>
                                            form.setData('gender', e.target.value)
                                        }
                                    >
                                        <option value="">—</option>
                                        <option value="boy">Αγόρι</option>
                                        <option value="girl">Κορίτσι</option>
                                        <option value="other">Άλλο</option>
                                    </select>
                                </Field>
                                <Field label="Email αθλητή">
                                    <input
                                        type="email"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.email || ''}
                                        onChange={(e) =>
                                            form.setData('email', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Τηλέφωνο αθλητή">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.phone || ''}
                                        onChange={(e) =>
                                            form.setData('phone', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                        </Card>

                        <Card title="Γονέας / Κηδεμόνας">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <Field label="Πατρώνυμο">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.father_first_name || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'father_first_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Τηλέφωνο πατρός">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.guardian_phone || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'guardian_phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Email πατρός">
                                    <input
                                        type="email"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.father_email || ''}
                                        onChange={(e) =>
                                            form.setData('father_email', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Μητρώνυμο">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.mother_first_name || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'mother_first_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Τηλέφωνο μητρός">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.mother_phone || ''}
                                        onChange={(e) =>
                                            form.setData('mother_phone', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Email μητρός">
                                    <input
                                        type="email"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.mother_email || ''}
                                        onChange={(e) =>
                                            form.setData('mother_email', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Κύριο email για ενημερώσεις"
                                    className="sm:col-span-2 lg:col-span-3"
                                    inline
                                >
                                    <NotifyEmailChecks
                                        value={form.data.preferred_parent_email}
                                        disabled={!editing}
                                        onChange={(next) =>
                                            form.setData('preferred_parent_email', next)
                                        }
                                    />
                                </Field>
                                <div className="col-span-full grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,3fr)_5.5rem_minmax(0,1.1fr)_minmax(0,1fr)]">
                                    <Field label="Διεύθυνση">
                                        <input
                                            className={inputClass()}
                                            disabled={!editing}
                                            value={form.data.address || ''}
                                            onChange={(e) =>
                                                form.setData('address', e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Τ.Κ.">
                                        <input
                                            className={inputClass()}
                                            disabled={!editing}
                                            value={form.data.postal_code || ''}
                                            onChange={(e) =>
                                                form.setData('postal_code', e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Πόλη">
                                        <input
                                            className={inputClass()}
                                            disabled={!editing}
                                            value={form.data.city || ''}
                                            onChange={(e) =>
                                                form.setData('city', e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Νομός">
                                        <input
                                            className={inputClass()}
                                            disabled={!editing}
                                            value={form.data.county || ''}
                                            onChange={(e) =>
                                                form.setData('county', e.target.value)
                                            }
                                        />
                                    </Field>
                                </div>
                            </div>
                        </Card>

                        <Card title="Σημειώσεις">
                            <textarea
                                className={inputClass()}
                                rows={3}
                                disabled={!editing}
                                placeholder="Γενικές παρατηρήσεις..."
                                value={form.data.comments || ''}
                                onChange={(e) => form.setData('comments', e.target.value)}
                            />
                        </Card>
                        </div>

                        <div className="space-y-4">
                        <Card title="Αθλητική Πληροφορία">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Άθλημα">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.sport || ''}
                                        onChange={(e) => {
                                            const sport = e.target.value;
                                            form.setData({
                                                ...form.data,
                                                sport,
                                                sports: sport ? [sport] : [],
                                            });
                                        }}
                                    >
                                        <option value="">Επιλογή αθλήματος…</option>
                                        {sportSelectOptions.map((name) => (
                                            <option key={name} value={name}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Τμήμα">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={
                                            form.data.primary_class_id ||
                                            form.data.class_ids?.[0] ||
                                            ''
                                        }
                                        onChange={(e) => {
                                            const id = e.target.value;
                                            form.setData({
                                                ...form.data,
                                                primary_class_id: id,
                                                class_ids: id ? [id] : [],
                                            });
                                        }}
                                    >
                                        <option value="">Επιλογή τμήματος…</option>
                                        {classes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                    {!classes.length && (
                                        <span className="mt-1 block text-xs text-ts-muted">
                                            Δεν υπάρχουν τμήματα — δημιούργησε από ΜΗΤΡΩΟ →
                                            Τμήματα.
                                        </span>
                                    )}
                                </Field>
                                <Field label="Αρ. Δελτίου">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.registration_number || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'registration_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Σωματείο">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.club_name || ''}
                                        onChange={(e) =>
                                            form.setData('club_name', e.target.value)
                                        }
                                    >
                                        <option value="">Επιλογή σωματείου…</option>
                                        {associationSelectOptions.map((name) => (
                                            <option key={name} value={name}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Παραλαβή στολής">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.uniform_received ? 'yes' : 'no'}
                                        onChange={(e) =>
                                            form.setData(
                                                'uniform_received',
                                                e.target.value === 'yes',
                                            )
                                        }
                                    >
                                        <option value="no">Όχι</option>
                                        <option value="yes">Ναι</option>
                                    </select>
                                </Field>
                                <Field label="Μέγεθος στολής">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.uniform_size || ''}
                                        onChange={(e) =>
                                            form.setData('uniform_size', e.target.value)
                                        }
                                    >
                                        <option value="">Επιλογή μεγέθους…</option>
                                        {sizeSelectOptions.map((size) => (
                                            <option key={size} value={size}>
                                                {size}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Πακέτο ρουχισμού">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.clothing_package_ids?.[0] || ''}
                                        onChange={(e) => {
                                            const id = e.target.value;
                                            form.setData(
                                                'clothing_package_ids',
                                                id ? [id] : [],
                                            );
                                        }}
                                    >
                                        <option value="">Επιλογή πακέτου...</option>
                                        {clothingSelectOptions.map((pkg) => (
                                            <option key={pkg.id} value={pkg.id}>
                                                {pkg.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Μηνιαία συνδρομή (€)">
                                    <input
                                        type="number"
                                        step="0.01"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.monthly_fee ?? ''}
                                        onChange={(e) =>
                                            form.setData('monthly_fee', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                        </Card>

                        <Card title="Ιατρικές Παρατηρήσεις">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Ομάδα αίματος">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.blood_type || ''}
                                        onChange={(e) =>
                                            form.setData('blood_type', e.target.value)
                                        }
                                    >
                                        {BLOOD.map((b) => (
                                            <option key={b || 'empty'} value={b}>
                                                {b || '—'}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Αλλεργίες">
                                    <textarea
                                        className={inputClass()}
                                        rows={2}
                                        disabled={!editing}
                                        value={form.data.allergies || ''}
                                        onChange={(e) =>
                                            form.setData('allergies', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Χρόνιες παθήσεις / παρατηρήσεις">
                                    <textarea
                                        className={inputClass()}
                                        rows={2}
                                        disabled={!editing}
                                        value={form.data.chronic_conditions || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'chronic_conditions',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Φαρμακευτική αγωγή">
                                    <textarea
                                        className={inputClass()}
                                        rows={2}
                                        disabled={!editing}
                                        value={form.data.medication || ''}
                                        onChange={(e) =>
                                            form.setData('medication', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                        </Card>

                        <Card title="Κατάσταση εγγραφής">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Κατάσταση">
                                    <select
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.status || 'active'}
                                        onChange={(e) =>
                                            form.setData('status', e.target.value)
                                        }
                                    >
                                        <option value="active">Ενεργός</option>
                                        <option value="trial">Δοκιμή</option>
                                        <option value="inactive">Ανενεργός</option>
                                    </select>
                                </Field>
                                <Field label="Λήξη εγγραφής">
                                    <input
                                        type="date"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.registration_expires || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'registration_expires',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <label className="flex items-center gap-2 text-sm text-ts-fg-soft sm:col-span-2">
                                    <input
                                        type="checkbox"
                                        disabled={!editing}
                                        checked={!!form.data.auto_renewal}
                                        onChange={(e) =>
                                            form.setData('auto_renewal', e.target.checked)
                                        }
                                    />
                                    Αυτόματη Ανανέωση
                                </label>
                            </div>
                        </Card>
                        </div>
                    </div>
                )}

                {tab === 'identity' && (
                    <Card title="AMKA & Ταυτοποίηση">
                        {canAmka ? (
                            <div className="mb-4 rounded-md border border-amber-100 bg-amber-50/80 p-4 text-sm text-ts-fg">
                                <h4 className="font-semibold">{AMKA_PRIVACY.collectionTitle}</h4>
                                <p className="mt-1 text-ts-muted">{AMKA_PRIVACY.collectionBody}</p>
                                <h4 className="mt-3 font-semibold">{AMKA_PRIVACY.legalTitle}</h4>
                                <p className="mt-1 text-ts-muted">{AMKA_PRIVACY.legalBody}</p>
                                <h4 className="mt-3 font-semibold">{AMKA_PRIVACY.securityTitle}</h4>
                                <p className="mt-1 text-ts-muted">{AMKA_PRIVACY.securityBody}</p>
                            </div>
                        ) : (
                            <p className="mb-3 text-sm text-ts-muted">
                                Ο ΑΜΚΑ είναι ορατός μόνο σε διαχειριστή συλλόγου, γραμματεία και
                                ιατρό (RBAC).
                            </p>
                        )}
                        {canAmka && (
                            <div className="mt-2">
                                <p className="text-sm font-medium text-ts-fg">{AMKA_CONSENT_TITLE}</p>
                                <p className="mt-1 text-sm text-ts-muted">{AMKA_CONSENT_LABEL}</p>
                                <label className="mt-3 flex items-start gap-2 text-sm text-ts-fg-soft">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5"
                                        disabled={!editing}
                                        checked={!!form.data.gdpr_items?.amkaHealthCard}
                                        onChange={(e) =>
                                            setGdprItem('amkaHealthCard', e.target.checked)
                                        }
                                    />
                                    <span>{AMKA_CONSENT_CHECKBOX}</span>
                                </label>
                            </div>
                        )}
                        {editing && athlete.amka_set && canAmka && (
                            <label className="mt-3 flex items-center gap-2 text-sm text-rose-700">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.clear_amka}
                                    onChange={(e) =>
                                        form.setData('clear_amka', e.target.checked)
                                    }
                                />
                                Διαγραφή ΑΜΚΑ
                            </label>
                        )}
                    </Card>
                )}

                {tab === 'health' && (
                    <Card title="Κάρτα Υγείας">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Κάρτα υγείας">
                                <select
                                    className={inputClass()}
                                    disabled={!editing}
                                    value={form.data.health_card ? 'yes' : 'no'}
                                    onChange={(e) =>
                                        form.setData('health_card', e.target.value === 'yes')
                                    }
                                >
                                    <option value="yes">Ναι</option>
                                    <option value="no">Όχι</option>
                                </select>
                            </Field>
                            <Field label="Λήξη κάρτας υγείας">
                                <input
                                    type="date"
                                    className={inputClass()}
                                    disabled={!editing}
                                    value={form.data.health_card_expires || ''}
                                    onChange={(e) =>
                                        form.setData('health_card_expires', e.target.value)
                                    }
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <span className="text-sm text-ts-muted">Έγγραφο</span>
                                <button
                                    type="button"
                                    className="mt-1 block w-full rounded-md bg-teal-700 px-3 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50"
                                    disabled={
                                        !form.data.health_card ||
                                        !canAmka ||
                                        healthCardPreviewBusy
                                    }
                                    onClick={() => void openHealthCardPreview()}
                                >
                                    {healthCardPreviewBusy
                                        ? 'Δημιουργία PDF…'
                                        : 'Προεπισκόπηση PDF'}
                                </button>
                                <p className="mt-1.5 text-xs text-ts-muted">
                                    Ανοίγει το επίσημο έντυπο με στοιχεία αθλητή, φωτογραφία και
                                    ΑΜΚΑ — όπως στο TeamSuite.
                                </p>
                            </div>
                        </div>
                    </Card>
                )}

                {tab === 'fees' && (
                    <>
                        <Card title="Συνδρομές & χρεώσεις">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {[
                                    ['registration_charge', 'Χρέωση εγγραφής'],
                                    ['monthly_charge', 'Χρέωση μήνα'],
                                    ['season_ticket', 'Εισιτήριο διαρκείας'],
                                    ['subscription_discount', 'Έκπτωση συνδρομής'],
                                ].map(([key, label]) => (
                                    <Field key={key} label={label}>
                                        <select
                                            className={inputClass()}
                                            disabled={!editing}
                                            value={form.data[key] ? 'yes' : 'no'}
                                            onChange={(e) =>
                                                form.setData(key, e.target.value === 'yes')
                                            }
                                        >
                                            <option value="yes">Ναι</option>
                                            <option value="no">Όχι</option>
                                        </select>
                                    </Field>
                                ))}
                                <Field label="Μηνιαίο ποσό έκπτωσης (€)">
                                    <input
                                        type="number"
                                        step="0.01"
                                        className={inputClass()}
                                        disabled={!editing}
                                        value={form.data.discount_amount ?? 0}
                                        onChange={(e) =>
                                            form.setData('discount_amount', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Λόγος έκπτωσης" className="sm:col-span-2">
                                    <input
                                        className={inputClass()}
                                        disabled={!editing}
                                        placeholder="Επιλογή λόγου έκπτωσης..."
                                        value={form.data.discount_reason || ''}
                                        onChange={(e) =>
                                            form.setData('discount_reason', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                        </Card>
                        <Card title="Οφειλές σεζόν">
                            <p className="mb-2 text-sm text-ts-muted">
                                Χρεώσεις {euro(balance.charges)} € · Πληρωμές{' '}
                                {euro(balance.payments)} € · Υπόλοιπο {euro(owed)} €
                            </p>
                            <ul className="space-y-1 text-sm">
                                {transactions.map((t) => (
                                    <li
                                        key={t.id}
                                        className="flex justify-between border-b border-ts-border py-1"
                                    >
                                        <span>
                                            {t.type} {t.month}/{t.year}{' '}
                                            <span className="text-ts-muted">{t.comments}</span>
                                        </span>
                                        <span>
                                            {euro(t.amount)} €
                                        </span>
                                    </li>
                                ))}
                                {!transactions.length && (
                                    <li className="text-ts-muted">Δεν υπάρχουν κινήσεις ακόμα.</li>
                                )}
                            </ul>
                        </Card>
                    </>
                )}

                {tab === 'gdpr' && (
                    <Card title="Συγκαταθέσεις (GDPR)">
                        <div className="mb-3 flex flex-wrap gap-2">
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    form.data.gdpr_consent === 'full' ||
                                    form.data.gdpr_consent === 'locked'
                                        ? 'bg-emerald-50 text-emerald-800'
                                        : 'bg-slate-100 text-ts-muted'
                                }`}
                            >
                                {form.data.gdpr_consent === 'full' ||
                                form.data.gdpr_consent === 'locked'
                                    ? 'Πλήρης συναίνεση'
                                    : 'Εκκρεμής συναίνεση'}
                            </span>
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                    form.data.gdpr_consent === 'locked'
                                        ? 'bg-amber-50 text-amber-900'
                                        : 'bg-slate-100 text-ts-muted'
                                }`}
                            >
                                {form.data.gdpr_consent === 'locked'
                                    ? 'Κλειδωμένο'
                                    : 'Ξεκλείδωτο'}
                            </span>
                        </div>
                        <Field label="Λήξη συναίνεσης">
                            <input
                                type="date"
                                className={inputClass()}
                                disabled={!editing || form.data.gdpr_consent === 'locked'}
                                value={form.data.consent_expires || ''}
                                onChange={(e) =>
                                    form.setData('consent_expires', e.target.value)
                                }
                            />
                        </Field>
                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                            {[
                                ['personalData', 'Επεξεργασία προσωπικών δεδομένων'],
                                ['photoUse', 'Χρήση φωτογραφίας'],
                                ['gallery', 'Δημοσίευση gallery'],
                                ['communication', 'Επικοινωνία'],
                                ['medical', 'Ιατρικά δεδομένα'],
                                [
                                    'amkaHealthCard',
                                    `${AMKA_CONSENT_CHECKBOX} — συγκατάθεση ΑΜΚΑ`,
                                ],
                            ].map(([key, label]) => (
                                <label
                                    key={key}
                                    className="flex items-center gap-2 text-sm text-ts-fg-soft"
                                >
                                    <input
                                        type="checkbox"
                                        disabled={
                                            !editing || form.data.gdpr_consent === 'locked'
                                        }
                                        checked={!!form.data.gdpr_items?.[key]}
                                        onChange={(e) =>
                                            setGdprItem(key, e.target.checked)
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </Card>
                )}

                {tab === 'progress' && (
                    <Card title="Αναφορές προόδου">
                        <ul className="mb-4 space-y-3 text-sm">
                            {progressReports.map((p) => (
                                <li
                                    key={p.id}
                                    className="rounded border border-ts-border px-3 py-2"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <strong className="text-ts-fg">
                                            {p.meta?.title || p.title || 'Αναφορά'}
                                        </strong>
                                        <button
                                            type="button"
                                            className="text-xs text-ts-muted hover:text-rose-600"
                                            onClick={() => {
                                                if (!confirm('Διαγραφή αναφοράς;')) {
                                                    return;
                                                }
                                                router.delete(
                                                    `/admin/athletes/${athlete.id}/progress/${p.id}`,
                                                    { preserveScroll: true },
                                                );
                                            }}
                                        >
                                            Διαγραφή
                                        </button>
                                    </div>
                                    <div className="mt-0.5 text-ts-muted">
                                        {p.report_date
                                            ? new Date(p.report_date).toLocaleDateString(
                                                  'el-GR',
                                                  {
                                                      day: '2-digit',
                                                      month: 'short',
                                                      year: 'numeric',
                                                  },
                                              )
                                            : '—'}
                                        {p.meta?.rating
                                            ? ` · Βαθμός ${p.meta.rating}/5`
                                            : ''}
                                        {p.meta?.author || p.created_by_name
                                            ? ` · ${p.meta?.author || p.created_by_name}`
                                            : ''}
                                    </div>
                                    {p.body ? (
                                        <p className="mt-1 text-ts-fg">{p.body}</p>
                                    ) : null}
                                </li>
                            ))}
                            {!progressReports.length && (
                                <li className="text-ts-muted">
                                    Δεν υπάρχουν ακόμη καταχωρήσεις.
                                </li>
                            )}
                        </ul>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Ημερομηνία">
                                <input
                                    type="date"
                                    className={inputClass()}
                                    value={progressForm.data.report_date}
                                    onChange={(e) =>
                                        progressForm.setData('report_date', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Βαθμός (1–5)">
                                <input
                                    type="number"
                                    min="1"
                                    max="5"
                                    className={inputClass()}
                                    value={progressForm.data.rating}
                                    onChange={(e) =>
                                        progressForm.setData('rating', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Τίτλος" className="sm:col-span-2">
                                <input
                                    className={inputClass()}
                                    placeholder="π.χ. Τεχνική / φυσική κατάσταση"
                                    value={progressForm.data.title}
                                    onChange={(e) =>
                                        progressForm.setData('title', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Σχόλια" className="sm:col-span-2">
                                <textarea
                                    className={inputClass()}
                                    rows={3}
                                    value={progressForm.data.body}
                                    onChange={(e) =>
                                        progressForm.setData('body', e.target.value)
                                    }
                                />
                            </Field>
                        </div>
                        <button
                            type="button"
                            className="mt-3 rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            onClick={() =>
                                progressForm.post(
                                    route('admin.athletes.progress.store', athlete.id),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            progressForm.reset('body', 'title'),
                                    },
                                )
                            }
                        >
                            Προσθήκη αναφοράς
                        </button>
                    </Card>
                )}

                {tab === 'announcements' && (
                    <Card title="Ανακοινώσεις συλλόγου">
                        <ul className="space-y-2 text-sm">
                            {announcements.map((a) => (
                                <li
                                    key={a.id}
                                    className="rounded border border-ts-border px-3 py-2"
                                >
                                    <div className="font-medium text-ts-fg">{a.title}</div>
                                    <div className="text-xs text-ts-muted">
                                        {a.published_at}
                                    </div>
                                    <div className="mt-1 text-ts-fg-soft">{a.body}</div>
                                </li>
                            ))}
                            {!announcements.length && (
                                <li className="text-ts-muted">Χωρίς ανακοινώσεις.</li>
                            )}
                        </ul>
                    </Card>
                )}

                {tab === 'history' && (
                    <>
                        <Card title="Παρουσίες (τρέχουσα σεζόν)">
                            <p className="text-sm text-ts-muted">
                                Παρουσίες: {attendanceTotals.present} · Απουσίες:{' '}
                                {attendanceTotals.absent} · Σύνολο καταγραφών:{' '}
                                {attendanceTotals.total}
                            </p>
                        </Card>
                        <Card title="Αλλαγές καρτέλας">
                            {changeLogs.length ? (
                                <ul className="max-h-64 space-y-1 overflow-y-auto text-sm">
                                    {changeLogs.map((c) => (
                                        <li
                                            key={c.id}
                                            className="border-b border-ts-border py-1 text-ts-fg-soft"
                                        >
                                            <span className="text-ts-muted">
                                                {c.created_at}
                                            </span>{' '}
                                            <strong>{c.field}</strong>: {c.old_value} →{' '}
                                            {c.new_value}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν καταγεγραμμένες αλλαγές πεδίων.
                                </p>
                            )}
                        </Card>
                    </>
                )}
            </form>

            <footer className="mt-4 flex shrink-0 flex-wrap justify-end gap-2 border-t border-ts-border pt-4">
                {editing ? (
                    <>
                        <button
                            type="button"
                            onClick={() => {
                                form.reset();
                                setEditing(false);
                            }}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg"
                        >
                            Ακύρωση
                        </button>
                        <button
                            type="button"
                            onClick={save}
                            disabled={form.processing}
                            className="rounded-md bg-teal-700 px-3 py-1.5 text-sm text-white hover:bg-teal-800"
                        >
                            {form.processing ? 'Αποθήκευση…' : 'Αποθήκευση Αλλαγών'}
                        </button>
                    </>
                ) : (
                    <>
                        <Link
                            href={route('admin.transactions.index', {
                                athlete_id: athlete.id,
                            })}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-hover"
                        >
                            Συναλλαγή
                        </Link>
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="rounded-md bg-teal-700 px-3 py-1.5 text-sm text-white hover:bg-teal-800"
                        >
                            Επεξεργασία προφίλ
                        </button>
                    </>
                )}
            </footer>
            </div>
        </AdminLayout>
    );
}
