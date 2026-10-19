import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Index({
    items,
    filters,
    routes,
    coaches = [],
    missingAttendanceCount = 0,
    missingAttendanceHref = null,
}) {
    const flash = usePage().props.flash;
    const [showCreate, setShowCreate] = useState(false);
    const [selected, setSelected] = useState([]);
    const [pageSize, setPageSize] = useState(25);
    const form = useForm({
        name: '',
        sport: '',
        age_group: '',
        gender: '',
        monthly_fee: '0',
        coach_id: '',
    });

    const tab = filters.tab === 'inactive' ? 'inactive' : 'active';
    const rows = items?.data || [];
    const pageIds = useMemo(() => rows.map((r) => r.id), [rows]);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

    const apply = (patch) => {
        router.get(
            route(routes.index),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout title="Τμήματα">
            <Head title="Τμήματα" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4">
                <h2 className="text-xl font-semibold text-ts-fg">Τμήματα</h2>
                <div className="mt-3 flex gap-4 border-b border-ts-border">
                    <button
                        type="button"
                        onClick={() => apply({ tab: 'active' })}
                        className={`border-b-2 px-1 pb-2 text-sm ${
                            tab === 'active'
                                ? 'border-teal-600 text-teal-700'
                                : 'border-transparent text-ts-muted'
                        }`}
                    >
                        Ενεργά
                    </button>
                    <button
                        type="button"
                        onClick={() => apply({ tab: 'inactive' })}
                        className={`border-b-2 px-1 pb-2 text-sm ${
                            tab === 'inactive'
                                ? 'border-teal-600 text-teal-700'
                                : 'border-transparent text-ts-muted'
                        }`}
                    >
                        Μη Ενεργά
                    </button>
                </div>
            </div>

            {missingAttendanceCount > 0 && (
                <Link
                    href={missingAttendanceHref || route('admin.trainings.index')}
                    className="mb-4 block w-full rounded-full border border-amber-300 bg-amber-100 px-4 py-2.5 text-sm font-semibold text-amber-950 hover:bg-amber-200"
                >
                    {missingAttendanceCount} προπονήσεις χωρίς παρουσίες
                </Link>
            )}

            <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 className="text-base font-semibold text-ts-fg">Τμήματα</h3>
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="flex items-center gap-1 text-sm text-ts-muted">
                            <span>Σεζόν:</span>
                            <select
                                className="rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm text-ts-fg"
                                value={filters.season_id || ''}
                                onChange={(e) => apply({ season_id: e.target.value })}
                            >
                                <option value="">—</option>
                            </select>
                        </label>
                        <label className="flex items-center gap-1 text-sm text-ts-muted">
                            <span>Άθλημα:</span>
                            <select
                                className="rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm text-ts-fg"
                                value={filters.sport || ''}
                                onChange={(e) => apply({ sport: e.target.value })}
                            >
                                <option value="">Όλα</option>
                            </select>
                        </label>
                        <button
                            type="button"
                            onClick={() => setShowCreate((v) => !v)}
                            className="rounded-md bg-teal-700 px-3 py-1.5 text-sm text-white hover:bg-teal-800"
                        >
                            + Δημιουργία
                        </button>
                        <button
                            type="button"
                            disabled={selected.length === 0}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover disabled:opacity-50"
                            onClick={() => {
                                if (!selected.length) return;
                                alert('Μαζική αλλαγή κατάστασης για ' + selected.length + ' τμήματα.');
                            }}
                        >
                            Μαζική αλλαγή κατάστασης
                        </button>
                    </div>
                </div>

                <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="text-ts-muted">
                            Δείξε{' '}
                            <select
                                className="rounded-md border border-ts-border bg-ts-bg px-2 py-1 text-sm"
                                value={pageSize}
                                onChange={(e) => setPageSize(Number(e.target.value))}
                            >
                                {[10, 25, 50].map((n) => (
                                    <option key={n} value={n}>
                                        {n}
                                    </option>
                                ))}
                            </select>{' '}
                            εγγραφές
                        </label>
                        <button
                            type="button"
                            className="text-ts-accent hover:underline"
                            onClick={() => setSelected(allSelected ? [] : pageIds)}
                        >
                            {allSelected ? 'Αποεπιλογή όλων' : 'Επιλογή όλων'}
                        </button>
                        {allSelected ? null : (
                            <button
                                type="button"
                                className="text-ts-muted hover:underline"
                                onClick={() => setSelected([])}
                            >
                                Αποεπιλογή όλων
                            </button>
                        )}
                    </div>
                    <label className="flex items-center gap-1 text-ts-muted">
                        <span>Αναζήτηση:</span>
                        <input
                            className="rounded-md border border-ts-border bg-ts-bg px-2 py-1 text-sm text-ts-fg"
                            defaultValue={filters.q || ''}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') apply({ q: e.currentTarget.value });
                            }}
                        />
                    </label>
                </div>

                {showCreate && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route(routes.store));
                        }}
                        className="mb-6 grid gap-2 rounded-lg border border-ts-border bg-ts-bg p-4 sm:grid-cols-3"
                    >
                        <p className="sm:col-span-3 text-xs font-semibold uppercase tracking-wider text-ts-muted">
                            Νέο τμήμα
                        </p>
                        <input
                            required
                            placeholder="Όνομα"
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        <input
                            placeholder="Άθλημα"
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={form.data.sport}
                            onChange={(e) => form.setData('sport', e.target.value)}
                        />
                        <input
                            placeholder="Κατηγορία / έτος"
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={form.data.age_group}
                            onChange={(e) => form.setData('age_group', e.target.value)}
                        />
                        <select
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={form.data.gender}
                            onChange={(e) => form.setData('gender', e.target.value)}
                        >
                            <option value="">Φύλο</option>
                            <option value="mixed">Μικτό</option>
                            <option value="male">Αγόρια</option>
                            <option value="female">Κορίτσια</option>
                        </select>
                        <select
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={form.data.coach_id}
                            onChange={(e) => form.setData('coach_id', e.target.value)}
                        >
                            <option value="">Α' Προπονητής</option>
                            {coaches.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.last_name} {c.first_name}
                                </option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg sm:w-fit"
                        >
                            Δημιουργία & άνοιγμα προφίλ
                        </button>
                    </form>
                )}

                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-ts-surface-2 text-xs uppercase text-ts-muted">
                            <tr>
                                <th className="px-3 py-2 text-left">
                                    <input
                                        type="checkbox"
                                        checked={allSelected}
                                        onChange={() =>
                                            setSelected(allSelected ? [] : pageIds)
                                        }
                                        aria-label="Επιλογή όλων"
                                    />
                                </th>
                                <th className="px-3 py-2 text-left">Όνομα</th>
                                <th className="px-3 py-2 text-left">Φύλο</th>
                                <th className="px-3 py-2 text-left">Σεζόν</th>
                                <th className="px-3 py-2 text-left">Άθλημα</th>
                                <th className="px-3 py-2 text-left">Κατηγορία</th>
                                <th className="px-3 py-2 text-left">Α' Προπονητής</th>
                                <th className="px-3 py-2 text-left">Αθλητές</th>
                                <th className="px-3 py-2 text-right" />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((c) => (
                                <tr
                                    key={c.id}
                                    className="border-t border-ts-border hover:bg-ts-hover/40"
                                >
                                    <td className="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(c.id)}
                                            onChange={() =>
                                                setSelected((prev) =>
                                                    prev.includes(c.id)
                                                        ? prev.filter((x) => x !== c.id)
                                                        : [...prev, c.id],
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <Link
                                            href={route(routes.show, c.id)}
                                            className="font-medium text-ts-accent hover:underline"
                                        >
                                            {c.name}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 text-ts-muted">{c.gender || '—'}</td>
                                    <td className="px-3 py-2 text-ts-muted">
                                        {c.season_name || '—'}
                                    </td>
                                    <td className="px-3 py-2 text-ts-muted">{c.sport || '—'}</td>
                                    <td className="px-3 py-2 text-ts-muted">
                                        {c.age_group || '—'}
                                    </td>
                                    <td className="px-3 py-2 text-ts-muted">
                                        {c.coach_name || '—'}
                                    </td>
                                    <td className="px-3 py-2 text-ts-fg-soft">
                                        {c.athletes_count}
                                    </td>
                                    <td className="px-3 py-2 text-right text-ts-muted">⋮</td>
                                </tr>
                            ))}
                            {!rows.length && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-8 text-center text-ts-muted"
                                    >
                                        Δεν βρέθηκαν τμήματα.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <p className="mt-3 text-right text-xs text-ts-muted">
                    Εμφανίζονται 1 έως {rows.length} από {items?.total ?? rows.length} εγγραφές
                </p>
            </section>
        </AdminLayout>
    );
}
