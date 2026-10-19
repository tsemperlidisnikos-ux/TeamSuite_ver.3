import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const ATHLETE_SUBSCRIPTIONS = 'ΣΥΝΔΡΟΜΕΣ ΑΘΛΗΤΩΝ';

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

function formatSeasonRange(from, to) {
    if (!from || !to) {
        return '';
    }
    return `${formatDate(from)} - ${formatDate(to)}`;
}

const inputClass =
    'w-full rounded-md border border-ts-border bg-ts-bg px-2.5 py-2 text-sm text-ts-fg focus:border-ts-accent focus:outline-none focus:ring-1 focus:ring-ts-accent';

function Card({ children, className = '' }) {
    return (
        <section className={`rounded-xl border border-ts-border bg-ts-surface p-4 shadow-sm ${className}`}>
            {children}
        </section>
    );
}

function SectionHeader({ eyebrow, title, description, aside }) {
    return (
        <div className="mb-4 flex flex-wrap items-start justify-between gap-4">
            <div className="max-w-3xl">
                {eyebrow ? (
                    <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-ts-accent">{eyebrow}</p>
                ) : null}
                <h3 className="text-xl font-bold tracking-tight text-ts-fg">{title}</h3>
                {description ? <p className="mt-1 text-sm text-ts-muted">{description}</p> : null}
            </div>
            {aside}
        </div>
    );
}

function StatPill({ label, value }) {
    return (
        <div className="min-w-[10rem] rounded-xl bg-gradient-to-br from-ts-accent to-teal-900 px-4 py-3 text-ts-accent-fg shadow-sm">
            <div className="text-xs opacity-90">{label}</div>
            <div className="text-2xl font-semibold">{value}</div>
        </div>
    );
}

