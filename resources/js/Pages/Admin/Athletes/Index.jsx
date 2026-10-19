import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const statusLabel = {
    active: 'Ενεργός',
    inactive: 'Ανενεργός',
    trial: 'Δοκιμαστικός',
};

export default function Index({
    rows,
    filters,
    classes,
    healthNoticeRows = [],
    pendingApplications = [],
}) {
    const flash = usePage().props.flash;
    const [selected, setSelected] = useState([]);
    const [showCreate, setShowCreate] = useState(false);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [editingAppId, setEditingAppId] = useState(null);
    const [editDraft, setEditDraft] = useState(null);
    const [healthNoticeBusy, setHealthNoticeBusy] = useState(false);
    const [filtersCollapsed, setFiltersCollapsed] = useState(false);
    const [importHelpOpen, setImportHelpOpen] = useState(false);

    const btnSecondary =
        'inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-slate-50 disabled:opacity-50';
    const btnPrimary =
        'inline-flex items-center gap-1.5 rounded-md bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800';

    const applicationKindLabel = (kind) => {
        if (kind === 'trial') return 'Δοκιμαστική';
        if (kind === 'waitlist') return 'Αναμονή';
        return 'Πλήρης εγγραφή';
    };

    const startEditApp = (app) => {
        setEditingAppId(app.id);
        setEditDraft({
            first_name: app.first_name || '',
            last_name: app.last_name || '',
            guardian_name: app.guardian_name || '',
            guardian_phone: app.guardian_phone || '',
            email: app.email || '',
            class_id: app.class_id || '',
            type: app.type || 'full',
            notes: app.notes || '',
        });
    };

    const cancelEditApp = () => {
        setEditingAppId(null);
        setEditDraft(null);
    };

    const saveEditApp = (appId) => {
        if (!editDraft) return;
        router.patch(route('admin.athletes.applications.update', appId), editDraft, {
            preserveScroll: true,
            onSuccess: () => cancelEditApp(),
        });
    };

    const sendHealthReminders = () => {
        setHealthNoticeBusy(true);
        router.post(
            route('admin.athletes.health-reminders'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setHealthNoticeBusy(false),
            },
        );
    };

    const createForm = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        status: 'active',
        monthly_fee: '0',
        sport: '',
    });

    const bulkForm = useForm({
        ids: [],
        status: '',
        sport: '',
        gender: '',
        class_id: '',
        health_card: '',
    });

    const applyFilters = (patch) => {
        router.get(
            route('admin.athletes.index'),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    const pageIds = useMemo(() => (rows.data || []).map((r) => r.id), [rows.data]);
    const bulkCount = selected.length > 0 ? selected.length : pageIds.length;
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

    const toggleAll = () => {
        setSelected(allSelected ? [] : pageIds);
    };

    const toggleOne = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const openBulk = () => {
        const ids = selected.length > 0 ? selected : pageIds;
        if (ids.length === 0) {
            return;
        }
        bulkForm.setData({
            ids,
            status: '',
            sport: '',
            gender: '',
            class_id: '',
            health_card: '',
        });
        setBulkOpen(true);
    };

    const submitBulk = (e) => {
        e.preventDefault();
        bulkForm.post(route('admin.athletes.bulk'), {
            preserveScroll: true,
            onSuccess: () => {
                setBulkOpen(false);
                setSelected([]);
            },
        });
    };

    const expiredCount = healthNoticeRows.filter((r) => r.status === 'expired').length;
    const soonCount = healthNoticeRows.filter((r) => r.status === 'soon').length;

    return (
        <AdminLayout title="Αθλητές">
            <Head title="Αθλητές" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-2xl font-semibold text-slate-900">Αθλητές</h2>
                    <p className="mt-0.5 text-sm text-slate-500">
                        Μητρώο αθλητών, γονείς και σύνδεση με τμήματα.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={openBulk}
                        disabled={bulkCount === 0}
                        className={btnSecondary}
                    >
                        <span aria-hidden>✎</span> Μαζική αλλαγή
                    </button>
                    <button type="button" onClick={() => setShowCreate((v) => !v)} className={btnPrimary}>
                        + Νέος αθλητής
                    </button>
                </div>
            </div>

            {healthNoticeRows.length > 0 && (
                <section className="mb-4 rounded-lg border border-ts-border bg-ts-surface px-4 py-3 text-sm text-ts-fg">
                    <p>
                        <strong>Κάρτες υγείας:</strong> {expiredCount} ληγμένες, {soonCount} λήγουν
                        σε 30 ημέρες.
                    </p>
                    <p className="mt-1 text-ts-muted">
                        Στείλτε υπενθύμιση στους γονείς (email/SMS/εφαρμογή) και σύνοψη στη
                        γραμματεία. Το πολύ μία φορά την ημέρα ανά αθλητή.
                    </p>
                    <button
                        type="button"
                        disabled={healthNoticeBusy}
                        className="mt-3 rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-hover disabled:opacity-50"
                        onClick={sendHealthReminders}
                    >
                        {healthNoticeBusy ? 'Αποστολή…' : 'Υπενθύμιση λήξεων'}
                    </button>
                </section>
            )}

            {pendingApplications.length > 0 && (
                <section
                    id="pending-applications"
                    className="mb-4 rounded-lg border border-ts-border bg-ts-surface p-4"
                >
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-base font-semibold text-ts-fg">
                            Εκκρεμείς αιτήσεις εγγραφής
                        </h3>
                        <span className="rounded-full bg-rose-600 px-2 py-0.5 text-xs font-semibold text-white">
                            {pendingApplications.length}
                        </span>
                    </div>
                    <p className="mb-3 text-sm text-ts-muted">
                        Από δημόσια φόρμα. Οι αιτήσεις μένουν σε αναμονή μέχρι να πατήσετε Έγκριση (ενεργός αθλητής).
                    </p>
                    <div className="space-y-2">
                        {pendingApplications.map((app) => {
                            const editing = editingAppId === app.id && editDraft;
                            const classLabel =
                                classes.find((c) => c.id === (editing ? editDraft.class_id : app.class_id))
                                    ?.name || '—';
                            return (
                                <article
                                    key={app.id}
                                    className="rounded-md border border-ts-border px-3 py-3"
                                >
                                    {editing ? (
                                        <div className="space-y-3">
                                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Επώνυμο</span>
                                                    <input
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.last_name}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                last_name: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </label>
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Όνομα</span>
                                                    <input
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.first_name}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                first_name: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </label>
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Γονέας</span>
                                                    <input
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.guardian_name}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                guardian_name: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </label>
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Τηλέφωνο</span>
                                                    <input
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.guardian_phone}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                guardian_phone: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </label>
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Email</span>
                                                    <input
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.email}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                email: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </label>
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Τμήμα</span>
                                                    <select
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.class_id}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                class_id: e.target.value,
                                                            })
                                                        }
                                                    >
                                                        <option value="">—</option>
                                                        {classes.map((c) => (
                                                            <option key={c.id} value={c.id}>
                                                                {c.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                                <label className="block text-sm">
                                                    <span className="text-ts-muted">Τύπος</span>
                                                    <select
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.type}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                type: e.target.value,
                                                            })
                                                        }
                                                    >
                                                        <option value="full">Πλήρης εγγραφή</option>
                                                        <option value="trial">Δοκιμαστική</option>
                                                        <option value="waitlist">Αναμονή</option>
                                                    </select>
                                                </label>
                                                <label className="block text-sm sm:col-span-2 lg:col-span-1">
                                                    <span className="text-ts-muted">Σχόλια</span>
                                                    <input
                                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                                        value={editDraft.notes}
                                                        onChange={(e) =>
                                                            setEditDraft({
                                                                ...editDraft,
                                                                notes: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </label>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => saveEditApp(app.id)}
                                                    className="rounded-md bg-ts-accent px-2.5 py-1 text-xs text-ts-accent-fg"
                                                >
                                                    Αποθήκευση αλλαγών
                                                </button>
                                                <Link
                                                    href={route(
                                                        'admin.athletes.applications.approve',
                                                        app.id,
                                                    )}
                                                    method="post"
                                                    as="button"
                                                    className="rounded-md bg-emerald-600 px-2.5 py-1 text-xs text-white hover:bg-emerald-500"
                                                >
                                                    Έγκριση
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={cancelEditApp}
                                                    className="rounded-md border border-ts-border px-2.5 py-1 text-xs"
                                                >
                                                    Ακύρωση
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="grid flex-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                                <div>
                                                    <div className="font-medium text-ts-fg">
                                                        {app.last_name} {app.first_name}
                                                    </div>
                                                    <div className="text-ts-muted">
                                                        {app.email || '—'}
                                                    </div>
                                                    {app.notes ? (
                                                        <div className="text-ts-muted">{app.notes}</div>
                                                    ) : null}
                                                </div>
                                                <div>
                                                    <span className="text-ts-muted">Γονέας</span>
                                                    <div>{app.guardian_name || '—'}</div>
                                                    <div className="text-ts-muted">
                                                        {app.guardian_phone || '—'}
                                                    </div>
                                                </div>
                                                <div>
                                                    <span className="text-ts-muted">Τμήμα</span>
                                                    <div>{classLabel}</div>
                                                </div>
                                                <div>
                                                    <span className="text-ts-muted">Τύπος / Ημ/νία</span>
                                                    <div>{applicationKindLabel(app.type)}</div>
                                                    <div className="text-ts-muted">
                                                        {(app.created_at || '').slice(0, 10) || '—'}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => startEditApp(app)}
                                                    className="rounded-md border border-ts-border px-2.5 py-1 text-xs text-ts-fg-soft hover:bg-ts-hover"
                                                >
                                                    Επεξεργασία
                                                </button>
                                                <Link
                                                    href={route(
                                                        'admin.athletes.applications.approve',
                                                        app.id,
                                                    )}
                                                    method="post"
                                                    as="button"
                                                    className="rounded-md bg-emerald-600 px-2.5 py-1 text-xs text-white hover:bg-emerald-500"
                                                >
                                                    Έγκριση
                                                </Link>
                                                <Link
                                                    href={route(
                                                        'admin.athletes.applications.reject',
                                                        app.id,
                                                    )}
                                                    method="post"
                                                    as="button"
                                                    className="rounded-md border border-ts-border px-2.5 py-1 text-xs text-ts-fg-soft hover:bg-ts-hover"
                                                >
                                                    Απόρριψη
                                                </Link>
                                            </div>
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                </section>
            )}

            {!filtersCollapsed && (
                <div className="mb-2 flex flex-wrap items-end gap-2">
                    <label className="relative min-w-[220px] flex-1">
                        <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-slate-400">
                            ⌕
                        </span>
                        <input
                            className="w-full rounded-md border border-slate-300 bg-white py-1.5 pe-3 ps-9 text-sm text-slate-900"
                            placeholder="Αναζήτηση αθλητή ή γονέα..."
                            defaultValue={filters.q}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyFilters({ q: e.currentTarget.value });
                                }
                            }}
                        />
                    </label>
                    <label className="block text-sm">
                        <span className="mb-0.5 block text-xs font-medium text-slate-600">Άθλημα</span>
                        <select
                            className="min-w-[10rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900"
                            value={filters.sport || ''}
                            onChange={(e) => applyFilters({ sport: e.target.value })}
                        >
                            <option value="">Όλα τα αθλήματα</option>
                            {[...new Set((rows.data || []).map((r) => r.sport).filter(Boolean))].map(
                                (s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ),
                            )}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="mb-0.5 block text-xs font-medium text-slate-600">Τμήμα</span>
                        <select
                            className="min-w-[10rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900"
                            value={filters.class_id}
                            onChange={(e) => applyFilters({ class_id: e.target.value })}
                        >
                            <option value="">Όλα τα τμήματα</option>
                            <option value="none">Χωρίς τμήμα</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="mb-0.5 block text-xs font-medium text-slate-600">Κατάσταση</span>
                        <select
                            className="min-w-[8rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900"
                            value={filters.status}
                            onChange={(e) => applyFilters({ status: e.target.value })}
                        >
                            <option value="">Όλα</option>
                            <option value="active">Ενεργός</option>
                            <option value="trial">Δοκιμαστικός</option>
                            <option value="inactive">Ανενεργός</option>
                        </select>
                    </label>
                    <button
                        type="button"
                        onClick={openBulk}
                        disabled={bulkCount === 0}
                        className={btnSecondary}
                    >
                        <span aria-hidden>✎</span> Μαζική αλλαγή
                        {bulkCount > 0 ? ` (${bulkCount})` : ''}
                    </button>
                    <a
                        href={route('admin.athletes.export', {
                            status: filters.status || undefined,
                            sport: filters.sport || undefined,
                            class_id: filters.class_id || undefined,
                            q: filters.q || undefined,
                        })}
                        className={btnSecondary}
                    >
                        ↓ Εξαγωγή
                    </a>
                    <label className={`${btnSecondary} cursor-pointer`}>
                        ↑ Εισαγωγή
                        <input
                            type="file"
                            accept=".csv,.xlsx,text/csv"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (!file) return;
                                const data = new FormData();
                                data.append('file', file);
                                router.post(route('admin.athletes.import'), data, {
                                    forceFormData: true,
                                });
                                e.target.value = '';
                            }}
                        />
                    </label>
                    <button
                        type="button"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 bg-slate-100 text-slate-600 hover:bg-slate-200"
                        aria-label="Απόκρυψη φίλτρων"
                        onClick={() => setFiltersCollapsed(true)}
                    >
                        −
                    </button>
                    <button
                        type="button"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-500 hover:bg-slate-50"
                        aria-label="Οδηγίες εισαγωγής αθλητών"
                        onClick={() => setImportHelpOpen((v) => !v)}
                    >
                        ?
                    </button>
                </div>
            )}
            {filtersCollapsed && (
                <div className="mb-2">
                    <button
                        type="button"
                        className={btnSecondary}
                        onClick={() => setFiltersCollapsed(false)}
                    >
                        + Εμφάνιση φίλτρων
                    </button>
                </div>
            )}
            {importHelpOpen && (
                <div className="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    Η εισαγωγή δέχεται CSV/XLSX με στήλες επώνυμο, όνομα, email, τηλέφωνο, άθλημα,
                    κατάσταση. Κάντε πρώτα εξαγωγή για το σωστό πρότυπο στηλών.
                </div>
            )}
            <p className="mb-3 text-sm text-slate-500">
                Μαζική αλλαγή: επιλέξτε <strong className="font-semibold text-slate-700">Τμήμα</strong>{' '}
                και πατήστε το κουμπί πάνω δεξιά (ή δίπλα στα φίλτρα). Αν δεν τσεκάρετε γραμμές,
                ενημερώνονται όλοι όσοι φαίνονται στη λίστα.
            </p>

            {showCreate && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        createForm.post(route('admin.athletes.store'));
                    }}
                    className="mb-6 grid gap-2 rounded-lg border border-ts-border bg-ts-surface p-4 sm:grid-cols-3"
                >
                    <p className="sm:col-span-3 text-xs font-semibold uppercase tracking-wider text-ts-muted">
                        Νέος αθλητής
                    </p>
                    <input
                        required
                        placeholder="Όνομα"
                        className="rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm text-ts-fg"
                        value={createForm.data.first_name}
                        onChange={(e) => createForm.setData('first_name', e.target.value)}
                    />
                    <input
                        required
                        placeholder="Επώνυμο"
                        className="rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm text-ts-fg"
                        value={createForm.data.last_name}
                        onChange={(e) => createForm.setData('last_name', e.target.value)}
                    />
                    <select
                        className="rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm text-ts-fg"
                        value={createForm.data.status}
                        onChange={(e) => createForm.setData('status', e.target.value)}
                    >
                        <option value="active">Ενεργός</option>
                        <option value="trial">Δοκιμαστικός</option>
                        <option value="inactive">Ανενεργός</option>
                    </select>
                    <button
                        type="submit"
                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg sm:col-span-3 sm:w-fit"
                    >
                        Δημιουργία & άνοιγμα προφίλ
                    </button>
                </form>
            )}

            {bulkOpen && (
                <form
                    onSubmit={submitBulk}
                    className="mb-6 grid gap-2 rounded-lg border border-sky-200 bg-sky-50 p-4 sm:grid-cols-3"
                >
                    <p className="sm:col-span-3 text-sm font-semibold text-sky-950">
                        Μαζική αλλαγή ({bulkForm.data.ids.length})
                    </p>
                    <select
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        value={bulkForm.data.status}
                        onChange={(e) => bulkForm.setData('status', e.target.value)}
                    >
                        <option value="">Κατάσταση — χωρίς αλλαγή</option>
                        <option value="active">Ενεργός</option>
                        <option value="trial">Δοκιμαστικός</option>
                        <option value="inactive">Ανενεργός</option>
                    </select>
                    <select
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        value={bulkForm.data.class_id}
                        onChange={(e) => bulkForm.setData('class_id', e.target.value)}
                    >
                        <option value="">Τμήμα — χωρίς αλλαγή</option>
                        <option value="none">Χωρίς τμήμα</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        value={bulkForm.data.gender}
                        onChange={(e) => bulkForm.setData('gender', e.target.value)}
                    >
                        <option value="">Φύλο — χωρίς αλλαγή</option>
                        <option value="male">Αγόρι</option>
                        <option value="female">Κορίτσι</option>
                        <option value="other">Άλλο</option>
                    </select>
                    <input
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        placeholder="Άθλημα"
                        value={bulkForm.data.sport}
                        onChange={(e) => bulkForm.setData('sport', e.target.value)}
                    />
                    <select
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        value={bulkForm.data.health_card}
                        onChange={(e) => bulkForm.setData('health_card', e.target.value)}
                    >
                        <option value="">Κάρτα υγείας — χωρίς αλλαγή</option>
                        <option value="1">Ναι</option>
                        <option value="0">Όχι</option>
                    </select>
                    <div className="flex gap-2 sm:col-span-3">
                        <button
                            type="submit"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                        >
                            Εφαρμογή
                        </button>
                        <button
                            type="button"
                            onClick={() => setBulkOpen(false)}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                        >
                            Ακύρωση
                        </button>
                    </div>
                </form>
            )}

            <div className="max-h-[min(calc(2.75rem+20*2.5rem),calc(100vh-16rem))] overflow-auto overscroll-contain rounded-lg border border-slate-200 bg-white">
                <table className="min-w-full text-sm">
                    <thead className="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th className="w-10 px-3 py-2.5">
                                <input
                                    type="checkbox"
                                    checked={allSelected}
                                    onChange={toggleAll}
                                    aria-label="Επιλογή όλων"
                                />
                            </th>
                            <th className="px-3 py-2.5">Επώνυμο</th>
                            <th className="px-3 py-2.5">Όνομα</th>
                            <th className="px-3 py-2.5">Άθλημα</th>
                            <th className="px-3 py-2.5">Τμήμα</th>
                            <th className="px-3 py-2.5">Γονέας</th>
                            <th className="px-3 py-2.5">Κατάσταση</th>
                            <th className="px-3 py-2.5 text-right" />
                        </tr>
                    </thead>
                    <tbody>
                        {(rows.data || []).map((r) => (
                            <tr
                                key={r.id}
                                className="cursor-pointer border-t border-slate-100 hover:bg-slate-50/80"
                                onClick={() => router.visit(route('admin.athletes.show', r.id))}
                            >
                                <td
                                    className="px-3 py-2.5"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(r.id)}
                                        onChange={() => toggleOne(r.id)}
                                        aria-label={`Επιλογή ${r.last_name}`}
                                    />
                                </td>
                                <td className="px-3 py-2.5">
                                    <div className="flex items-center gap-2.5">
                                        {r.photo_url ? (
                                            <img
                                                src={r.photo_url}
                                                alt=""
                                                className="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200"
                                            />
                                        ) : (
                                            <span
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-400 ring-1 ring-slate-200"
                                                aria-hidden
                                            >
                                                👤
                                            </span>
                                        )}
                                        <Link
                                            href={route('admin.athletes.show', r.id)}
                                            className="font-semibold text-slate-900 hover:text-teal-700"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {r.last_name}
                                        </Link>
                                    </div>
                                </td>
                                <td className="px-3 py-2.5">
                                    <Link
                                        href={route('admin.athletes.show', r.id)}
                                        className="font-semibold text-slate-900 hover:text-teal-700"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        {r.first_name}
                                    </Link>
                                    {r.email ? (
                                        <div className="text-xs text-slate-500">{r.email}</div>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2.5 text-slate-700">{r.sport || '—'}</td>
                                <td className="px-3 py-2.5 text-slate-700">
                                    {r.class_names || '—'}
                                </td>
                                <td className="px-3 py-2.5">
                                    <div className="text-slate-800">{r.guardian_name || '—'}</div>
                                    {r.guardian_phone ? (
                                        <div className="text-xs text-slate-500">
                                            {r.guardian_phone}
                                        </div>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2.5">
                                    <span
                                        className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                            r.status === 'active'
                                                ? 'bg-emerald-50 text-emerald-800'
                                                : r.status === 'trial'
                                                  ? 'bg-amber-50 text-amber-800'
                                                  : 'bg-slate-100 text-slate-600'
                                        }`}
                                    >
                                        {statusLabel[r.status] || r.status}
                                    </span>
                                </td>
                                <td
                                    className="px-3 py-2.5 text-right"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <div className="flex justify-end gap-1">
                                        <Link
                                            href={`${route('admin.athletes.show', r.id)}?edit=1`}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                            title="Επεξεργασία"
                                            aria-label="Επεξεργασία"
                                        >
                                            ✎
                                        </Link>
                                        <Link
                                            href={route('admin.athletes.destroy', r.id)}
                                            method="delete"
                                            as="button"
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                                            title="Διαγραφή"
                                            aria-label="Διαγραφή"
                                            onClick={(e) => {
                                                if (
                                                    !confirm(
                                                        `Διαγραφή αθλητή ${r.last_name} ${r.first_name};`,
                                                    )
                                                ) {
                                                    e.preventDefault();
                                                }
                                            }}
                                        >
                                            🗑
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {!rows.data?.length && (
                            <tr>
                                <td colSpan={8} className="px-3 py-8 text-center text-slate-500">
                                    Δεν βρέθηκαν αθλητές με αυτά τα φίλτρα.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <p className="mt-3 text-xs text-ts-muted">Σύνολο {rows.total ?? rows.data?.length ?? 0}</p>
        </AdminLayout>
    );
}
