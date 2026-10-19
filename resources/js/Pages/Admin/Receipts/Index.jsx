import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2.5 py-2 text-sm text-ts-fg focus:border-ts-accent focus:outline-none focus:ring-1 focus:ring-ts-accent';
const btnSecondary =
    'inline-flex items-center gap-1.5 rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-bg disabled:opacity-50';
const btnTeal = 'rounded-md bg-teal-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-700';
const btnDanger =
    'rounded-md bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-500/25 disabled:opacity-50';
const card = 'rounded-xl border border-ts-border bg-ts-surface p-4 shadow-sm';

function euro(n) {
    return Number(n || 0).toLocaleString('el-GR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(value) {
    if (!value) {
        return '—';
    }
    const date = new Date(`${String(value).slice(0, 10)}T12:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleDateString('el-GR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function StatCard({ label, value, tone = 'neutral' }) {
    const toneClass =
        tone === 'positive' ? 'text-emerald-600' : tone === 'warn' ? 'text-amber-600' : 'text-ts-fg';

    return (
        <div className={card}>
            <div className="text-sm text-ts-muted">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${toneClass}`}>{value}</div>
        </div>
    );
}

export default function Index({
    tab,
    filters,
    issues,
    seriesOptions,
    summary,
    bySeries,
    byMonth,
    byKind,
    receiptSeries,
}) {
    const flash = usePage().props.flash;
    const [draft, setDraft] = useState({
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
        series: filters.series || '',
        kind: filters.kind || 'all',
        status: filters.status || 'all',
        q: filters.q || '',
    });
    const [openIssue, setOpenIssue] = useState(null);
    const [cancellingId, setCancellingId] = useState('');

    const receiptForm = useForm({ series: 'Α', range_from: 1, range_to: 50 });

    const setTab = (id) => {
        router.get(
            route('admin.receipts.index'),
            { tab: id, ...(id === 'registry' ? draft : {}) },
            { preserveState: true, replace: true },
        );
    };

    const applyFilters = (e) => {
        e?.preventDefault?.();
        router.get(route('admin.receipts.index'), { tab: 'registry', ...draft }, {
            preserveState: true,
            replace: true,
        });
    };

    const csvHref = useMemo(() => {
        const params = new URLSearchParams();
        Object.entries(draft).forEach(([key, value]) => {
            if (value !== '' && value != null) {
                params.set(key, value);
            }
        });
        const qs = params.toString();
        return route('admin.receipts.export') + (qs ? `?${qs}` : '');
    }, [draft]);

    const cancelIssue = (row) => {
        if (row.is_cancelled || cancellingId) {
            return;
        }
        if (
            !confirm(
                `Να ακυρωθεί η απόδειξη ${row.label}; Ο αριθμός θα παραμείνει στο μητρώο και δεν θα ξαναδοθεί.`,
            )
        ) {
            return;
        }
        setCancellingId(row.id);
        router.post(
            route('admin.receipts.cancel', row.id),
            { ...draft, tab: 'registry' },
            {
                preserveScroll: true,
                onFinish: () => setCancellingId(''),
            },
        );
    };

    return (
        <AdminLayout title="Αποδείξεις">
            <Head title="Αποδείξεις είσπραξης" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {flash.error}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold text-ts-fg">Αποδείξεις είσπραξης</h2>
                    <p className="mt-1 text-sm text-ts-muted">
                        Δείτε τι έχει κοπεί (συγκεντρωτικά και αναλυτικά) και ορίστε τη σειρά αριθμών
                        του βιβλίου.
                    </p>
                </div>
                <Link href={route('admin.settings.index')} className="text-sm text-ts-accent">
                    ← Ρυθμίσεις
                </Link>
            </div>

            <nav className="mb-4 flex flex-wrap gap-4 border-b border-ts-border">
                {[
                    { id: 'registry', label: 'Μητρώο' },
                    { id: 'numbering', label: 'Αρίθμηση' },
                ].map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`-mb-px border-b-2 px-1 py-2 text-sm ${
                            tab === t.id
                                ? 'border-ts-accent font-medium text-ts-fg'
                                : 'border-transparent text-ts-muted hover:text-ts-fg'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </nav>

            {tab === 'registry' && (
                <div className="space-y-4 receipt-registry-print">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <p className="max-w-3xl text-sm text-ts-muted">
                            Συγκεντρωτικά και αναλυτικά όλες οι αποδείξεις που έχουν κοπεί: ποσό,
                            παραλήπτης και αιτιολογία. Οι ακυρωμένες αριθμήσεις μένουν στο μητρώο και
                            δεν ξαναδίνονται.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <a
                                href={csvHref}
                                className={btnSecondary}
                                aria-disabled={issues.length === 0}
                                onClick={(e) => {
                                    if (issues.length === 0) {
                                        e.preventDefault();
                                    }
                                }}
                            >
                                CSV
                            </a>
                            <button type="button" className={btnSecondary} onClick={() => window.print()}>
                                Εκτύπωση
                            </button>
                        </div>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-3 rounded-xl border border-ts-border bg-ts-surface p-3 sm:grid-cols-2 lg:grid-cols-6"
                    >
                        <label className="text-xs text-ts-muted">
                            Από
                            <input
                                type="date"
                                className={`${field} mt-1`}
                                value={draft.date_from}
                                onChange={(e) => setDraft((d) => ({ ...d, date_from: e.target.value }))}
                            />
                        </label>
                        <label className="text-xs text-ts-muted">
                            Έως
                            <input
                                type="date"
                                className={`${field} mt-1`}
                                value={draft.date_to}
                                onChange={(e) => setDraft((d) => ({ ...d, date_to: e.target.value }))}
                            />
                        </label>
                        <label className="text-xs text-ts-muted">
                            Σειρά
                            <select
                                className={`${field} mt-1`}
                                value={draft.series}
                                onChange={(e) => setDraft((d) => ({ ...d, series: e.target.value }))}
                            >
                                <option value="">Όλες</option>
                                {seriesOptions.map((item) => (
                                    <option key={item} value={item}>
                                        {item}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-ts-muted">
                            Είδος
                            <select
                                className={`${field} mt-1`}
                                value={draft.kind}
                                onChange={(e) => setDraft((d) => ({ ...d, kind: e.target.value }))}
                            >
                                <option value="all">Όλα</option>
                                <option value="subscription">Συνδρομές</option>
                                <option value="rental">Ενοικιάσεις</option>
                                <option value="other">Άλλο</option>
                            </select>
                        </label>
                        <label className="text-xs text-ts-muted">
                            Κατάσταση
                            <select
                                className={`${field} mt-1`}
                                value={draft.status}
                                onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value }))}
                            >
                                <option value="all">Όλες</option>
                                <option value="valid">Εκδοθείσες</option>
                                <option value="voided">Ακυρωμένες</option>
                            </select>
                        </label>
                        <label className="text-xs text-ts-muted lg:col-span-1 sm:col-span-2">
                            Αναζήτηση
                            <div className="mt-1 flex gap-2">
                                <input
                                    className={field}
                                    value={draft.q}
                                    onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
                                    placeholder="Όνομα, αιτιολογία, αριθμός…"
                                />
                                <button type="submit" className={btnTeal}>
                                    Φίλτρο
                                </button>
                            </div>
                        </label>
                    </form>

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard label="Εκδοθείσες" value={String(summary.issued_count)} />
                        <StatCard
                            label="Σύνολο ποσού"
                            value={`€ ${euro(summary.total_amount)}`}
                            tone="positive"
                        />
                        <StatCard label="Με email" value={String(summary.emailed_count)} />
                        <StatCard
                            label="Ακυρωμένες"
                            value={String(summary.cancelled_count)}
                            tone="warn"
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3">
                        <section className={card}>
                            <h3 className="mb-2 text-sm font-semibold text-ts-fg">Ανά σειρά</h3>
                            {bySeries.length === 0 ? (
                                <p className="text-sm text-ts-muted">Δεν υπάρχουν αποδείξεις στα φίλτρα.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-ts-border text-left text-xs text-ts-muted">
                                                <th className="py-1.5 pr-2">Σειρά</th>
                                                <th className="py-1.5 pr-2">Εκδοθείσες</th>
                                                <th className="py-1.5 pr-2">Ακυρωμένες</th>
                                                <th className="py-1.5">Ποσό</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {bySeries.map((row) => (
                                                <tr key={row.series} className="border-b border-ts-border/60">
                                                    <td className="py-1.5 pr-2 text-ts-fg">{row.series}</td>
                                                    <td className="py-1.5 pr-2">{row.issued}</td>
                                                    <td className="py-1.5 pr-2">{row.cancelled}</td>
                                                    <td className="py-1.5">€ {euro(row.amount)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>

                        <section className={card}>
                            <h3 className="mb-2 text-sm font-semibold text-ts-fg">Ανά μήνα έκδοσης</h3>
                            {byMonth.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν εκδοθείσες αποδείξεις στα φίλτρα.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-ts-border text-left text-xs text-ts-muted">
                                                <th className="py-1.5 pr-2">Μήνας</th>
                                                <th className="py-1.5 pr-2">Πλήθος</th>
                                                <th className="py-1.5">Ποσό</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {byMonth.map((row) => (
                                                <tr key={row.month} className="border-b border-ts-border/60">
                                                    <td className="py-1.5 pr-2 text-ts-fg">{row.month}</td>
                                                    <td className="py-1.5 pr-2">{row.count}</td>
                                                    <td className="py-1.5">€ {euro(row.amount)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>

                        <section className={card}>
                            <h3 className="mb-2 text-sm font-semibold text-ts-fg">Ανά είδος</h3>
                            {byKind.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν εκδοθείσες αποδείξεις στα φίλτρα.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-ts-border text-left text-xs text-ts-muted">
                                                <th className="py-1.5 pr-2">Είδος</th>
                                                <th className="py-1.5 pr-2">Πλήθος</th>
                                                <th className="py-1.5">Ποσό</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {byKind.map((row) => (
                                                <tr key={row.kind} className="border-b border-ts-border/60">
                                                    <td className="py-1.5 pr-2 text-ts-fg">{row.kind_label}</td>
                                                    <td className="py-1.5 pr-2">{row.count}</td>
                                                    <td className="py-1.5">€ {euro(row.amount)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </div>

                    <section className={card}>
                        <h3 className="mb-3 text-sm font-semibold text-ts-fg">Αναλυτικό μητρώο</h3>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[56rem] text-sm">
                                <thead>
                                    <tr className="border-b border-ts-border text-left text-xs uppercase tracking-wide text-ts-muted">
                                        <th className="px-2 py-2">Ημ/νία</th>
                                        <th className="px-2 py-2">Απόδειξη</th>
                                        <th className="px-2 py-2">Είδος</th>
                                        <th className="px-2 py-2">Παραλήπτης</th>
                                        <th className="px-2 py-2">Αιτιολογία</th>
                                        <th className="px-2 py-2">Ποσό</th>
                                        <th className="px-2 py-2">Κατάσταση</th>
                                        <th className="px-2 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {issues.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="px-2 py-4 text-ts-muted">
                                                Δεν έχουν κοπεί αποδείξεις (ή δεν ταιριάζουν τα φίλτρα).
                                            </td>
                                        </tr>
                                    ) : (
                                        issues.map((row) => (
                                            <tr
                                                key={row.id}
                                                className={`border-b border-ts-border/60 ${
                                                    row.is_cancelled ? 'opacity-60' : ''
                                                }`}
                                            >
                                                <td className="px-2 py-2 whitespace-nowrap">
                                                    {formatDate(row.issued_date)}
                                                </td>
                                                <td className="px-2 py-2 font-medium text-ts-fg">
                                                    {row.label}
                                                </td>
                                                <td className="px-2 py-2">{row.kind_label}</td>
                                                <td className="px-2 py-2">{row.received_from || '—'}</td>
                                                <td className="max-w-[16rem] px-2 py-2">{row.reason || '—'}</td>
                                                <td className="px-2 py-2 whitespace-nowrap">
                                                    {row.amount > 0 ? `€ ${euro(row.amount)}` : '—'}
                                                </td>
                                                <td className="px-2 py-2">{row.status_label}</td>
                                                <td className="px-2 py-2">
                                                    {!row.is_cancelled ? (
                                                        <div className="flex flex-wrap gap-2">
                                                            <button
                                                                type="button"
                                                                className="text-xs font-medium text-ts-accent hover:underline"
                                                                onClick={() => setOpenIssue(row)}
                                                            >
                                                                Προβολή
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className={btnDanger}
                                                                disabled={cancellingId === row.id}
                                                                onClick={() => cancelIssue(row)}
                                                            >
                                                                {cancellingId === row.id
                                                                    ? 'Ακύρωση…'
                                                                    : 'Ακύρωση απόδειξης'}
                                                            </button>
                                                        </div>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                {summary.issued_count > 0 ? (
                                    <tfoot>
                                        <tr className="border-t border-ts-border font-medium">
                                            <td colSpan={5} className="px-2 py-2">
                                                Σύνολο εκδοθεισών
                                            </td>
                                            <td className="px-2 py-2">€ {euro(summary.total_amount)}</td>
                                            <td colSpan={2} className="px-2 py-2 text-ts-muted">
                                                {summary.issued_count} αποδείξεις
                                            </td>
                                        </tr>
                                    </tfoot>
                                ) : null}
                            </table>
                        </div>
                    </section>
                </div>
            )}

            {tab === 'numbering' && (
                <div className="max-w-xl space-y-4">
                    <form
                        className={`${card} grid gap-2 sm:grid-cols-3`}
                        onSubmit={(e) => {
                            e.preventDefault();
                            receiptForm.post(route('admin.settings.receipts.store'), {
                                onSuccess: () => {
                                    const nextFrom = Number(receiptForm.data.range_to) + 1;
                                    receiptForm.setData({
                                        series: receiptForm.data.series,
                                        range_from: nextFrom,
                                        range_to: nextFrom + 49,
                                    });
                                },
                            });
                        }}
                    >
                        <h3 className="sm:col-span-3 text-base font-semibold text-ts-fg">
                            Αρίθμηση αποδείξεων
                        </h3>
                        <p className="sm:col-span-3 text-sm text-ts-muted">
                            Ορίστε σειρά και εύρος αριθμών για το βιβλίο αποδείξεων.
                        </p>
                        <input
                            required
                            placeholder="Σειρά"
                            className={field}
                            value={receiptForm.data.series}
                            onChange={(e) => receiptForm.setData('series', e.target.value)}
                        />
                        <input
                            type="number"
                            min={1}
                            className={field}
                            value={receiptForm.data.range_from}
                            onChange={(e) => receiptForm.setData('range_from', e.target.value)}
                        />
                        <input
                            type="number"
                            min={1}
                            className={field}
                            value={receiptForm.data.range_to}
                            onChange={(e) => receiptForm.setData('range_to', e.target.value)}
                        />
                        <button type="submit" className={btnTeal} disabled={receiptForm.processing}>
                            Προσθήκη εύρους
                        </button>
                    </form>

                    <ul className="space-y-1">
                        {receiptSeries.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-center justify-between rounded border border-ts-border px-3 py-2 text-sm"
                            >
                                <div>
                                    <div className="text-ts-fg">
                                        Σειρά {item.series}: {item.range_from}–{item.range_to}
                                    </div>
                                    <div className="text-xs text-ts-muted">επόμενο: {item.next_number}</div>
                                </div>
                                <button
                                    type="button"
                                    className="text-sm text-rose-500"
                                    onClick={() => {
                                        if (confirm('Διαγραφή;')) {
                                            router.delete(route('admin.settings.receipts.destroy', item.id));
                                        }
                                    }}
                                >
                                    Διαγραφή
                                </button>
                            </li>
                        ))}
                        {!receiptSeries.length && (
                            <li className="text-sm text-ts-muted">Καμία σειρά.</li>
                        )}
                    </ul>
                </div>
            )}

            {openIssue ? (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    role="dialog"
                    aria-modal="true"
                    onClick={() => setOpenIssue(null)}
                >
                    <div
                        className="w-full max-w-md rounded-xl border border-ts-border bg-ts-surface p-5 shadow-lg"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-semibold text-ts-fg">Απόδειξη {openIssue.label}</h3>
                                <p className="text-sm text-ts-muted">{openIssue.status_label}</p>
                            </div>
                            <button
                                type="button"
                                className="text-sm text-ts-muted hover:text-ts-fg"
                                onClick={() => setOpenIssue(null)}
                            >
                                Κλείσιμο
                            </button>
                        </div>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-ts-muted">Ημερομηνία</dt>
                                <dd className="text-ts-fg">{formatDate(openIssue.issued_date)}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-ts-muted">Είδος</dt>
                                <dd className="text-ts-fg">{openIssue.kind_label}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-ts-muted">Παραλήπτης</dt>
                                <dd className="text-right text-ts-fg">{openIssue.received_from || '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-ts-muted">Αιτιολογία</dt>
                                <dd className="text-right text-ts-fg">{openIssue.reason || '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-ts-muted">Ποσό</dt>
                                <dd className="font-semibold text-ts-fg">
                                    {openIssue.amount > 0 ? `€ ${euro(openIssue.amount)}` : '—'}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            ) : null}
        </AdminLayout>
    );
}