function SummaryStatCard({ label, value, hint, tone = 'neutral' }) {
    const toneClass =
        tone === 'positive'
            ? 'text-emerald-600'
            : tone === 'negative'
              ? 'text-rose-600'
              : 'text-ts-fg';

    return (
        <Card>
            <div className="text-sm text-ts-muted">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${toneClass}`}>{value}</div>
            {hint ? <div className="mt-1 text-xs text-ts-muted">{hint}</div> : null}
        </Card>
    );
}

function GradientSummaryCard({ label, value }) {
    return (
        <div className="rounded-xl bg-gradient-to-br from-teal-700 to-slate-900 px-4 py-4 text-white shadow-sm">
            <div className="text-sm text-teal-100">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}

function TitleAnalysisTable({ children }) {
    return (
        <div className="overflow-hidden rounded-lg border border-ts-border">
            <div className="grid grid-cols-[minmax(8rem,12rem)_1fr] bg-slate-900 text-xs font-semibold uppercase tracking-wide text-white">
                <div className="border-r border-slate-700 px-3 py-2">Τίτλος</div>
                <div className="px-3 py-2">Ανάλυση</div>
            </div>
            <div className="divide-y divide-ts-border bg-ts-surface">{children}</div>
        </div>
    );
}

function TitleAnalysisRow({ title, htmlFor, children }) {
    return (
        <div className="grid grid-cols-[minmax(8rem,12rem)_1fr] text-sm">
            <label
                htmlFor={htmlFor}
                className="border-r border-ts-border bg-ts-bg px-3 py-2.5 font-medium text-ts-fg-soft"
            >
                {title}
            </label>
            <div className="px-3 py-2">{children}</div>
        </div>
    );
}

function DataTable({ columns, rows, emptyMessage }) {
    const needsScroll = rows.length > 20;

    return (
        <div
            className={`rounded-lg border border-ts-border ${
                needsScroll
                    ? 'max-h-[min(calc(2.75rem+20*2.5rem),calc(100vh-16rem))] overflow-auto overscroll-contain'
                    : 'overflow-x-auto'
            }`}
        >
            <table className="min-w-full text-sm">
                <thead className="sticky top-0 z-10 bg-slate-900 text-left text-xs uppercase tracking-wide text-white">
                    <tr>
                        {columns.map((col) => (
                            <th key={col.key} className="px-3 py-2 font-semibold">
                                {col.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-ts-border bg-ts-surface">
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="px-3 py-6 text-center text-ts-muted">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={row.id} className="text-ts-fg-soft">
                                {columns.map((col) => (
                                    <td key={col.key} className="px-3 py-2 align-top">
                                        {col.render(row)}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

const PAYMENT_METHOD_OPTIONS = [
    { value: 'cash', label: 'Μετρητά' },
    { value: 'pos', label: 'POS' },
    { value: 'transfer', label: 'Κατάθεση' },
    { value: 'viva', label: 'VIVA' },
    { value: 'other', label: 'Άλλο' },
];

function EntryListToolbar({ total, count, onPrint }) {
    return (
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ts-border bg-ts-bg px-3 py-2 text-sm">
            <span className="text-ts-muted">
                {count} εγγραφές · {euro(total)} €
            </span>
            <button
                type="button"
                className="rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg-soft hover:bg-ts-hover"
                onClick={onPrint}
            >
                Εκτύπωση
            </button>
        </div>
    );
}

function EntryForm({
    routeName,
    label,
    categories = [],
    subcategory,
    associationOptions = [],
    sportOptions = [],
    accounts = [],
}) {
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        amount: '',
        category: subcategory || categories[0] || '',
        subcategory: subcategory || categories[0] || '',
        description: '',
        occurred_on: today,
        club_name: '',
        sport: '',
        payment_method: 'cash',
        cash_account_id: '',
        vat_rate: '0',
        notes: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    category: subcategory || data.category,
                    subcategory: subcategory || data.subcategory,
                }));
                form.post(route(routeName), {
                    preserveScroll: true,
                    onSuccess: () =>
                        form.reset(
                            'amount',
                            'description',
                            'notes',
                            'cash_account_id',
                            'occurred_on',
                        ),
                });
            }}
            className="space-y-3"
        >
            <h4 className="text-sm font-semibold text-ts-fg">{label}</h4>
            <TitleAnalysisTable>
                <TitleAnalysisRow title="Σωματείο" htmlFor="entry-club">
                    <select
                        id="entry-club"
                        className={inputClass}
                        value={form.data.club_name}
                        onChange={(e) => {
                            form.setData('club_name', e.target.value);
                            form.setData('sport', '');
                        }}
                    >
                        <option value="">Επιλέξτε σωματείο…</option>
                        {(associationOptions || [])
                            .filter((c) => c.active !== false)
                            .map((c) => (
                                <option key={c.id} value={c.name}>
                                    {c.name}
                                </option>
                            ))}
                    </select>
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Άθλημα" htmlFor="entry-sport">
                    <select
                        id="entry-sport"
                        className={inputClass}
                        value={form.data.sport}
                        onChange={(e) => form.setData('sport', e.target.value)}
                        disabled={!form.data.club_name}
                    >
                        <option value="">
                            {form.data.club_name ? 'Επιλέξτε άθλημα…' : 'Επιλέξτε πρώτα σωματείο…'}
                        </option>
                        {(sportOptions || []).map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Ημερομηνία" htmlFor="entry-date">
                    <input
                        id="entry-date"
                        type="date"
                        className={inputClass}
                        value={form.data.occurred_on}
                        onChange={(e) => form.setData('occurred_on', e.target.value)}
                        required
                    />
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Περιγραφή" htmlFor="entry-description">
                    <input
                        id="entry-description"
                        type="text"
                        className={inputClass}
                        placeholder="π.χ. Χορηγία"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        required
                    />
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Τρόπος πληρωμής" htmlFor="entry-method">
                    <select
                        id="entry-method"
                        className={inputClass}
                        value={form.data.payment_method}
                        onChange={(e) => form.setData('payment_method', e.target.value)}
                    >
                        {PAYMENT_METHOD_OPTIONS.map((m) => (
                            <option key={m.value} value={m.value}>
                                {m.label}
                            </option>
                        ))}
                    </select>
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Ταμείο / λογαριασμός" htmlFor="entry-account">
                    <select
                        id="entry-account"
                        className={inputClass}
                        value={form.data.cash_account_id}
                        onChange={(e) => form.setData('cash_account_id', e.target.value)}
                    >
                        <option value="">— χωρίς ταμείο —</option>
                        {(accounts || []).map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </select>
                </TitleAnalysisRow>
                <TitleAnalysisRow title="ΦΠΑ %" htmlFor="entry-vat">
                    <select
                        id="entry-vat"
                        className={inputClass}
                        value={form.data.vat_rate}
                        onChange={(e) => form.setData('vat_rate', e.target.value)}
                    >
                        <option value="0">0%</option>
                        <option value="6">6%</option>
                        <option value="13">13%</option>
                        <option value="24">24%</option>
                    </select>
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Ποσό" htmlFor="entry-amount">
                    <div className="flex items-center gap-1">
                        <input
                            id="entry-amount"
                            type="number"
                            step="0.01"
                            min="0"
                            className={inputClass}
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            required
                        />
                        <span className="text-xs text-ts-muted">€</span>
                    </div>
                </TitleAnalysisRow>
                <TitleAnalysisRow title="Σημειώσεις" htmlFor="entry-notes">
                    <textarea
                        id="entry-notes"
                        rows={2}
                        className={inputClass}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                </TitleAnalysisRow>
            </TitleAnalysisTable>
            {form.errors.amount || form.errors.description ? (
                <p className="text-sm text-rose-600">
                    {form.errors.amount || form.errors.description}
                </p>
            ) : null}
            <button
                type="submit"
                disabled={form.processing}
                className="rounded-md bg-ts-accent px-3 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
            >
                {form.processing ? 'Αποθήκευση…' : 'Αποθήκευση'}
            </button>
        </form>
    );
}

function MonthlyBars({ series }) {
    const rows = series || [];
    const max = Math.max(1, ...rows.flatMap((row) => [Number(row.revenue || 0), Number(row.expense || 0)]));

    return (
        <div>
            <div className="mb-3 flex items-center gap-4 text-xs text-ts-muted">
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-sm bg-teal-600" /> Έσοδα
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-sm bg-amber-700" /> Έξοδα
                </span>
            </div>
            <div className="flex h-56 items-end gap-3 overflow-x-auto pb-1">
                {rows.map((row) => {
                    const revenueH = Math.round((Number(row.revenue || 0) / max) * 100);
                    const expenseH = Math.round((Number(row.expense || 0) / max) * 100);

                    return (
                        <div key={row.key} className="flex min-w-[4.5rem] flex-1 flex-col items-center gap-2">
                            <div className="flex h-44 w-full items-end justify-center gap-1">
                                <div
                                    className="w-4 rounded-t bg-teal-600"
                                    style={{ height: `${Math.max(revenueH, Number(row.revenue) > 0 ? 4 : 0)}%` }}
                                    title={`Έσοδα ${euro(row.revenue)} €`}
                                />
                                <div
                                    className="w-4 rounded-t bg-amber-700"
                                    style={{ height: `${Math.max(expenseH, Number(row.expense) > 0 ? 4 : 0)}%` }}
                                    title={`Έξοδα ${euro(row.expense)} €`}
                                />
                            </div>
                            <div className="text-center text-[11px] leading-tight text-ts-muted">{row.label}</div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

const PIE_COLORS = ['#0d9488', '#b45309', '#1e3a5f', '#64748b', '#f97316', '#0ea5e9'];

function CategoryPie({ title, rows }) {
    const items = rows || [];
    const total = items.reduce((sum, row) => sum + Number(row.amount || 0), 0) || 1;
    let angle = -90;
    const slices = items.slice(0, 6).map((row, index) => {
        const value = Number(row.amount || 0);
        const sweep = (value / total) * 360;
        const start = angle;
        angle += sweep;
        const large = sweep > 180 ? 1 : 0;
        const startRad = (Math.PI / 180) * start;
        const endRad = (Math.PI / 180) * (start + sweep);
        const x1 = 50 + 40 * Math.cos(startRad);
        const y1 = 50 + 40 * Math.sin(startRad);
        const x2 = 50 + 40 * Math.cos(endRad);
        const y2 = 50 + 40 * Math.sin(endRad);
        const path =
            sweep <= 0.01 ? '' : `M 50 50 L ${x1} ${y1} A 40 40 0 ${large} 1 ${x2} ${y2} Z`;

        return { ...row, path, color: PIE_COLORS[index % PIE_COLORS.length], value };
    });

    return (
        <Card>
            <h3 className="mb-3 text-base font-semibold text-ts-fg">{title}</h3>
            {items.length === 0 ? (
                <p className="text-sm text-ts-muted">Δεν υπάρχουν δεδομένα.</p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-[10rem_1fr] sm:items-center">
                    <svg viewBox="0 0 100 100" className="mx-auto h-40 w-40">
                        {slices.map((slice) =>
                            slice.path ? <path key={slice.category} d={slice.path} fill={slice.color} /> : null,
                        )}
                    </svg>
                    <ul className="space-y-2 text-sm">
                        {slices.map((slice) => (
                            <li key={slice.category} className="flex items-center justify-between gap-3">
                                <span className="inline-flex items-center gap-2 text-ts-fg-soft">
                                    <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: slice.color }} />
                                    {slice.category}
                                </span>
                                <span className="font-medium text-ts-fg">{euro(slice.value)} €</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Card>
    );
}

function AnalysisPanel({ analysis, date, onDateChange }) {
    const day = analysis?.day || {};
    const totals = analysis?.totals || {};
    const netNegative = Number(totals.net || 0) < 0;

    const dayCards = [
        {
            value: day.collections,
            label: 'Εισπράξεις συνδρομών / ενοικιάσεων',
            hint: `${day.collectionCount || 0} κινήσεις`,
        },
        {
            value: day.linkedIncome ?? day.contractual,
            label: 'Συνδεδεμένα έσοδα',
            hint: 'Από συνδρομές / ενοικιάσεις',
        },
        {
            value: day.otherRevenues,
            label: 'Λοιπά έσοδα Οικονομικών',
            hint: 'Ενισχύσεις, χορηγίες και άλλα',
        },
        {
            value: day.totalIncome,
            label: 'Σύνολο εσόδων ημέρας',
            hint: `${day.incomeCount || 0} εγγραφές`,
        },
        {
            value: day.receipts,
            label: 'Αποδείξεις',
            hint: `${day.receiptsCount || 0} εκδοθείσες`,
        },
        {
            value: day.withoutReceipt,
            label: 'Χωρίς απόδειξη',
            hint: Number(day.withoutReceipt) > 0 ? 'Υπάρχουν εισπράξεις χωρίς κοπή' : 'Σε συμφωνία',
        },
    ];

    return (
        <div className="space-y-4">
            <Card>
                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-semibold text-ts-fg">Ταμείο ημέρας</h3>
                        <p className="mt-1 max-w-3xl text-sm text-ts-muted">
                            Εισπράξεις (μετρητά / POS / online), αποδείξεις που κόπηκαν και έσοδα στα Οικονομικά
                            για την επιλεγμένη ημέρα.
                        </p>
                    </div>
                    <label className="text-sm text-ts-muted">
                        Ημερομηνία
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={date}
                            onChange={(e) => onDateChange(e.target.value)}
                        />
                    </label>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {dayCards.map((card) => {
                        const warn = card.label === 'Χωρίς απόδειξη' && Number(card.value) > 0;
                        return (
                            <div
                                key={card.label}
                                className={`rounded-lg border px-3 py-3 ${
                                    warn
                                        ? 'border-amber-300 bg-amber-50/70 dark:border-amber-900/50 dark:bg-amber-950/20'
                                        : 'border-ts-border bg-ts-bg'
                                }`}
                            >
                                <div className="text-lg font-semibold text-ts-fg">
                                    {euro(card.value)} €
                                </div>
                                <div className="mt-1 text-sm text-ts-fg-soft">{card.label}</div>
                                <div className="text-xs text-ts-muted">{card.hint}</div>
                            </div>
                        );
                    })}
                </div>

                {(day.methods || []).length > 0 ? (
                    <div className="mt-4 overflow-x-auto">
                        <DataTable
                            columns={[
                                { key: 'label', label: 'Τρόπος', render: (row) => row.label },
                                {
                                    key: 'collections',
                                    label: 'Εισπράξεις',
                                    render: (row) => `${euro(row.collections)} €`,
                                },
                                {
                                    key: 'collectionCount',
                                    label: 'Κινήσεις',
                                    render: (row) => row.collectionCount || '—',
                                },
                                {
                                    key: 'linkedIncome',
                                    label: 'Συνδεδεμένα έσοδα',
                                    render: (row) => `${euro(row.linkedIncome)} €`,
                                },
                                {
                                    key: 'otherIncome',
                                    label: 'Λοιπά έσοδα',
                                    render: (row) => `${euro(row.otherIncome)} €`,
                                },
                                {
                                    key: 'income',
                                    label: 'Σύνολο εσόδων',
                                    render: (row) => `${euro(row.income)} €`,
                                },
                            ]}
                            rows={(day.methods || []).map((row) => ({ ...row, id: row.key }))}
                            emptyMessage=""
                        />
                    </div>
                ) : (
                    <p className="mt-4 text-sm text-ts-muted">
                        Δεν υπάρχουν εισπράξεις ή έσοδα για αυτή την ημέρα.
                    </p>
                )}

                {(day.missingReceipts || []).length > 0 ? (
                    <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900/40 dark:bg-amber-950/20">
                        <h4 className="text-sm font-semibold text-ts-fg">Εισπράξεις χωρίς απόδειξη</h4>
                        <ul className="mt-2 list-disc space-y-1 ps-5 text-sm text-ts-fg-soft">
                            {(day.missingReceipts || []).map((row) => (
                                <li key={row.id}>
                                    {row.label} · {euro(row.amount)} €
                                </li>
                            ))}
                        </ul>
                        <p className="mt-2 text-xs text-ts-muted">
                            Κόψτε απόδειξη από Συναλλαγές ή Ενοικίαση → Απόδειξη. Μητρώο: Ρυθμίσεις → Αποδείξεις
                        </p>
                    </div>
                ) : null}

                <div className="mt-4 flex justify-end">
                    <button
                        type="button"
                        className="rounded-md border border-ts-border bg-ts-bg px-3 py-1.5 text-sm text-ts-fg-soft hover:bg-ts-hover"
                        onClick={() => window.print()}
                    >
                        Εκτύπωση ταμείου
                    </button>
                </div>
            </Card>

            <div className="grid gap-3 md:grid-cols-3">
                <SummaryStatCard label="Συνολικά έσοδα" value={`${euro(totals.revenue)} €`} tone="positive" />
                <SummaryStatCard label="Συνολικά έξοδα" value={`${euro(totals.expense)} €`} tone="negative" />
                <SummaryStatCard
                    label="Καθαρό αποτέλεσμα"
                    value={`${euro(totals.net)} €`}
                    hint={`Εκκρεμή: ${euro(totals.pending)} €`}
                    tone={netNegative ? 'negative' : 'positive'}
                />
            </div>

            <Card>
                <h3 className="mb-3 text-base font-semibold text-ts-fg">Μηνιαία σύγκριση εσόδων / εξόδων</h3>
                <MonthlyBars series={analysis?.monthlySeries || []} />
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                <CategoryPie title="Έσοδα ανά κατηγορία" rows={analysis?.revenueByCategory || []} />
                <CategoryPie title="Έξοδα ανά κατηγορία" rows={analysis?.expenseByCategory || []} />
            </div>
        </div>
    );
}

function RevenuesPanel({
    categories,
    subcategory,
    rows,
    onSubcategoryChange,
    associationOptions = [],
    sportOptions = [],
    accounts = [],
}) {
    const isAthletePayments = subcategory === ATHLETE_SUBSCRIPTIONS;
    const total = rows.reduce((sum, row) => sum + Number(row.amount || 0), 0);

    return (
        <div className="space-y-4">
            <SectionHeader
                eyebrow="Κατηγορία"
                title="ΕΣΟΔΑ"
                description="Καταχώρηση εσόδων συλλόγου ανά υποκατηγορία, μαζί με αυτόματες πληρωμές αθλητών (εγγραφές, συνδρομές, κάρτες διαρκείας)."
                aside={<StatPill label="Σύνολο υποκατηγορίας" value={`${euro(total)} €`} />}
            />

            <TitleAnalysisTable>
                <TitleAnalysisRow title="Υποκατηγορία" htmlFor="revenue-subcategory">
                    <select
                        id="revenue-subcategory"
                        className={inputClass}
                        value={subcategory}
                        onChange={(e) => onSubcategoryChange(e.target.value)}
                    >
                        {(categories || []).map((item) => (
                            <option key={item} value={item}>
                                {item}
                            </option>
                        ))}
                    </select>
                </TitleAnalysisRow>
            </TitleAnalysisTable>

            {isAthletePayments ? (
                <p className="rounded-lg border border-ts-border bg-ts-bg px-3 py-2 text-sm text-ts-muted">
                    Τα έσοδα αυτής της κατηγορίας προστίθενται αυτόματα από τις πληρωμές αθλητών (Συνδρομές /
                    Κινήσεις / Viva): εγγραφές, μηνιαίες ή ετήσιες συνδρομές και κάρτες διαρκείας. Εμφανίζονται και
                    στην Ανάλυση.
                </p>
            ) : (
                <Card>
                    <EntryForm
                        key={`revenue-${subcategory}`}
                        routeName="admin.finance.revenues.store"
                        label="Νέα καταχώρηση εσόδου"
                        categories={categories}
                        subcategory={subcategory}
                        associationOptions={associationOptions}
                        sportOptions={sportOptions}
                        accounts={accounts}
                    />
                </Card>
            )}

            <EntryListToolbar total={total} count={rows.length} onPrint={() => window.print()} />
            <DataTable
                columns={[
                    { key: 'date', label: 'Ημερομηνία', render: (row) => formatDate(row.date) },
                    { key: 'description', label: 'Περιγραφή', render: (row) => row.description || '—' },
                    { key: 'club', label: 'Σωματείο', render: (row) => row.clubName || '—' },
                    { key: 'sport', label: 'Άθλημα', render: (row) => row.sport || '—' },
                    { key: 'period', label: 'Περίοδος', render: (row) => row.period || '—' },
                    { key: 'amount', label: 'Ποσό', render: (row) => `${euro(row.amount)} €` },
                ]}
                rows={rows}
                emptyMessage="Δεν υπάρχουν εγγραφές για αυτή την υποκατηγορία."
            />
        </div>
    );
}

function emptyMatchDetails() {
    return {
        sport: '',
        category: '',
        teams: '',
        referees: '',
        judges: '',
        commissioner: '',
        observer: '',
        doctor: '',
        travelReferees: '',
        travelJudges: '',
        travelCommissioner: '',
        travelObserver: '',
        transportBus: '',
        transportPlane: '',
        transportShip: '',
        transportOther: '',
        accommodation: '',
        food: '',
        otherExpenses: '',
        otherExpensesNote: '',
    };
}

function matchTravelTotal(details) {
    const breakdown =
        Number(details.travelReferees || 0) +
        Number(details.travelJudges || 0) +
        Number(details.travelCommissioner || 0) +
        Number(details.travelObserver || 0);
    return breakdown > 0 ? breakdown : Number(details.travelAllowance || 0);
}

function matchExpenseTotal(details) {
    return (
        Number(details.referees || 0) +
        Number(details.judges || 0) +
        Number(details.commissioner || 0) +
        Number(details.observer || 0) +
        Number(details.doctor || 0) +
        matchTravelTotal(details) +
        Number(details.transportBus || 0) +
        Number(details.transportPlane || 0) +
        Number(details.transportShip || 0) +
        Number(details.transportOther || 0) +
        Number(details.accommodation || 0) +
        Number(details.food || 0) +
        Number(details.otherExpenses || 0)
    );
}

function AmountInput({ id, value, onChange }) {
    return (
        <div className="flex items-center gap-1">
            <input
                id={id}
                type="number"
                step="0.01"
                min="0"
                className={inputClass}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            <span className="text-xs text-ts-muted">€</span>
        </div>
    );
}

function MatchExpenseForm({ associationOptions = [], sportOptions = [] }) {
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        club_name: '',
        occurred_on: today,
        category: 'ΑΓΩΝΕΣ',
        subcategory: 'ΑΓΩΝΕΣ',
        notes: '',
        match_details: emptyMatchDetails(),
    });
    const matchTotal = matchExpenseTotal(form.data.match_details);
    const clubs = (associationOptions || []).filter((a) => a.active !== false);

    const setMatchField = (key, value) => {
        form.setData('match_details', {
            ...form.data.match_details,
            [key]: value,
        });
    };

    return (
        <Card>
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => {
                        const details = data.match_details;
                        const travel = matchTravelTotal(details);
                        return {
                            ...data,
                            amount: matchExpenseTotal(details),
                            description: `Αγώνας: ${String(details.teams || '').trim()}`,
                            sport: details.sport,
                            match_details: {
                                ...details,
                                travelAllowance: travel,
                                referees: Number(details.referees || 0),
                                judges: Number(details.judges || 0),
                                commissioner: Number(details.commissioner || 0),
                                observer: Number(details.observer || 0),
                                doctor: Number(details.doctor || 0),
                                travelReferees: Number(details.travelReferees || 0),
                                travelJudges: Number(details.travelJudges || 0),
                                travelCommissioner: Number(details.travelCommissioner || 0),
                                travelObserver: Number(details.travelObserver || 0),
                                transportBus: Number(details.transportBus || 0),
                                transportPlane: Number(details.transportPlane || 0),
                                transportShip: Number(details.transportShip || 0),
                                transportOther: Number(details.transportOther || 0),
                                accommodation: Number(details.accommodation || 0),
                                food: Number(details.food || 0),
                                otherExpenses: Number(details.otherExpenses || 0),
                            },
                        };
                    });
                    form.post(route('admin.finance.expenses.store'), {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset('notes');
                            form.setData('match_details', emptyMatchDetails());
                        },
                    });
                }}
            >
                <h4 className="text-sm font-semibold text-ts-fg">Νέα καταχώρηση εξόδου αγώνα</h4>
                <TitleAnalysisTable>
                    <TitleAnalysisRow title="Σωματείο" htmlFor="match-club">
                        <select
                            id="match-club"
                            className={inputClass}
                            value={form.data.club_name}
                            onChange={(e) => {
                                form.setData('club_name', e.target.value);
                                setMatchField('sport', '');
                            }}
                            required
                        >
                            <option value="">Επιλέξτε σωματείο…</option>
                            {clubs.map((c) => (
                                <option key={c.id} value={c.name}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Ημερομηνία" htmlFor="match-date">
                        <input
                            id="match-date"
                            type="date"
                            className={inputClass}
                            value={form.data.occurred_on}
                            onChange={(e) => form.setData('occurred_on', e.target.value)}
                            required
                        />
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Άθλημα" htmlFor="match-sport">
                        <select
                            id="match-sport"
                            className={inputClass}
                            value={form.data.match_details.sport}
                            onChange={(e) => setMatchField('sport', e.target.value)}
                            disabled={!form.data.club_name}
                            required
                        >
                            <option value="">
                                {form.data.club_name
                                    ? 'Επιλέξτε άθλημα…'
                                    : 'Επιλέξτε πρώτα σωματείο…'}
                            </option>
                            {(sportOptions || []).map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Κατηγορία" htmlFor="match-category">
                        <input
                            id="match-category"
                            type="text"
                            className={inputClass}
                            placeholder="π.χ. U16"
                            value={form.data.match_details.category}
                            onChange={(e) => setMatchField('category', e.target.value)}
                            required
                        />
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Ομάδες" htmlFor="match-teams">
                        <input
                            id="match-teams"
                            type="text"
                            className={inputClass}
                            placeholder="π.χ. Ολυμπιακός - Παναθηναϊκός"
                            value={form.data.match_details.teams}
                            onChange={(e) => setMatchField('teams', e.target.value)}
                            required
                        />
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Έξοδα">
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {[
                                ['referees', 'Διαιτητές'],
                                ['judges', 'Κριτές'],
                                ['commissioner', 'Κομισάριος'],
                                ['observer', 'Παρατηρητής / Video Observer'],
                                ['doctor', 'Ιατρός'],
                            ].map(([key, label]) => (
                                <label key={key} className="text-xs text-ts-muted">
                                    {label}
                                    <div className="mt-1">
                                        <AmountInput
                                            id={`match-${key}`}
                                            value={form.data.match_details[key]}
                                            onChange={(v) => setMatchField(key, v)}
                                        />
                                    </div>
                                </label>
                            ))}
                        </div>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Οδοιπορικά">
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                ['travelReferees', 'Διαιτητές'],
                                ['travelJudges', 'Κριτές'],
                                ['travelCommissioner', 'Κομισάριος'],
                                ['travelObserver', 'Παρατηρητής / Video Observer'],
                            ].map(([key, label]) => (
                                <label key={key} className="text-xs text-ts-muted">
                                    {label}
                                    <div className="mt-1">
                                        <AmountInput
                                            id={`match-${key}`}
                                            value={form.data.match_details[key]}
                                            onChange={(v) => setMatchField(key, v)}
                                        />
                                    </div>
                                </label>
                            ))}
                        </div>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Μετακίνηση">
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                ['transportBus', 'Λεωφορείο'],
                                ['transportPlane', 'Αεροπλάνο'],
                                ['transportShip', 'Πλοίο'],
                                ['transportOther', 'Άλλο'],
                            ].map(([key, label]) => (
                                <label key={key} className="text-xs text-ts-muted">
                                    {label}
                                    <div className="mt-1">
                                        <AmountInput
                                            id={`match-${key}`}
                                            value={form.data.match_details[key]}
                                            onChange={(v) => setMatchField(key, v)}
                                        />
                                    </div>
                                </label>
                            ))}
                        </div>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Διαμονή" htmlFor="match-accommodation">
                        <AmountInput
                            id="match-accommodation"
                            value={form.data.match_details.accommodation}
                            onChange={(v) => setMatchField('accommodation', v)}
                        />
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Διατροφή" htmlFor="match-food">
                        <AmountInput
                            id="match-food"
                            value={form.data.match_details.food}
                            onChange={(v) => setMatchField('food', v)}
                        />
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Λοιπά έξοδα" htmlFor="match-other-expenses">
                        <div className="grid gap-2 sm:grid-cols-[10rem_1fr]">
                            <AmountInput
                                id="match-other-expenses"
                                value={form.data.match_details.otherExpenses}
                                onChange={(v) => setMatchField('otherExpenses', v)}
                            />
                            <input
                                id="match-other-expenses-note"
                                type="text"
                                className={inputClass}
                                placeholder="Τι αφορά το έξοδο"
                                value={form.data.match_details.otherExpensesNote}
                                onChange={(e) => setMatchField('otherExpensesNote', e.target.value)}
                            />
                        </div>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Σύνολο αγώνα">
                        <strong className="text-base text-ts-fg">{euro(matchTotal)} €</strong>
                    </TitleAnalysisRow>
                    <TitleAnalysisRow title="Σημειώσεις" htmlFor="match-notes">
                        <textarea
                            id="match-notes"
                            rows={3}
                            className={inputClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </TitleAnalysisRow>
                </TitleAnalysisTable>

                {form.errors.club_name ||
                form.errors.amount ||
                form.errors['match_details.sport'] ||
                form.errors['match_details.category'] ||
                form.errors['match_details.teams'] ||
                form.errors.occurred_on ? (
                    <p className="text-sm text-rose-600">
                        {form.errors.club_name ||
                            form.errors.amount ||
                            form.errors['match_details.sport'] ||
                            form.errors['match_details.category'] ||
                            form.errors['match_details.teams'] ||
                            form.errors.occurred_on}
                    </p>
                ) : null}

                <button
                    type="submit"
                    disabled={form.processing || matchTotal <= 0}
                    className="rounded-md bg-ts-accent px-3 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
                >
                    {form.processing ? 'Αποθήκευση…' : 'Καταχώρηση εξόδου αγώνα'}
                </button>
            </form>
        </Card>
    );
}

function ExpensesPanel({
    categories,
    subcategory,
    rows,
    onSubcategoryChange,
    associationOptions = [],
    sportOptions = [],
    accounts = [],
}) {
    const isMatchForm = subcategory === 'ΑΓΩΝΕΣ';
    const total = rows.reduce((sum, row) => sum + Number(row.amount || 0), 0);

    return (
        <div className="space-y-4">
            <SectionHeader
                eyebrow="Κατηγορία"
                title="ΕΞΟΔΑ"
                description="Καταχώρηση εξόδων συλλόγου. Τα έξοδα αγώνων έχουν αναλυτική φόρμα."
                aside={<StatPill label="Σύνολο υποκατηγορίας" value={`${euro(total)} €`} />}
            />

            <TitleAnalysisTable>
                <TitleAnalysisRow title="Υποκατηγορία" htmlFor="expense-subcategory">
                    <select
                        id="expense-subcategory"
                        className={inputClass}
                        value={subcategory}
                        onChange={(e) => onSubcategoryChange(e.target.value)}
                    >
                        {(categories || []).map((item) => (
                            <option key={item} value={item}>
                                {item}
                            </option>
                        ))}
                    </select>
                    {isMatchForm ? (
                        <p className="mt-1 text-xs text-ts-muted">
                            Η υποκατηγορία ανοίγει αναλυτική φόρμα εξόδων αγώνα.
                        </p>
                    ) : null}
                </TitleAnalysisRow>
            </TitleAnalysisTable>

            {isMatchForm ? (
                <MatchExpenseForm
                    associationOptions={associationOptions}
                    sportOptions={sportOptions}
                />
            ) : (
                <Card>
                    <EntryForm
                        key={`expense-${subcategory}`}
                        routeName="admin.finance.expenses.store"
                        label="Νέα καταχώρηση εξόδου"
                        categories={categories}
                        subcategory={subcategory}
                        associationOptions={associationOptions}
                        sportOptions={sportOptions}
                        accounts={accounts}
                    />
                </Card>
            )}

            <EntryListToolbar total={total} count={rows.length} onPrint={() => window.print()} />
            <DataTable
                columns={[
                    { key: 'date', label: 'Ημερομηνία', render: (row) => formatDate(row.date) },
                    { key: 'description', label: 'Περιγραφή', render: (row) => row.description || '—' },
                    { key: 'club', label: 'Σωματείο', render: (row) => row.clubName || '—' },
                    { key: 'sport', label: 'Άθλημα', render: (row) => row.sport || '—' },
                    { key: 'amount', label: 'Ποσό', render: (row) => `${euro(row.amount)} €` },
                ]}
                rows={rows}
                emptyMessage="Δεν υπάρχουν εγγραφές για αυτή την υποκατηγορία."
            />
        </div>
    );
}

const KIND_LABELS = {
    cash: 'Μετρητά',
    bank: 'Τράπεζα',
    other: 'Άλλο',
};

function AccountsPanel({ accounts, closedMonths = [] }) {
    const accountForm = useForm({
        name: '',
        kind: 'cash',
        opening_balance: '0',
    });
    const closeForm = useForm({
        year_month: new Date().toISOString().slice(0, 7),
    });
    const sortedClosed = useMemo(
        () => [...(closedMonths || [])].sort().reverse(),
        [closedMonths],
    );

    return (
        <div className="space-y-4">
            <Card>
                <SectionHeader
                    title="Ταμεία / Λογαριασμοί"
                    description="Μετρητά, τράπεζα και υπόλοιπα βάσει εσόδων/εξόδων."
                />

                <form
                    className="grid gap-3 md:grid-cols-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        accountForm.post(route('admin.finance.accounts.store'), {
                            preserveScroll: true,
                            onSuccess: () => accountForm.reset('name', 'opening_balance'),
                        });
                    }}
                >
                    <label className="text-sm text-ts-muted">
                        Όνομα
                        <input
                            className={`${inputClass} mt-1`}
                            value={accountForm.data.name}
                            onChange={(e) => accountForm.setData('name', e.target.value)}
                            required
                        />
                    </label>
                    <label className="text-sm text-ts-muted">
                        Τύπος
                        <select
                            className={`${inputClass} mt-1`}
                            value={accountForm.data.kind}
                            onChange={(e) => accountForm.setData('kind', e.target.value)}
                        >
                            <option value="cash">Μετρητά</option>
                            <option value="bank">Τράπεζα</option>
                            <option value="other">Άλλο</option>
                        </select>
                    </label>
                    <label className="text-sm text-ts-muted">
                        Αρχικό υπόλοιπο
                        <input
                            type="number"
                            step="0.01"
                            className={`${inputClass} mt-1`}
                            value={accountForm.data.opening_balance}
                            onChange={(e) => accountForm.setData('opening_balance', e.target.value)}
                        />
                    </label>
                    <div className="md:col-span-3">
                        <button
                            type="submit"
                            disabled={accountForm.processing || !accountForm.data.name.trim()}
                            className="rounded-md bg-ts-accent px-3 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
                        >
                            + Νέο ταμείο
                        </button>
                    </div>
                </form>

                <div className="mt-4">
                    <DataTable
                        columns={[
                            { key: 'name', label: 'Όνομα', render: (row) => row.name },
                            {
                                key: 'type',
                                label: 'Τύπος',
                                render: (row) => KIND_LABELS[row.kind] || KIND_LABELS.cash,
                            },
                            {
                                key: 'opening',
                                label: 'Αρχικό',
                                render: (row) => `${euro(row.opening_balance)} €`,
                            },
                            {
                                key: 'balance',
                                label: 'Υπόλοιπο',
                                render: (row) => `${euro(row.opening_balance)} €`,
                            },
                        ]}
                        rows={(accounts || []).map((row) => ({ ...row, id: row.id }))}
                        emptyMessage="Δεν υπάρχουν ταμεία ακόμη."
                    />
                </div>
            </Card>

            <Card>
                <SectionHeader
                    title="Κλείσιμο μήνα"
                    description="Κλειστός μήνας μπλοκάρει νέες/τροποποιήσεις εσόδων και εξόδων για εκείνες τις ημερομηνίες."
                />
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (
                            !confirm(
                                `Κλείσιμο μήνα ${closeForm.data.year_month}; Δεν θα επιτρέπονται αλλαγές στις κινήσεις.`,
                            )
                        ) {
                            return;
                        }
                        closeForm.post(route('admin.finance.close-month'), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <label className="text-sm text-ts-muted">
                        Μήνας
                        <input
                            type="month"
                            className={`${inputClass} mt-1`}
                            value={closeForm.data.year_month}
                            onChange={(e) => closeForm.setData('year_month', e.target.value)}
                            required
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={closeForm.processing || !closeForm.data.year_month}
                        className="rounded-md bg-ts-accent px-3 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
                    >
                        Κλείσιμο μήνα
                    </button>
                </form>
                {sortedClosed.length > 0 ? (
                    <ul className="mt-3 space-y-1 text-sm text-ts-muted">
                        {sortedClosed.map((month) => (
                            <li key={month}>Κλειστός: {month}</li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-3 text-sm text-ts-muted">Κανένας μήνας κλειστός.</p>
                )}
            </Card>
        </div>
    );
}

function sumBalanceBucketRows(rows) {
    return (rows || []).reduce(
        (acc, row) => ({
            cash: acc.cash + Number(row.cash || 0),
            card: acc.card + Number(row.card || 0),
            bank: acc.bank + Number(row.bank || 0),
            online: acc.online + Number(row.online || 0),
            total: acc.total + Number(row.total || 0),
        }),
        { cash: 0, card: 0, bank: 0, online: 0, total: 0 },
    );
}

function printFinanceBalance(mode = 'full') {
    document.body.classList.add('printing-finance-balance');
    if (mode === 'collections') {
        document.body.classList.add('printing-finance-balance-collections');
    }
    const cleanup = () => {
        document.body.classList.remove('printing-finance-balance');
        document.body.classList.remove('printing-finance-balance-collections');
    };
    window.addEventListener('afterprint', cleanup, { once: true });
    window.requestAnimationFrame(() => {
        try {
            window.print();
        } finally {
            window.setTimeout(cleanup, 0);
        }
    });
}

function BalanceBucketTable({ title, rows, className = '' }) {
    const totals = sumBalanceBucketRows(rows);
    const list = rows || [];

    return (
        <Card className={className}>
            <h3 className="mb-3 text-base font-semibold text-ts-fg">{title}</h3>
            <div className="overflow-x-auto rounded-lg border border-ts-border">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-900 text-left text-xs uppercase tracking-wide text-white">
                        <tr>
                            <th className="px-3 py-2 font-semibold">Άθλημα</th>
                            <th className="px-3 py-2 font-semibold">Κατηγορία</th>
                            <th className="px-3 py-2 text-right font-semibold">Μετρητά</th>
                            <th className="px-3 py-2 text-right font-semibold">Κάρτες</th>
                            <th className="px-3 py-2 text-right font-semibold">Τράπεζα</th>
                            <th className="px-3 py-2 text-right font-semibold">Online</th>
                            <th className="px-3 py-2 text-right font-semibold">Ποσό</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-ts-border bg-ts-surface">
                        {list.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-3 py-6 text-center text-ts-muted">
                                    Δεν υπάρχουν δεδομένα στον πίνακα
                                </td>
                            </tr>
                        ) : (
                            list.map((row) => (
                                <tr key={row.key} className="text-ts-fg-soft">
                                    <td className="px-3 py-2">{row.sport || '—'}</td>
                                    <td className="px-3 py-2">{row.category || '—'}</td>
                                    <td className="px-3 py-2 text-right">{euro(row.cash)} €</td>
                                    <td className="px-3 py-2 text-right">{euro(row.card)} €</td>
                                    <td className="px-3 py-2 text-right">{euro(row.bank)} €</td>
                                    <td className="px-3 py-2 text-right">{euro(row.online)} €</td>
                                    <td className="px-3 py-2 text-right font-medium text-ts-fg">
                                        {euro(row.total)} €
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                    {list.length > 0 && (
                        <tfoot>
                            <tr className="border-t border-ts-border bg-ts-bg text-ts-fg">
                                <td className="px-3 py-2 font-semibold" colSpan={2}>
                                    Σύνολο
                                </td>
                                <td className="px-3 py-2 text-right font-semibold">{euro(totals.cash)} €</td>
                                <td className="px-3 py-2 text-right font-semibold">{euro(totals.card)} €</td>
                                <td className="px-3 py-2 text-right font-semibold">{euro(totals.bank)} €</td>
                                <td className="px-3 py-2 text-right font-semibold">{euro(totals.online)} €</td>
                                <td className="px-3 py-2 text-right font-semibold">{euro(totals.total)} €</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </Card>
    );
}

function BalancePanel({ balance, onApplyRange }) {
    const [from, setFrom] = useState(balance?.from || '');
    const [to, setTo] = useState(balance?.to || '');
    const [printMenuOpen, setPrintMenuOpen] = useState(false);

    useEffect(() => {
        setFrom(balance?.from || '');
        setTo(balance?.to || '');
    }, [balance?.from, balance?.to]);

    useEffect(() => {
        if (!printMenuOpen) {
            return undefined;
        }
        const onPointerDown = (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }
            if (!event.target.closest('[data-balance-print-menu]')) {
                setPrintMenuOpen(false);
            }
        };
        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [printMenuOpen]);

    const methods = balance?.collectionMethods || [];
    const methodTotal = methods.reduce(
        (acc, row) => ({
            count: acc.count + Number(row.count || 0),
            amount: acc.amount + Number(row.amount || 0),
        }),
        { count: 0, amount: 0 },
    );

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3 no-print">
                <h3 className="text-lg font-semibold text-ts-fg">
                    Ισοζύγιο ({formatSeasonRange(balance?.from, balance?.to)})
                </h3>
                <div className="flex items-center gap-2 text-sm text-ts-muted">
                    <a
                        href={route('admin.finance.balance.export', {
                            balance_from: balance?.from || from,
                            balance_to: balance?.to || to,
                        })}
                        className="hover:text-ts-fg"
                    >
                        Excel
                    </a>
                    <div className="relative" data-balance-print-menu>
                        <button
                            type="button"
                            className="inline-flex items-center gap-1.5 rounded-md border border-ts-border bg-ts-surface px-2.5 py-1.5 text-ts-fg-soft hover:bg-ts-hover"
                            aria-expanded={printMenuOpen}
                            aria-haspopup="menu"
                            onClick={() => setPrintMenuOpen((open) => !open)}
                        >
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                className="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                            >
                                <path d="M6 9V3h12v6" />
                                <path d="M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />
                                <path d="M6 13h12v8H6z" />
                            </svg>
                            Print
                        </button>
                        {printMenuOpen ? (
                            <div
                                className="absolute right-0 z-20 mt-1 min-w-[14rem] rounded-lg border border-ts-border bg-ts-surface py-1 shadow-lg"
                                role="menu"
                            >
                                <button
                                    type="button"
                                    role="menuitem"
                                    className="block w-full px-3 py-2 text-left text-sm text-ts-fg hover:bg-ts-hover"
                                    onClick={() => {
                                        setPrintMenuOpen(false);
                                        printFinanceBalance('full');
                                    }}
                                >
                                    Πλήρες ισοζύγιο
                                </button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    className="block w-full px-3 py-2 text-left text-sm text-ts-fg hover:bg-ts-hover"
                                    onClick={() => {
                                        setPrintMenuOpen(false);
                                        printFinanceBalance('collections');
                                    }}
                                >
                                    Μόνο εισπράξεις και έσοδα
                                </button>
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>

            <Card className="no-print">
                <SectionHeader
                    title="Περίοδος report εισπράξεων"
                    description="Επίλεξε ημερομηνίες και πάτησε «Προβολή report»."
                />
                <div className="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    <label className="text-sm text-ts-muted">
                        Από
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </label>
                    <label className="text-sm text-ts-muted">
                        Έως
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </label>
                    <button
                        type="button"
                        className="rounded-md bg-ts-accent px-4 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover"
                        onClick={() => onApplyRange(from, to)}
                    >
                        Προβολή report
                    </button>
                </div>
            </Card>

            <div id="balance-print-area" className="space-y-4">
                <div className="balance-summary grid gap-3 md:grid-cols-3">
                    <SummaryStatCard label="Έσοδα" value={`${euro(balance?.revenue)} €`} tone="positive" />
                    <SummaryStatCard label="Έξοδα" value={`${euro(balance?.expense)} €`} tone="negative" />
                    <SummaryStatCard
                        label="Υπόλοιπο"
                        value={`${euro(balance?.net)} €`}
                        tone={Number(balance?.net || 0) < 0 ? 'negative' : 'positive'}
                    />
                </div>

                <Card className="balance-collection-report">
                    <p className="text-xs font-semibold uppercase tracking-wide text-ts-accent">
                        Report εισπράξεων
                    </p>
                    <h4 className="mt-1 text-base font-semibold text-ts-fg">Ανά τρόπο πληρωμής</h4>
                    <p className="mb-3 text-xs text-ts-muted">{formatSeasonRange(balance?.from, balance?.to)}</p>
                    <DataTable
                        columns={[
                            { key: 'label', label: 'Τρόπος πληρωμής', render: (row) => row.label },
                            { key: 'count', label: 'Κινήσεις', render: (row) => row.count },
                            {
                                key: 'amount',
                                label: 'Εισπράχθηκαν',
                                render: (row) => `${euro(row.amount)} €`,
                            },
                        ]}
                        rows={[
                            ...methods.map((row) => ({ ...row, id: row.key })),
                            {
                                id: 'total',
                                label: 'Σύνολο',
                                count: methodTotal.count,
                                amount: methodTotal.amount,
                            },
                        ]}
                        emptyMessage="Δεν υπάρχουν εισπράξεις για την περίοδο."
                    />
                </Card>

                <BalanceBucketTable
                    title="Έσοδα"
                    rows={balance?.incomeRows || []}
                    className="balance-table-block"
                />
                <BalanceBucketTable
                    title="Έξοδα"
                    rows={balance?.expenseRows || []}
                    className="balance-table-block balance-table-block--expenses"
                />
            </div>
        </div>
    );
}

function BudgetPanel({
    budget,
    categories,
    associationOptions = [],
    sportOptions = [],
    onSeasonChange,
}) {
    const seasonStart = budget?.seasonStart;
    const prevSeason = seasonStart != null ? seasonStart - 1 : null;
    const nextSeason = seasonStart != null ? seasonStart + 1 : null;
    const [editingId, setEditingId] = useState(null);
    const budgetForm = useForm({
        season_start: seasonStart ?? new Date().getFullYear(),
        type: 'income',
        subcategory: '',
        amount: '',
        club_name: '',
        sport: '',
    });

    const subcategoryOptions =
        budgetForm.data.type === 'income'
            ? categories?.income || []
            : categories?.expense || [];

    const resetBudgetForm = () => {
        setEditingId(null);
        budgetForm.setData({
            season_start: seasonStart ?? new Date().getFullYear(),
            type: 'income',
            subcategory: '',
            amount: '',
            club_name: '',
            sport: '',
        });
        budgetForm.clearErrors();
    };

    const startEdit = (row) => {
        setEditingId(row.id);
        budgetForm.setData({
            season_start: seasonStart ?? new Date().getFullYear(),
            type: row.type || 'income',
            subcategory: row.subcategory || '',
            amount: String(row.amount ?? ''),
            club_name: row.clubName || '',
            sport: row.sport || '',
        });
        budgetForm.clearErrors();
    };

    const deleteLine = (row) => {
        if (!confirm('Διαγραφή γραμμής προϋπολογισμού;')) {
            return;
        }
        router.delete(route('admin.finance.budget.destroy', row.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (editingId === row.id) {
                    resetBudgetForm();
                }
            },
        });
    };

    return (
        <div className="space-y-4">
            <SectionHeader
                eyebrow="Κατηγορία"
                title="ΠΡΟΫΠΟΛΟΓΙΣΜΟΣ"
                description="Για Συνδρομές αθλητών το Πραγματικό προκύπτει από τις πληρωμές αθλητών (Συναλλαγές). Για τις υπόλοιπες υποκατηγορίες από τις καταχωρήσεις Έσοδα/Έξοδα."
                aside={
                    <StatPill label="Σεζόν" value={budget?.seasonLabel || '—'} />
                }
            />

            <div className="flex flex-wrap gap-2">
                {prevSeason != null ? (
                    <button
                        type="button"
                        className="rounded-full border border-ts-border px-3 py-1 text-sm hover:bg-ts-hover"
                        onClick={() => {
                            resetBudgetForm();
                            onSeasonChange(prevSeason);
                        }}
                    >
                        ← {`${prevSeason}-${String(prevSeason + 1).slice(-2)}`}
                    </button>
                ) : null}
                <span className="rounded-full bg-ts-accent px-3 py-1 text-sm text-ts-accent-fg">
                    {budget?.seasonLabel}
                </span>
                {nextSeason != null ? (
                    <button
                        type="button"
                        className="rounded-full border border-ts-border px-3 py-1 text-sm hover:bg-ts-hover"
                        onClick={() => {
                            resetBudgetForm();
                            onSeasonChange(nextSeason);
                        }}
                    >
                        {`${nextSeason}-${String(nextSeason + 1).slice(-2)}`} →
                    </button>
                ) : null}
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <GradientSummaryCard label="Προϋπ. εσόδων" value={`${euro(budget?.budgetedRevenue)} €`} />
                <GradientSummaryCard label="Πραγματικά έσοδα" value={`${euro(budget?.actualRevenue)} €`} />
                <GradientSummaryCard label="Προϋπ. εξόδων" value={`${euro(budget?.budgetedExpense)} €`} />
                <GradientSummaryCard label="Πραγματικά έξοδα" value={`${euro(budget?.actualExpense)} €`} />
            </div>

            <Card>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const options = {
                            preserveScroll: true,
                            onSuccess: () => resetBudgetForm(),
                        };
                        budgetForm.transform((data) => ({
                            ...data,
                            season_start: seasonStart ?? data.season_start,
                        }));
                        if (editingId) {
                            budgetForm.patch(route('admin.finance.budget.update', editingId), options);
                        } else {
                            budgetForm.post(route('admin.finance.budget.store'), options);
                        }
                    }}
                >
                    <TitleAnalysisTable>
                        <TitleAnalysisRow title="Τύπος">
                            <select
                                className={inputClass}
                                value={budgetForm.data.type}
                                onChange={(e) => {
                                    budgetForm.setData({
                                        ...budgetForm.data,
                                        type: e.target.value,
                                        subcategory: '',
                                    });
                                }}
                            >
                                <option value="income">Έσοδο</option>
                                <option value="expense">Έξοδο</option>
                            </select>
                        </TitleAnalysisRow>
                        <TitleAnalysisRow title="Υποκατηγορία">
                            <select
                                className={inputClass}
                                value={budgetForm.data.subcategory}
                                onChange={(e) => budgetForm.setData('subcategory', e.target.value)}
                                required
                            >
                                <option value="">Επιλέξτε…</option>
                                {subcategoryOptions.map((item) => (
                                    <option key={item} value={item}>
                                        {item}
                                    </option>
                                ))}
                            </select>
                        </TitleAnalysisRow>
                        <TitleAnalysisRow title="Ποσό σεζόν (€)">
                            <input
                                type="number"
                                step="0.01"
                                className={inputClass}
                                value={budgetForm.data.amount}
                                onChange={(e) => budgetForm.setData('amount', e.target.value)}
                                required
                            />
                        </TitleAnalysisRow>
                        <TitleAnalysisRow title="Σωματείο (προαιρ.)">
                            <select
                                className={inputClass}
                                value={budgetForm.data.club_name}
                                onChange={(e) => budgetForm.setData('club_name', e.target.value)}
                            >
                                <option value="">Όλα</option>
                                {(associationOptions || [])
                                    .filter((a) => a.active !== false)
                                    .map((a) => (
                                        <option key={a.id} value={a.name}>
                                            {a.name}
                                        </option>
                                    ))}
                            </select>
                        </TitleAnalysisRow>
                        <TitleAnalysisRow title="Άθλημα (προαιρ.)">
                            <select
                                className={inputClass}
                                value={budgetForm.data.sport}
                                onChange={(e) => budgetForm.setData('sport', e.target.value)}
                            >
                                <option value="">Όλα</option>
                                {(sportOptions || []).map((name) => (
                                    <option key={name} value={name}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </TitleAnalysisRow>
                    </TitleAnalysisTable>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {editingId ? (
                            <button
                                type="button"
                                onClick={resetBudgetForm}
                                className="rounded-md border border-ts-border px-3 py-2 text-sm hover:bg-ts-hover"
                            >
                                Ακύρωση
                            </button>
                        ) : null}
                        <button
                            type="submit"
                            disabled={
                                budgetForm.processing ||
                                !budgetForm.data.subcategory ||
                                budgetForm.data.amount === ''
                            }
                            className="rounded-md bg-ts-accent px-3 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
                        >
                            {editingId ? 'Ενημέρωση στόχου' : 'Προσθήκη στόχου'}
                        </button>
                    </div>
                </form>
            </Card>

            <DataTable
                columns={[
                    { key: 'type', label: 'Τύπος', render: (row) => (row.type === 'income' ? 'Έσοδο' : 'Έξοδο') },
                    { key: 'subcategory', label: 'Υποκατηγορία', render: (row) => row.subcategory },
                    { key: 'amount', label: 'Προϋπολογισμός', render: (row) => `${euro(row.amount)} €` },
                    { key: 'actual', label: 'Πραγματικό', render: (row) => `${euro(row.actual)} €` },
                    {
                        key: 'actions',
                        label: '',
                        render: (row) => (
                            <div className="flex gap-2 justify-end">
                                <button
                                    type="button"
                                    className="text-sky-600 hover:underline"
                                    onClick={() => startEdit(row)}
                                >
                                    Επεξεργασία
                                </button>
                                <button
                                    type="button"
                                    className="text-rose-600 hover:underline"
                                    onClick={() => deleteLine(row)}
                                >
                                    Διαγραφή
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={(budget?.lines || []).map((row) => ({ ...row, id: row.id }))}
                emptyMessage="Δεν υπάρχουν γραμμές προϋπολογισμού για αυτή τη σεζόν."
            />
        </div>
    );
}

function ReportsPanel({ reports, onPeriodChange }) {
    const cardsTop = [
        { label: 'Σύνολο εσόδων', value: reports?.totalRevenue },
        { label: 'Σύνολο εξόδων', value: reports?.totalExpense },
        { label: 'Υπόλοιπο', value: reports?.balance },
        { label: 'Οφειλές αθλητών', value: reports?.athleteDebts },
    ];
    const cardsToday = [
        { label: 'Έσοδα σήμερα', value: reports?.todayRevenue },
        { label: 'Έξοδα σήμερα', value: reports?.todayExpense },
        { label: 'Υπόλοιπο σήμερα', value: reports?.todayBalance },
    ];

    return (
        <div className="space-y-4">
            <SectionHeader
                eyebrow="Οικονομικά"
                title="ΑΝΑΦΟΡΕΣ"
                description="Αναλυτικές αναφορές εσόδων, εξόδων και οφειλών με φίλτρα ημερομηνίας. Προεπισκόπηση, εκτύπωση ή εξαγωγή Excel / PDF."
                aside={
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="rounded-md bg-ts-accent px-3 py-2 text-sm text-ts-accent-fg hover:bg-ts-accent-hover"
                            onClick={() => window.print()}
                        >
                            Προεπισκόπηση
                        </button>
                        <a
                            href={route('admin.finance.reports.export', {
                                reports_from: reports?.dateFrom,
                                reports_to: reports?.dateTo,
                            })}
                            className="rounded-md border border-ts-border px-3 py-2 text-sm hover:bg-ts-hover"
                        >
                            Excel
                        </a>
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-2 text-sm hover:bg-ts-hover"
                            onClick={() => window.print()}
                        >
                            PDF
                        </button>
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-2 text-sm hover:bg-ts-hover"
                            onClick={() => window.print()}
                        >
                            Εκτύπωση
                        </button>
                    </div>
                }
            />

            <Card>
                <h4 className="text-sm font-semibold text-ts-fg">Περίοδος</h4>
                <p className="text-sm text-ts-muted">Γρήγορη επιλογή σεζόν, τρέχοντος μήνα ή τρέχουσας ημέρας.</p>
                <div className="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        className="rounded-full border border-ts-border px-3 py-1 text-sm hover:bg-ts-hover"
                        onClick={() =>
                            onPeriodChange({
                                reports_from: `${new Date().getFullYear()}-08-01`,
                                reports_to: `${new Date().getFullYear() + 1}-07-31`,
                            })
                        }
                    >
                        Τρέχουσα σεζόν {reports?.currentSeasonLabel}
                    </button>
                    <button
                        type="button"
                        className="rounded-full border border-ts-border px-3 py-1 text-sm hover:bg-ts-hover"
                        onClick={() => {
                            const now = new Date();
                            const start = new Date(now.getFullYear(), now.getMonth(), 1);
                            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                            onPeriodChange({
                                reports_from: start.toISOString().slice(0, 10),
                                reports_to: end.toISOString().slice(0, 10),
                            });
                        }}
                    >
                        Τρέχων μήνας
                    </button>
                    <button
                        type="button"
                        className="rounded-full border border-ts-border px-3 py-1 text-sm hover:bg-ts-hover"
                        onClick={() => {
                            const today = new Date().toISOString().slice(0, 10);
                            onPeriodChange({ reports_from: today, reports_to: today });
                        }}
                    >
                        Τρέχουσα ημέρα
                    </button>
                </div>
            </Card>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {cardsTop.map((card) => (
                    <GradientSummaryCard key={card.label} label={card.label} value={`${euro(card.value)} €`} />
                ))}
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                {cardsToday.map((card) => (
                    <GradientSummaryCard key={card.label} label={card.label} value={`${euro(card.value)} €`} />
                ))}
            </div>
        </div>
    );
}

export default function Index({
    tab = 'analysis',
    tabs = [],
    date,
    analysis,
    revenueRows = [],
    expenseRows = [],
    revenueSubcategory,
    expenseSubcategory,
    accounts = [],
    closedMonths = [],
    balance,
    reports,
    budget,
    associationOptions = [],
    sportOptions = [],
    categories = {},
}) {
    const visit = (next = {}) => {
        router.get(
            route('admin.finance.index'),
            {
                tab,
                date,
                revenue_subcategory: revenueSubcategory,
                expense_subcategory: expenseSubcategory,
                season: budget?.seasonStart,
                balance_from: balance?.from,
                balance_to: balance?.to,
                reports_from: reports?.dateFrom,
                reports_to: reports?.dateTo,
                ...next,
            },
            { preserveState: true, replace: true },
        );
    };

    const tabButtons = useMemo(() => tabs, [tabs]);

    return (
        <AdminLayout title="Οικονομικά">
            <Head title="Οικονομικά" />

            <div className="mb-5">
                <h2 className="text-2xl font-semibold text-ts-fg">Οικονομικά</h2>
                <p className="text-sm text-ts-muted">
                    Έσοδα, έξοδα, προϋπολογισμός και αναφορές της ακαδημίας.
                </p>
            </div>

            <div className="mb-5 flex flex-wrap gap-2">
                {tabButtons.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => visit({ tab: item.id })}
                        className={`rounded-full border px-4 py-1.5 text-sm ${
                            tab === item.id
                                ? 'border-ts-accent bg-ts-accent text-ts-accent-fg'
                                : 'border-ts-border bg-ts-surface text-ts-fg-soft hover:bg-ts-hover'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            {tab === 'analysis' && (
                <AnalysisPanel analysis={analysis} date={date} onDateChange={(value) => visit({ date: value })} />
            )}

            {tab === 'revenues' && (
                <RevenuesPanel
                    categories={categories.income || []}
                    subcategory={revenueSubcategory}
                    rows={revenueRows}
                    onSubcategoryChange={(value) => visit({ revenue_subcategory: value })}
                    associationOptions={associationOptions}
                    sportOptions={sportOptions}
                    accounts={accounts}
                />
            )}

            {tab === 'expenses' && (
                <ExpensesPanel
                    categories={categories.expense || []}
                    subcategory={expenseSubcategory}
                    rows={expenseRows}
                    onSubcategoryChange={(value) => visit({ expense_subcategory: value })}
                    associationOptions={associationOptions}
                    sportOptions={sportOptions}
                    accounts={accounts}
                />
            )}

            {tab === 'accounts' && (
                <AccountsPanel accounts={accounts} closedMonths={closedMonths} />
            )}

            {tab === 'balance' && (
                <BalancePanel
                    balance={balance}
                    onApplyRange={(from, to) => visit({ balance_from: from, balance_to: to })}
                />
            )}

            {tab === 'budget' && (
                <BudgetPanel
                    budget={budget}
                    categories={categories}
                    associationOptions={associationOptions}
                    sportOptions={sportOptions}
                    onSeasonChange={(season) => visit({ season })}
                />
            )}

            {tab === 'reports' && (
                <ReportsPanel reports={reports} onPeriodChange={(period) => visit(period)} />
            )}
        </AdminLayout>
    );
}
