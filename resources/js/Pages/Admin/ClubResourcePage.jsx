import ResourceForm from '@/Components/ResourceForm';
import ResourceTable from '@/Components/ResourceTable';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function formatMissingAttendanceDate(iso) {
    if (!iso) {
        return '—';
    }
    const raw = String(iso).slice(0, 10);
    const d = new Date(`${raw}T12:00:00`);
    if (Number.isNaN(d.getTime())) {
        return raw;
    }
    return d.toLocaleDateString('el-GR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatMissingAttendanceLine(row) {
    const date = formatMissingAttendanceDate(row.date);
    const start = row.start_time || '—';
    const end = row.end_time || '—';
    const className = row.class_name || 'Τμήμα';
    return `${date} - ${start}-${end} - ${className}`;
}

export default function ClubResourcePage({
    title,
    subtitle = null,
    createLabel: createLabelProp = null,
    columns,
    fields,
    items,
    routes,
    filters = { q: '', status: '' },
    filterOptions = null,
    filterLabel = 'Κατάσταση',
    toolbar = [],
    emptyMessage = null,
    missingAttendance = [],
    missingAttendanceCount = 0,
}) {
    const flash = usePage().props.flash;
    const [showCreate, setShowCreate] = useState(() => title === 'Αγώνες');
    const [showRecurring, setShowRecurring] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [filtersCollapsed, setFiltersCollapsed] = useState(false);
    const [recurringDays, setRecurringDays] = useState([]);
    const [recurringStart, setRecurringStart] = useState('18:00');
    const [recurringEnd, setRecurringEnd] = useState('19:30');
    const [weekLabel, setWeekLabel] = useState(() => {
        const now = new Date();
        return now.toLocaleDateString('el-GR', { day: 'numeric', month: 'short', year: 'numeric' });
    });

    const weekdayOptions = [
        { value: 1, label: 'Δευτέρα' },
        { value: 2, label: 'Τρίτη' },
        { value: 3, label: 'Τετάρτη' },
        { value: 4, label: 'Πέμπτη' },
        { value: 5, label: 'Παρασκευή' },
        { value: 6, label: 'Σάββατο' },
        { value: 0, label: 'Κυριακή' },
    ];

    const applyFilters = (patch) => {
        if (!routes?.index) return;
        router.get(
            route(routes.index),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    const isCoaches = title === 'Προπονητές';
    const isStaff = title === 'Προσωπικό';
    const isTrainings = title === 'Προπονήσεις';
    const isSchedule = title === 'Πρόγραμμα';
    const isMatches = title === 'Αγώνες';
    const isWarehouse = title === 'Αποθήκη';
    const isPhotos = title === 'Φωτογραφίες';
    const isPartners = title === 'Συμβεβλημένες Επιχειρήσεις';
    const isProtocol = title === 'Πρωτόκολλο Εγγράφων' || title === 'Πρωτόκολλο εγγράφων';

    const createLabel =
        createLabelProp ||
        (isCoaches
            ? '+ Νέος προπονητής'
            : isStaff
              ? '+ Νέο μέλος'
              : isTrainings
                ? '+ Νέα προπόνηση'
                : isSchedule
                  ? '+ Νέα Ώρα'
                  : isMatches
                    ? '+ Καταχώρηση'
                    : isWarehouse
                      ? '+ Νέο προϊόν'
                      : isPhotos
                        ? 'ΑΝΕΒΑΣΜΑ ΦΩΤΟΓΡΑΦΙΩΝ'
                        : isPartners
                          ? '+ Νέος συνεργάτης'
                          : isProtocol
                            ? '+ Καταχώρηση Εγγράφου'
                            : '+ Νέα εγγραφή');

    const isMembers = title === 'Μέλη';
    const isPeopleList = isCoaches || isStaff || isMembers;
    const peopleBulkCount =
        selectedIds.length > 0 ? selectedIds.length : (items?.data || []).length;
    const btnSecondary =
        'inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-slate-50 disabled:opacity-50';
    const btnPrimary =
        'inline-flex items-center gap-1.5 rounded-md bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800';

    const runPeopleBulkStatus = () => {
        const ids =
            selectedIds.length > 0 ? selectedIds : (items?.data || []).map((r) => r.id);
        if (!ids.length) {
            alert('Δεν υπάρχουν εγγραφές για μαζική αλλαγή.');
            return;
        }
        const choice = window.prompt('Νέα κατάσταση: 1 = Ενεργοί, 0 = Ανενεργοί', '1');
        if (choice === null) {
            return;
        }
        if (!['0', '1', 'true', 'false'].includes(choice)) {
            alert('Μη έγκυρη κατάσταση. Χρησιμοποιήστε 1 ή 0.');
            return;
        }
        router.post(
            route(isCoaches ? 'admin.coaches.bulk' : 'admin.staff.bulk'),
            { ids, is_active: choice },
            { onSuccess: () => setSelectedIds([]) },
        );
    };

    const exportCsv = () => {
        const rows = items?.data || [];
        const keys = (columns || []).map((c) => c.key);
        const header = (columns || []).map((c) => c.label).join(',');
        const lines = rows.map((r) =>
            keys.map((k) => `"${String(r[k] ?? '').replaceAll('"', '""')}"`).join(','),
        );
        const blob = new Blob([[header, ...lines].join('\n')], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${title}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const searchPlaceholder = isWarehouse
        ? 'Αναζήτηση προϊόντων...'
        : isPhotos
          ? 'Αναζήτηση φωτογραφιών...'
          : isPartners
            ? 'Αναζήτηση συνεργατών...'
            : isProtocol
              ? 'Αρ. Πρωτοκόλλου, θέμα, αποστολέας…'
              : isCoaches
                ? 'Αναζήτηση προπονητή...'
                : isStaff
                  ? 'Αναζήτηση μέλους...'
                  : isMembers
                    ? 'Επώνυμο, όνομα, πόλη, email...'
                    : 'Αναζήτηση';

    const formSubmitLabel = isMatches
        ? '+ Καταχώρηση'
        : isTrainings
          ? 'Αποθήκευση'
          : isSchedule
            ? 'Αποθήκευση'
            : isProtocol
              ? 'Καταχώρηση'
              : 'Προσθήκη';

    const formTitle = isMatches
        ? 'Νέος αγώνας'
        : isTrainings
          ? 'Νέα προπόνηση'
          : isSchedule
            ? 'Νέα ώρα προγράμματος'
            : isProtocol
              ? 'Καταχώρηση Εγγράφου'
              : null;

    return (
        <AdminLayout title={title}>
            <Head title={title} />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            {isTrainings && missingAttendanceCount > 0 && (
                <section
                    className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900"
                    aria-label="Προπονήσεις χωρίς παρουσίες"
                >
                    <p className="font-medium">
                        {missingAttendanceCount === 1
                            ? '1 ληγμένη προπόνηση χωρίς παρουσίες'
                            : `${missingAttendanceCount} ληγμένες προπονήσεις χωρίς παρουσίες`}
                    </p>
                    <ul className="mt-2 max-h-40 space-y-1 overflow-y-auto">
                        {missingAttendance.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={route('admin.attendance.index', {
                                        class_id: row.class_id,
                                        date: row.date,
                                    })}
                                    className="text-rose-800 underline hover:text-rose-950"
                                >
                                    {formatMissingAttendanceLine(row)}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-2xl font-semibold text-slate-900">{title}</h2>
                    {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                    {isMatches && !subtitle && (
                        <p className="mt-0.5 text-sm text-slate-500">
                            Προγραμματισμός αγώνων, φύλλο αποτελέσματος και σκορ.
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    {isTrainings && (
                        <>
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() => setShowRecurring((v) => !v)}
                            >
                                Επαναλαμβανόμενες προπονήσεις
                            </button>
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() =>
                                    router.post(route('admin.trainings.remind-tomorrow'))
                                }
                            >
                                Υπενθύμιση αύριο
                            </button>
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() => {
                                    if (!selectedIds.length) {
                                        alert('Επιλέξτε γραμμές για μαζική επεξεργασία.');
                                        return;
                                    }
                                    const status = window.prompt(
                                        'Νέα κατάσταση (scheduled / done / cancelled):',
                                        'done',
                                    );
                                    if (!status) return;
                                    if (!['scheduled', 'done', 'cancelled'].includes(status)) {
                                        alert('Μη έγκυρη κατάσταση.');
                                        return;
                                    }
                                    router.post(route('admin.trainings.bulk'), {
                                        ids: selectedIds,
                                        status,
                                    });
                                }}
                            >
                                Μαζική επεξεργασία
                            </button>
                            <button
                                type="button"
                                className="inline-flex items-center gap-1.5 rounded-md border border-rose-200 bg-white px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50"
                                onClick={() => {
                                    if (!selectedIds.length) {
                                        alert('Επιλέξτε γραμμές για διαγραφή.');
                                        return;
                                    }
                                    if (!confirm(`Διαγραφή ${selectedIds.length} προπονήσεων;`)) return;
                                    selectedIds.forEach((id) => {
                                        router.delete(route(routes.destroy, id), {
                                            preserveScroll: true,
                                        });
                                    });
                                    setSelectedIds([]);
                                }}
                            >
                                Μαζική διαγραφή
                            </button>
                        </>
                    )}
                    {isSchedule && (
                        <>
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() =>
                                    setWeekLabel(
                                        new Date().toLocaleDateString('el-GR', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        }),
                                    )
                                }
                            >
                                Σήμερα
                            </button>
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() => {
                                    if (
                                        !confirm(
                                            'Δημιουργία προπονήσεων από το εβδομαδιαίο πρόγραμμα;',
                                        )
                                    ) {
                                        return;
                                    }
                                    router.post(route('admin.schedule.generate-trainings'), {
                                        days: 7,
                                    });
                                }}
                            >
                                Προπονήσεις από πρόγραμμα
                            </button>
                        </>
                    )}
                    {isProtocol && (
                        <button type="button" className={btnSecondary}>
                            Φίλτρα
                        </button>
                    )}
                    {(isWarehouse || isProtocol) && !isPeopleList && (
                        <>
                            <button type="button" className={btnSecondary} onClick={exportCsv}>
                                ↓ Εξαγωγή
                            </button>
                            {isWarehouse && (
                                <label className={`${btnSecondary} cursor-pointer`}>
                                    ↑ Εισαγωγή
                                    <input type="file" accept=".csv,.xlsx" className="hidden" />
                                </label>
                            )}
                        </>
                    )}
                    {isPeopleList && (isCoaches || isStaff) && (
                        <button
                            type="button"
                            className={btnSecondary}
                            disabled={peopleBulkCount === 0}
                            onClick={runPeopleBulkStatus}
                        >
                            <span aria-hidden>✎</span> Μαζική αλλαγή
                        </button>
                    )}
                    {isWarehouse && (
                        <span className="self-center text-xs text-slate-500">
                            Κινήσεις: Εισαγωγή / Εξαγωγή / Απογραφή
                        </span>
                    )}
                    {!isMatches && (
                        <button
                            type="button"
                            onClick={() => setShowCreate((v) => !v)}
                            className={btnPrimary}
                        >
                            {String(createLabel).startsWith('+')
                                ? createLabel
                                : `+ ${createLabel}`}
                        </button>
                    )}
                </div>
            </div>

            {isProtocol && (
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-base font-semibold text-ts-fg">Μητρώο Εγγράφων</h3>
                    <p className="text-xs text-ts-muted">Δείξε 25 εγγραφές</p>
                </div>
            )}

            {isCoaches && (
                <section className="mb-4 rounded-lg border border-ts-border bg-ts-surface px-4 py-3 text-sm">
                    <p>
                        <strong>Εφαρμογή προπονητή:</strong>{' '}
                        <code className="text-ts-accent">
                            {typeof window !== 'undefined'
                                ? `${window.location.origin}/app/coach`
                                : '/app/coach'}
                        </code>
                    </p>
                    <p className="mt-1 text-ts-muted">
                        0 από {(items?.data || []).length} προπονητές με λογαριασμό ενεργοποίησαν
                        ειδοποιήσεις. Στείλτε SMS/email ή Viber· στο κινητό: προσθήκη στην αρχική
                        οθόνη και μετά «Ενεργοποίηση ειδοποιήσεων».
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                            onClick={() => {
                                const url = `${window.location.origin}/app/coach`;
                                navigator.clipboard?.writeText(url).catch(() => {
                                    window.prompt('Αντιγράψτε τον σύνδεσμο:', url);
                                });
                            }}
                        >
                            Αντιγραφή συνδέσμου
                        </button>
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                        >
                            Υπενθύμιση χωρίς ειδοποιήσεις ({(items?.data || []).length || 0})
                        </button>
                    </div>
                </section>
            )}

            {isSchedule && (
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <label className="block text-xs text-ts-muted">
                        Άθλημα
                        <select
                            className="mt-1 block rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                            defaultValue=""
                        >
                            <option value="">Όλα τα αθλήματα</option>
                        </select>
                    </label>
                    <label className="block text-xs text-ts-muted">
                        Επιλογή Τμήματος
                        <select
                            className="mt-1 block rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                            defaultValue=""
                        >
                            <option value="">Όλα τα τμήματα</option>
                        </select>
                    </label>
                    <label className="block text-xs text-ts-muted">
                        Εβδομάδα
                        <div className="mt-1 flex items-center gap-1">
                            <button
                                type="button"
                                className="rounded-md border border-ts-border px-2 py-1.5 text-sm"
                                onClick={() => {
                                    const d = new Date();
                                    d.setDate(d.getDate() - 7);
                                    setWeekLabel(
                                        d.toLocaleDateString('el-GR', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        }),
                                    );
                                }}
                            >
                                ‹
                            </button>
                            <span className="min-w-[10rem] rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-center text-sm">
                                {weekLabel}
                            </span>
                            <button
                                type="button"
                                className="rounded-md border border-ts-border px-2 py-1.5 text-sm"
                                onClick={() => {
                                    const d = new Date();
                                    d.setDate(d.getDate() + 7);
                                    setWeekLabel(
                                        d.toLocaleDateString('el-GR', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        }),
                                    );
                                }}
                            >
                                ›
                            </button>
                        </div>
                    </label>
                </div>
            )}

            {isPhotos && (
                <div className="mb-4 flex flex-wrap gap-2">
                    <select
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                        defaultValue=""
                    >
                        <option value="">Όλες οι συλλογές</option>
                    </select>
                    <input
                        className="min-w-[180px] flex-1 rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg"
                        placeholder={searchPlaceholder}
                        defaultValue={filters.q || ''}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                applyFilters({ q: e.currentTarget.value });
                            }
                        }}
                    />
                    <select
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                        defaultValue="newest"
                        aria-label="Ταξινόμηση"
                    >
                        <option value="newest">Ταξινόμηση: Νεότερες πρώτες</option>
                        <option value="oldest">Ταξινόμηση: Παλαιότερες πρώτες</option>
                        <option value="name">Ταξινόμηση: Όνομα</option>
                    </select>
                </div>
            )}

            {isWarehouse && (
                <div className="mb-4 flex flex-wrap gap-2">
                    <input
                        className="min-w-[180px] flex-1 rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg"
                        placeholder={searchPlaceholder}
                        defaultValue={filters.q || ''}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                applyFilters({ q: e.currentTarget.value });
                            }
                        }}
                    />
                    <select className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm" defaultValue="">
                        <option value="">Κατηγορία · Όλες</option>
                    </select>
                    <select className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm" defaultValue="">
                        <option value="">Μάρκα · Όλες</option>
                    </select>
                    <select className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm" defaultValue="">
                        <option value="">Κατάσταση · Όλα</option>
                    </select>
                </div>
            )}

            {(routes?.index || filterOptions) && !isSchedule && !isPhotos && !isWarehouse && (
                <>
                    {filtersCollapsed && isPeopleList ? (
                        <div className="mb-2">
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() => setFiltersCollapsed(false)}
                            >
                                + Εμφάνιση φίλτρων
                            </button>
                        </div>
                    ) : (
                        <div className="mb-2 flex flex-wrap items-end gap-2">
                            <label className="relative min-w-[200px] flex-1">
                                <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    ⌕
                                </span>
                                <input
                                    className="w-full rounded-md border border-slate-300 bg-white py-1.5 pe-3 ps-9 text-sm text-slate-900"
                                    placeholder={searchPlaceholder}
                                    defaultValue={filters.q || ''}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            applyFilters({ q: e.currentTarget.value });
                                        }
                                    }}
                                />
                            </label>
                            {isStaff && (
                                <label className="block text-sm">
                                    <span className="mb-0.5 block text-xs font-medium text-slate-600">
                                        Ρόλος
                                    </span>
                                    <select
                                        className="min-w-[9rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm"
                                        defaultValue=""
                                    >
                                        <option value="">Όλοι οι ρόλοι</option>
                                        <option value="admin">Διαχειριστής</option>
                                        <option value="coach">Προπονητής</option>
                                        <option value="secretariat">Γραμματεία</option>
                                    </select>
                                </label>
                            )}
                            {isCoaches && (
                                <label className="block text-sm">
                                    <span className="mb-0.5 block text-xs font-medium text-slate-600">
                                        Άθλημα
                                    </span>
                                    <select
                                        className="min-w-[10rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm"
                                        defaultValue={filters.sport || ''}
                                        onChange={(e) => applyFilters({ sport: e.target.value })}
                                    >
                                        <option value="">Όλα τα αθλήματα</option>
                                    </select>
                                </label>
                            )}
                            {filterOptions?.status && (
                                <label className="block text-sm">
                                    <span className="mb-0.5 block text-xs font-medium text-slate-600">
                                        {filterLabel}
                                    </span>
                                    <select
                                        className="min-w-[8rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900"
                                        value={filters.status || ''}
                                        onChange={(e) => applyFilters({ status: e.target.value })}
                                    >
                                        <option value="">Όλα</option>
                                        {filterOptions.status.map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            {isCoaches && (
                                <label className="block text-sm">
                                    <span className="mb-0.5 block text-xs font-medium text-slate-600">
                                        Ειδοποιήσεις app
                                    </span>
                                    <select
                                        className="min-w-[9rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm"
                                        defaultValue=""
                                    >
                                        <option value="">Όλα</option>
                                        <option value="on">Ενεργές</option>
                                        <option value="off">Χωρίς ειδοποιήσεις</option>
                                    </select>
                                </label>
                            )}
                            {isPeopleList && (isCoaches || isStaff) && (
                                <button
                                    type="button"
                                    className={btnSecondary}
                                    disabled={peopleBulkCount === 0}
                                    onClick={runPeopleBulkStatus}
                                >
                                    <span aria-hidden>✎</span> Μαζική αλλαγή
                                    {peopleBulkCount > 0 ? ` (${peopleBulkCount})` : ''}
                                </button>
                            )}
                            {isPeopleList && (
                                <>
                                    <button
                                        type="button"
                                        className={btnSecondary}
                                        onClick={exportCsv}
                                    >
                                        ↓ Εξαγωγή
                                    </button>
                                    {(isCoaches || isStaff) && (
                                        <label className={`${btnSecondary} cursor-pointer`}>
                                            ↑ Εισαγωγή
                                            <input
                                                type="file"
                                                accept=".csv,.xlsx"
                                                className="hidden"
                                            />
                                        </label>
                                    )}
                                    <button
                                        type="button"
                                        className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 bg-slate-100 text-slate-600 hover:bg-slate-200"
                                        aria-label="Απόκρυψη φίλτρων"
                                        onClick={() => setFiltersCollapsed(true)}
                                    >
                                        −
                                    </button>
                                </>
                            )}
                        </div>
                    )}
                    {isPeopleList && (isCoaches || isStaff) && (
                        <p className="mb-3 text-sm text-slate-500">
                            Μαζική αλλαγή: επιλέξτε γραμμές και πατήστε το κουμπί πάνω δεξιά (ή δίπλα
                            στα φίλτρα). Αν δεν τσεκάρετε γραμμές, ενημερώνονται όλοι όσοι φαίνονται
                            στη λίστα.
                        </p>
                    )}
                </>
            )}

            {showRecurring && isTrainings && (
                <section className="mb-4 rounded-lg border border-ts-border bg-ts-surface p-4">
                    <h3 className="text-sm font-semibold text-ts-fg">Επαναλαμβανόμενες προπονήσεις</h3>
                    <p className="mt-1 text-xs text-ts-muted">
                        Δημιουργία για τις επόμενες 4 εβδομάδες · παράλειψη διπλοτύπων
                    </p>
                    <div className="mt-3 flex flex-wrap gap-3 text-sm">
                        {weekdayOptions.map((day) => (
                            <label key={day.value} className="flex items-center gap-1.5 text-ts-muted">
                                <input
                                    type="checkbox"
                                    checked={recurringDays.includes(day.value)}
                                    onChange={(e) => {
                                        setRecurringDays((prev) =>
                                            e.target.checked
                                                ? [...prev, day.value]
                                                : prev.filter((v) => v !== day.value),
                                        );
                                    }}
                                />
                                {day.label}
                            </label>
                        ))}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <input
                            type="time"
                            className="rounded-md border border-ts-border px-2 py-1.5 text-sm"
                            value={recurringStart}
                            onChange={(e) => setRecurringStart(e.target.value)}
                            aria-label="Ώρα έναρξης"
                        />
                        <input
                            type="time"
                            className="rounded-md border border-ts-border px-2 py-1.5 text-sm"
                            value={recurringEnd}
                            onChange={(e) => setRecurringEnd(e.target.value)}
                            aria-label="Ώρα λήξης"
                        />
                        <button
                            type="button"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            onClick={() => {
                                if (!recurringDays.length) {
                                    alert('Επιλέξτε τουλάχιστον μία ημέρα.');
                                    return;
                                }
                                router.post(route('admin.trainings.recurring'), {
                                    weekdays: recurringDays,
                                    starts_time: recurringStart,
                                    ends_time: recurringEnd,
                                    weeks: 4,
                                    title: 'Προπόνηση',
                                });
                            }}
                        >
                            Δημιουργία σειράς
                        </button>
                    </div>
                </section>
            )}

            {(showCreate || isMatches) && (
                <div className="mb-4">
                    {formTitle && (
                        <h3 className="mb-2 text-sm font-semibold text-ts-fg">{formTitle}</h3>
                    )}
                    <ResourceForm
                        fields={fields}
                        storeRoute={routes.store}
                        submitLabel={formSubmitLabel}
                        formTitle={formTitle}
                    />
                </div>
            )}

            {(isTrainings || isCoaches || isStaff) && (items?.data?.length ?? 0) > 0 && (
                <div className="mb-2 flex items-center gap-2 text-sm">
                    <label className="flex items-center gap-2 text-ts-muted">
                        <input
                            type="checkbox"
                            checked={
                                selectedIds.length > 0 &&
                                selectedIds.length === (items?.data?.length || 0)
                            }
                            onChange={(e) => {
                                setSelectedIds(
                                    e.target.checked ? (items?.data || []).map((r) => r.id) : [],
                                );
                            }}
                        />
                        Επιλογή όλων
                    </label>
                    {selectedIds.length > 0 && (
                        <span className="text-ts-muted">{selectedIds.length} επιλεγμένες</span>
                    )}
                </div>
            )}

            <div className="max-h-[min(calc(2.75rem+20*2.5rem),calc(100vh-16rem))] overflow-auto overscroll-contain">
                <ResourceTable
                    columns={columns}
                    rows={items?.data ?? []}
                    fields={fields}
                    updateRoute={routes.update}
                    destroyRoute={routes.destroy}
                    emptyMessage={
                        emptyMessage ||
                        (isCoaches
                            ? 'Πάτα «Νέος προπονητής» για να προσθέσεις τον πρώτο.'
                            : 'Δεν υπάρχουν εγγραφές.')
                    }
                    selectable={isTrainings || isCoaches || isStaff}
                    selectedIds={selectedIds}
                    onToggleSelect={(id) =>
                        setSelectedIds((prev) =>
                            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
                        )
                    }
                />
            </div>
            <p className="mt-3 text-xs text-ts-muted">
                Σύνολο {items?.total ?? items?.data?.length ?? 0}
            </p>
        </AdminLayout>
    );
}
