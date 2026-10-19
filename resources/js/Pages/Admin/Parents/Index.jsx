import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const STATUS_LABELS = {
    active: 'Ενεργός',
    pending: 'Πρόσκληση εκκρεμεί',
    not_invited: 'Δεν έχει προσκληθεί',
};

function statusBadgeClass(status) {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-800';
    }
    if (status === 'pending') {
        return 'bg-orange-50 text-orange-800';
    }
    return 'bg-slate-100 text-slate-600';
}

function appBadge(row) {
    if (row.receives_push) {
        return {
            label: 'Ειδοποιήσεις OK',
            className: 'bg-emerald-50 text-emerald-800',
        };
    }
    if (row.has_account || row.parent_user_id) {
        return {
            label: 'Χωρίς ειδοποιήσεις',
            className: 'bg-orange-50 text-orange-800',
        };
    }
    return {
        label: 'Χωρίς λογαριασμό',
        className: 'bg-orange-50 text-orange-800',
    };
}

export default function Index({
    directory = [],
    athletes = [],
    classes = [],
    parentAppUrl = '/login?app=parent',
    routes,
    filters = { q: '', status: '', push: '', class_id: '' },
}) {
    const flash = usePage().props.flash;
    const [inviteOpen, setInviteOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const [selected, setSelected] = useState([]);

    const form = useForm({
        parent_email: '',
        parent_name: '',
        parent_password: '',
        athlete_id: '',
        relation: 'γονέας',
    });

    const apply = (patch) => {
        router.get(
            route(routes.index),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    const withoutPush = useMemo(
        () => directory.filter((r) => !r.receives_push).length,
        [directory],
    );

    const pageRows = directory;
    const pageIds = useMemo(() => pageRows.map((r) => r.id), [pageRows]);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(parentAppUrl);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            window.prompt('Αντιγράψτε τον σύνδεσμο:', parentAppUrl);
        }
    };

    const exportCsv = () => {
        const header = ['Ονοματεπώνυμο', 'Αθλητές', 'Email', 'Κατάσταση'];
        const lines = directory.map((r) =>
            [
                r.full_name,
                (r.athletes || []).map((a) => a.label).join('; '),
                r.email,
                STATUS_LABELS[r.status] || r.status,
            ]
                .map((c) => `"${String(c).replaceAll('"', '""')}"`)
                .join(','),
        );
        const blob = new Blob([[header.join(','), ...lines].join('\n')], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `goneis-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const openInviteFor = (row) => {
        setInviteOpen(true);
        form.setData({
            ...form.data,
            parent_email: row.email || '',
            parent_name: row.full_name || '',
            athlete_id: row.athletes?.[0]?.id || '',
        });
    };

    return (
        <AdminLayout title="Γονείς">
            <Head title="Γονείς" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-2xl font-semibold text-slate-900">Γονείς</h2>
                    <p className="mt-0.5 text-sm text-slate-500">
                        Διαχείριση γονέων και σύνδεση με αθλητές
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setInviteOpen((v) => !v)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800"
                >
                    + Πρόσκληση γονέα
                </button>
            </div>

            <section className="mb-4 rounded-lg border border-sky-100 bg-sky-50/50 px-4 py-3">
                <p className="text-sm text-ts-fg">
                    <strong>Εφαρμογή γονέα:</strong>{' '}
                    <code className="text-ts-accent">{parentAppUrl}</code>
                </p>
                <p className="mt-1 text-sm text-ts-muted">
                    {directory.filter((r) => r.receives_push).length} από{' '}
                    {directory.filter((r) => r.has_account || r.parent_user_id || r.email).length}{' '}
                    γονείς με λογαριασμό ενεργοποίησαν ειδοποιήσεις. Στείλτε SMS/email ή Viber· στο
                    κινητό: προσθήκη στην αρχική οθόνη και μετά «Ενεργοποίηση ειδοποιήσεων».
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={copyLink}
                        className="rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm hover:bg-ts-hover"
                    >
                        {copied ? 'Αντιγράφηκε' : 'Αντιγραφή συνδέσμου'}
                    </button>
                    <button
                        type="button"
                        className="rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm hover:bg-ts-hover"
                    >
                        Υπενθύμιση χωρίς ειδοποιήσεις ({withoutPush})
                    </button>
                </div>
            </section>

            <div className="mb-3 flex flex-wrap items-end gap-2">
                <label className="relative min-w-[220px] flex-1">
                    <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-slate-400">
                        ⌕
                    </span>
                    <input
                        className="w-full rounded-md border border-slate-300 bg-white py-1.5 pe-3 ps-9 text-sm text-slate-900"
                        placeholder="Αναζήτηση γονέα ή email..."
                        defaultValue={filters.q || ''}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') apply({ q: e.currentTarget.value });
                        }}
                    />
                </label>
                <label className="block text-sm">
                    <span className="mb-0.5 block text-xs font-medium text-slate-600">Κατάσταση</span>
                    <select
                        className="min-w-[9rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm"
                        value={filters.status || ''}
                        onChange={(e) => apply({ status: e.target.value })}
                    >
                        <option value="">Όλα</option>
                        <option value="active">Ενεργός</option>
                        <option value="pending">Πρόσκληση εκκρεμεί</option>
                        <option value="not_invited">Δεν έχει προσκληθεί</option>
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-0.5 block text-xs font-medium text-slate-600">
                        Ειδοποιήσεις app
                    </span>
                    <select
                        className="min-w-[9rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm"
                        value={filters.push || ''}
                        onChange={(e) => apply({ push: e.target.value })}
                    >
                        <option value="">Όλα</option>
                        <option value="on">Ενεργές</option>
                        <option value="off">Χωρίς ειδοποιήσεις</option>
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-0.5 block text-xs font-medium text-slate-600">Ομάδα</span>
                    <select
                        className="min-w-[8rem] rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm"
                        value={filters.class_id || ''}
                        onChange={(e) => apply({ class_id: e.target.value })}
                    >
                        <option value="">Όλες</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <button
                    type="button"
                    onClick={() => apply({ q: '', status: '', push: '', class_id: '' })}
                    className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-slate-50"
                >
                    Επαναφορά
                </button>
                <button
                    type="button"
                    onClick={exportCsv}
                    className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-slate-50"
                >
                    ↓ Εξαγωγή
                </button>
            </div>

            {inviteOpen && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route(routes.store), {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                setInviteOpen(false);
                            },
                        });
                    }}
                    className="mb-6 grid gap-3 rounded-lg border border-sky-200 bg-sky-50 p-4 sm:grid-cols-2"
                >
                    <p className="sm:col-span-2 text-sm font-semibold text-sky-950">
                        Πρόσκληση γονέα
                    </p>
                    <input
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        placeholder="Ονοματεπώνυμο"
                        value={form.data.parent_name}
                        onChange={(e) => form.setData('parent_name', e.target.value)}
                    />
                    <input
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        placeholder="Email"
                        type="email"
                        required
                        value={form.data.parent_email}
                        onChange={(e) => form.setData('parent_email', e.target.value)}
                    />
                    <input
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        placeholder="Κωδικός"
                        type="text"
                        value={form.data.parent_password}
                        onChange={(e) => form.setData('parent_password', e.target.value)}
                    />
                    <select
                        className="rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm"
                        required
                        value={form.data.athlete_id}
                        onChange={(e) => form.setData('athlete_id', e.target.value)}
                    >
                        <option value="">Αθλητής</option>
                        {athletes.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.last_name} {a.first_name}
                            </option>
                        ))}
                    </select>
                    <div className="flex gap-2 sm:col-span-2">
                        <button
                            type="submit"
                            className="rounded-md bg-orange-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-600"
                        >
                            Αποστολή πρόσκλησης
                        </button>
                        <button
                            type="button"
                            onClick={() => setInviteOpen(false)}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                        >
                            Ακύρωση
                        </button>
                    </div>
                </form>
            )}

            <div className="overflow-hidden rounded-lg border border-ts-border bg-ts-surface">
                <div className="max-h-[min(calc(2.75rem+20*2.5rem),calc(100vh-16rem))] overflow-auto overscroll-contain">
                    <table className="min-w-full text-sm">
                        <thead className="sticky top-0 z-10 bg-ts-surface-2 text-xs font-bold uppercase tracking-wide text-ts-muted">
                            <tr>
                                <th className="px-3 py-2.5 text-left">
                                    <input
                                        type="checkbox"
                                        checked={allSelected}
                                        onChange={() =>
                                            setSelected(allSelected ? [] : pageIds)
                                        }
                                        aria-label="Επιλογή όλων"
                                    />
                                </th>
                                <th className="px-3 py-2.5 text-left">Ονοματεπώνυμο</th>
                                <th className="px-3 py-2.5 text-left">Συνδεδεμένοι αθλητές</th>
                                <th className="px-3 py-2.5 text-left">Email</th>
                                <th className="px-3 py-2.5 text-left">Κατάσταση</th>
                                <th className="px-3 py-2.5 text-left">Εφαρμογή</th>
                                <th className="px-3 py-2.5 text-left">Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pageRows.map((row) => {
                                const app = appBadge(row);
                                const needsInvite = row.status !== 'active';

                                return (
                                    <tr
                                        key={row.id}
                                        className="border-t border-ts-border hover:bg-ts-hover/40"
                                    >
                                        <td className="px-3 py-2.5">
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(row.id)}
                                                onChange={() =>
                                                    setSelected((prev) =>
                                                        prev.includes(row.id)
                                                            ? prev.filter((x) => x !== row.id)
                                                            : [...prev, row.id],
                                                    )
                                                }
                                                aria-label={`Επιλογή ${row.full_name}`}
                                            />
                                        </td>
                                        <td className="px-3 py-2.5 font-semibold text-ts-fg">
                                            {row.full_name}
                                        </td>
                                        <td className="px-3 py-2.5 text-ts-muted">
                                            {(row.athletes || []).length ? (
                                                <ul className="space-y-0.5">
                                                    {(row.athletes || []).map((a) => (
                                                        <li key={a.id || a.label}>{a.label}</li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5 text-ts-muted">
                                            {row.email || (
                                                <span className="text-ts-muted">Χωρίς email</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${statusBadgeClass(row.status)}`}
                                            >
                                                {STATUS_LABELS[row.status] || row.status}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${app.className}`}
                                            >
                                                {app.label}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {needsInvite ? (
                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={!row.email}
                                                        onClick={() => openInviteFor(row)}
                                                        className="inline-flex items-center rounded-md bg-orange-500 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-orange-600 disabled:opacity-45"
                                                    >
                                                        Αποστολή πρόσκλησης
                                                    </button>
                                                    <a
                                                        href={parentAppUrl}
                                                        className="inline-flex items-center rounded-md bg-amber-400 px-2.5 py-1.5 text-xs font-semibold text-slate-900 hover:bg-amber-500"
                                                    >
                                                        Σύνδεσμος app
                                                    </a>
                                                </div>
                                            ) : (
                                                <span className="text-ts-muted">--</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {!pageRows.length && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-8 text-center text-ts-muted"
                                    >
                                        Δεν υπάρχουν γονείς με αυτά τα κριτήρια.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-ts-border px-3 py-3 text-xs font-medium text-ts-muted">
                    Σύνολο {directory.length} εγγραφές
                </div>
            </div>
        </AdminLayout>
    );
}
