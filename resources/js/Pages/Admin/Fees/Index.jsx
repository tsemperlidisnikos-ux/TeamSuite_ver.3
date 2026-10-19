import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency, formatDate } from '@/lib/portalFormat';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const DEFAULT_MONTHS = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6];

function toggleMonth(list, month) {
    return list.includes(month) ? list.filter((m) => m !== month) : [...list, month];
}

function emptyWizard(seasons, monthLabels) {
    const season =
        seasons.find((s) => s.is_active)?.name ||
        seasons[seasons.length - 1]?.name ||
        '';
    return {
        season,
        sport: '',
        type_label: 'Συνδρομή',
        monthly_amount: 30,
        applies_to: 'all',
        class_id: '',
        athlete_id: '',
        months: DEFAULT_MONTHS.filter((m) => monthLabels.some((x) => x.month === m)),
        reminder_days: 7,
        registration_fee: 0,
        season_ticket_amount: 0,
        season_ticket_months: [],
        auto_generate: false,
        is_active: true,
    };
}

function ModalShell({ open, title, onClose, children, footer, wide }) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div
                className={`mt-8 w-full rounded-lg border border-ts-border bg-ts-surface shadow-xl ${
                    wide ? 'max-w-4xl' : 'max-w-3xl'
                }`}
            >
                <div className="flex items-center justify-between border-b border-ts-border px-4 py-3">
                    <h3 className="text-base font-semibold text-ts-fg">{title}</h3>
                    <button
                        type="button"
                        className="rounded-md px-2 py-1 text-sm text-ts-muted hover:bg-ts-hover"
                        onClick={onClose}
                    >
                        ✕
                    </button>
                </div>
                <div className="max-h-[70vh] overflow-y-auto px-4 py-4">{children}</div>
                {footer ? (
                    <div className="flex flex-wrap justify-end gap-2 border-t border-ts-border px-4 py-3">
                        {footer}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

export default function Index({
    rows,
    templates,
    reminders = [],
    classes = [],
    seasons = [],
    sports = [],
    athletes = [],
    filters,
    totals,
    defaultMonth,
    defaultYear,
    monthLabels = [],
    appliesToOptions = [],
}) {
    const flash = usePage().props.flash;
    const [selected, setSelected] = useState([]);
    const [panel, setPanel] = useState(null);
    const [athleteSearch, setAthleteSearch] = useState('');
    const [selectedTemplateIds, setSelectedTemplateIds] = useState([]);

    const wizard = useForm(emptyWizard(seasons, monthLabels));

    const bulkForm = useForm({
        athlete_ids: [],
        amount: '',
        template_id: '',
        month: defaultMonth,
        year: defaultYear,
        comments: 'Μηνιαία χρέωση',
        use_monthly_fee: true,
    });

    const apply = (patch) => {
        router.get(route('admin.fees.index'), { ...filters, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const allIds = useMemo(() => rows.map((r) => r.id), [rows]);
    const allSelected = selected.length > 0 && selected.length === allIds.length;

    const toggleAll = () => setSelected(allSelected ? [] : allIds);
    const toggleOne = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const openNewCharge = () => {
        wizard.setData(emptyWizard(seasons, monthLabels));
        setAthleteSearch('');
        setPanel('newCharge');
    };

    const openCreateCharges = () => {
        const first = templates[0]?.id ?? '';
        setSelectedTemplateIds(first ? [first] : []);
        setPanel('createCharges');
    };

    const openReminders = () => setPanel('reminders');

    const filteredClasses = useMemo(() => {
        if (!wizard.data.sport) return classes;
        return classes.filter((c) => (c.sport || '') === wizard.data.sport);
    }, [classes, wizard.data.sport]);

    const athleteHits = useMemo(() => {
        if (wizard.data.applies_to !== 'athlete') return [];
        const q = athleteSearch.trim().toLowerCase();
        if (!q) return [];
        return athletes
            .filter((a) => !wizard.data.sport || a.sport === wizard.data.sport)
            .filter((a) =>
                `${a.last_name} ${a.first_name}`.toLowerCase().includes(q),
            )
            .slice(0, 12);
    }, [athletes, athleteSearch, wizard.data.applies_to, wizard.data.sport]);

    const selectedAthlete = athletes.find((a) => a.id === wizard.data.athlete_id);

    const templateSummary = (tpl) => {
        const sport = tpl.sport || 'Όλα';
        const applies =
            appliesToOptions.find((o) => o.value === tpl.applies_to)?.label ||
            tpl.applies_to ||
            'Όλοι';
        return `${tpl.season || '—'} · ${sport} · ${applies} · ${formatCurrency(tpl.amount)} · ${(tpl.months || []).length} μήνες`;
    };

    const saveTemplate = (e) => {
        e.preventDefault();
        wizard.post(route('admin.fees.templates.store'), {
            onSuccess: () => {
                setPanel('createCharges');
                wizard.setData(emptyWizard(seasons, monthLabels));
            },
        });
    };

    const generateCharges = () => {
        if (!selectedTemplateIds.length) {
            alert('Επιλέξτε τουλάχιστον ένα πρότυπο χρέωσης.');
            return;
        }
        const msg =
            selectedTemplateIds.length === 1
                ? 'Δημιουργία χρεώσεων για τους ενεργούς αθλητές του προτύπου;'
                : `Δημιουργία χρεώσεων από ${selectedTemplateIds.length} πρότυπα;`;
        if (!confirm(msg)) return;
        router.post(route('admin.fees.generate-from-templates'), {
            template_ids: selectedTemplateIds,
            year: defaultYear,
        });
    };

    const sendReminders = (athleteIds = null) => {
        const ids = athleteIds ?? reminders.map((r) => r.athlete_id);
        if (!ids.length) {
            alert('Δεν υπάρχουν οφειλές προς υπενθύμιση.');
            return;
        }
        if (
            !confirm(
                `Αποστολή υπενθύμισης οφειλών σε ${ids.length} παραλήπτη/ες με email;`,
            )
        ) {
            return;
        }
        router.post(route('admin.fees.remind-debts'), {
            athlete_ids: ids,
            send: true,
        });
    };

    return (
        <AdminLayout title="Συνδρομές / Πληρωμές">
            <Head title="Συνδρομές / Πληρωμές" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold text-ts-fg">Συνδρομές / Πληρωμές</h2>
                    <p className="text-sm text-ts-muted">
                        Υπόλοιπα συνδρομών, πρότυπα χρεώσεων και υπενθυμίσεις οφειλών.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                        onClick={openCreateCharges}
                    >
                        Προβολή / Δημιουργία χρεώσεων
                    </button>
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                        onClick={openReminders}
                    >
                        Υπενθύμιση οφειλών
                    </button>
                    <button
                        type="button"
                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                        onClick={openNewCharge}
                    >
                        + Νέα χρέωση
                    </button>
                </div>
            </div>

            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-lg border border-ts-border bg-ts-surface px-4 py-3">
                    <div className="text-[11px] uppercase tracking-wide text-ts-muted">Οφειλές</div>
                    <div className="text-2xl font-semibold text-rose-700">
                        {formatCurrency(totals.owed)}
                    </div>
                    <div className="text-xs text-ts-muted">{totals.with_debt} αθλητές</div>
                </div>
                <div className="rounded-lg border border-ts-border bg-ts-surface px-4 py-3">
                    <div className="text-[11px] uppercase tracking-wide text-ts-muted">Πιστωτικά</div>
                    <div className="text-2xl font-semibold text-emerald-800">
                        {formatCurrency(totals.credit)}
                    </div>
                </div>
                <div className="rounded-lg border border-ts-border bg-ts-surface px-4 py-3">
                    <div className="text-[11px] uppercase tracking-wide text-ts-muted">
                        Ενεργοί αθλητές
                    </div>
                    <div className="text-2xl font-semibold text-ts-fg">{totals.active_athletes}</div>
                </div>
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <input
                    className="min-w-[220px] flex-1 rounded-md border border-ts-border bg-ts-surface px-3 py-2 text-sm text-ts-fg"
                    placeholder="Αναζήτηση αθλητή..."
                    defaultValue={filters.q}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') apply({ q: e.currentTarget.value });
                    }}
                />
                <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                    <input
                        type="checkbox"
                        checked={filters.owed === '1'}
                        onChange={(e) => apply({ owed: e.target.checked ? '1' : '' })}
                    />
                    Μόνο οφειλές
                </label>
                <Link
                    href={route('admin.transactions.index')}
                    className="rounded-md border border-ts-border px-3 py-2 text-sm hover:bg-ts-hover"
                >
                    Μετάβαση σε Συναλλαγές
                </Link>
            </div>

            <form
                id="fees-bulk"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(
                        route('admin.fees.bulk-charge'),
                        { ...bulkForm.data, athlete_ids: selected },
                        { onSuccess: () => setSelected([]) },
                    );
                }}
                className="mb-6 grid gap-2 rounded-lg border border-ts-border bg-ts-surface p-4 sm:grid-cols-4"
            >
                <p className="sm:col-span-4 text-xs font-semibold uppercase tracking-wider text-ts-muted">
                    Γρήγορη δημιουργία χρεώσεων ({selected.length} επιλεγμένοι)
                </p>
                <label className="flex items-center gap-2 text-sm text-ts-fg-soft sm:col-span-2">
                    <input
                        type="checkbox"
                        checked={bulkForm.data.use_monthly_fee}
                        onChange={(e) => bulkForm.setData('use_monthly_fee', e.target.checked)}
                    />
                    Χρήση μηνιαίας συνδρομής αθλητή
                </label>
                <select
                    className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                    value={bulkForm.data.template_id}
                    onChange={(e) => {
                        bulkForm.setData('template_id', e.target.value);
                        if (e.target.value) bulkForm.setData('use_monthly_fee', false);
                    }}
                    disabled={bulkForm.data.use_monthly_fee}
                >
                    <option value="">Πρότυπο (προαιρετικό)</option>
                    {templates
                        .filter((t) => t.is_active)
                        .map((t) => (
                            <option key={t.id} value={t.id}>
                                {t.name} ({formatCurrency(t.amount)})
                            </option>
                        ))}
                </select>
                <input
                    type="number"
                    step="0.01"
                    placeholder="Σταθερό ποσό"
                    className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                    value={bulkForm.data.amount}
                    onChange={(e) => {
                        bulkForm.setData('amount', e.target.value);
                        bulkForm.setData('use_monthly_fee', false);
                    }}
                    disabled={bulkForm.data.use_monthly_fee || !!bulkForm.data.template_id}
                />
                <input
                    type="number"
                    min={1}
                    max={12}
                    className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                    value={bulkForm.data.month}
                    onChange={(e) => bulkForm.setData('month', Number(e.target.value))}
                />
                <input
                    type="number"
                    className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                    value={bulkForm.data.year}
                    onChange={(e) => bulkForm.setData('year', Number(e.target.value))}
                />
                <button
                    type="submit"
                    disabled={!selected.length}
                    className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg disabled:opacity-40 sm:w-fit"
                >
                    Δημιουργία χρεώσεων
                </button>
            </form>

            <div className="mb-8 overflow-x-auto rounded-lg border border-ts-border bg-ts-surface">
                <table className="min-w-full text-sm">
                    <thead className="bg-ts-surface-2 text-xs uppercase text-ts-muted">
                        <tr>
                            <th className="w-8 px-3 py-2">
                                <input type="checkbox" checked={allSelected} onChange={toggleAll} />
                            </th>
                            <th className="px-3 py-2 text-left">Επώνυμο</th>
                            <th className="px-3 py-2 text-left">Όνομα</th>
                            <th className="px-3 py-2 text-left">Τμήμα</th>
                            <th className="px-3 py-2 text-left">Μηνιαία συνδρομή</th>
                            <th className="px-3 py-2 text-left">Υπόλοιπο</th>
                            <th className="px-3 py-2 text-left">Τελευταία πληρωμή</th>
                            <th className="px-3 py-2 text-right" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.id} className="border-t border-ts-border">
                                <td className="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(r.id)}
                                        onChange={() => toggleOne(r.id)}
                                    />
                                </td>
                                <td className="px-3 py-2 font-medium text-ts-fg">{r.last_name}</td>
                                <td className="px-3 py-2 font-medium text-ts-fg">{r.first_name}</td>
                                <td className="px-3 py-2 text-ts-muted">{r.class_names || '—'}</td>
                                <td className="px-3 py-2 text-ts-muted">
                                    {formatCurrency(r.monthly_fee || 0)}
                                </td>
                                <td className="px-3 py-2">
                                    <span
                                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                            r.owed > 0.009
                                                ? 'bg-rose-100 text-rose-800'
                                                : 'bg-slate-100 text-slate-600'
                                        }`}
                                    >
                                        {formatCurrency(r.owed)}
                                    </span>
                                </td>
                                <td className="px-3 py-2 text-ts-muted">
                                    {r.last_payment_amount != null
                                        ? `${formatCurrency(r.last_payment_amount)} · ${formatDate(r.last_payment_at)}`
                                        : '—'}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    <div className="flex flex-wrap justify-end gap-2">
                                        {r.owed > 0.009 ? (
                                            <button
                                                type="button"
                                                className="rounded-md border border-ts-border px-2 py-1 text-xs hover:bg-ts-hover"
                                                onClick={() => sendReminders([r.id])}
                                            >
                                                Υπενθύμιση
                                            </button>
                                        ) : null}
                                        <Link
                                            href={route('admin.athletes.show', r.id)}
                                            className="rounded-md border border-ts-border px-2 py-1 text-xs hover:bg-ts-hover"
                                        >
                                            Προφίλ
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {!rows.length && (
                            <tr>
                                <td colSpan={8} className="px-3 py-8 text-center text-ts-muted">
                                    Δεν βρέθηκαν αθλητές.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <ModalShell
                open={panel === 'newCharge'}
                title="Νέα χρέωση"
                onClose={() => setPanel(null)}
                footer={
                    <>
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                            onClick={() => setPanel(null)}
                        >
                            Ακύρωση
                        </button>
                        <button
                            type="button"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            disabled={wizard.processing}
                            onClick={saveTemplate}
                        >
                            Αποθήκευση
                        </button>
                    </>
                }
            >
                <form onSubmit={saveTemplate} className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm">
                            <span className="mb-1 block text-ts-muted">Σεζόν</span>
                            <select
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                value={wizard.data.season}
                                onChange={(e) => wizard.setData('season', e.target.value)}
                            >
                                {seasons.map((s) => (
                                    <option key={s.id || s.name} value={s.name}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-ts-muted">Άθλημα</span>
                            <select
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                value={wizard.data.sport}
                                onChange={(e) => wizard.setData('sport', e.target.value)}
                            >
                                <option value="">Όλα</option>
                                {sports.map((name) => (
                                    <option key={name} value={name}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <p className="text-xs text-ts-muted">
                        Ισχύει για μηνιαία συνδρομή, εγγραφή και εισιτήριο διαρκείας. Αν επιλεγεί
                        άθλημα, η χρέωση εφαρμόζεται μόνο σε αθλητές αυτού του αθλήματος.
                    </p>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={!!wizard.data.auto_generate}
                            onChange={(e) => wizard.setData('auto_generate', e.target.checked)}
                        />
                        Αυτόματη μηνιαία χρέωση (στο login, 1×/μήνα)
                    </label>

                    <section className="space-y-2 border-t border-ts-border pt-4">
                        <h4 className="font-semibold text-ts-fg">Εγγραφή</h4>
                        <label className="block text-sm">
                            <span className="mb-1 block text-ts-muted">Εγγραφή (€)</span>
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 sm:max-w-xs"
                                value={wizard.data.registration_fee || ''}
                                onChange={(e) =>
                                    wizard.setData(
                                        'registration_fee',
                                        e.target.value === '' ? 0 : Number(e.target.value),
                                    )
                                }
                            />
                        </label>
                        <p className="text-xs text-ts-muted">
                            Προστίθεται μία φορά στην πρώτη μηνιαία χρέωση κάθε αθλητή για αυτό το
                            πρότυπο.
                        </p>
                    </section>

                    <section className="space-y-3 border-t border-ts-border pt-4">
                        <h4 className="font-semibold text-ts-fg">Μηνιαία συνδρομή</h4>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <label className="block text-sm">
                                <span className="mb-1 block text-ts-muted">Τύπος</span>
                                <input
                                    className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                    value={wizard.data.type_label}
                                    onChange={(e) => wizard.setData('type_label', e.target.value)}
                                />
                            </label>
                            <label className="block text-sm">
                                <span className="mb-1 block text-ts-muted">Μηνιαίο ποσό (€)</span>
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                    value={wizard.data.monthly_amount || ''}
                                    onChange={(e) =>
                                        wizard.setData(
                                            'monthly_amount',
                                            e.target.value === '' ? 0 : Number(e.target.value),
                                        )
                                    }
                                />
                            </label>
                            <label className="block text-sm">
                                <span className="mb-1 block text-ts-muted">Ισχύει για</span>
                                <select
                                    className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                    value={wizard.data.applies_to}
                                    onChange={(e) => {
                                        const applies_to = e.target.value;
                                        wizard.setData({
                                            ...wizard.data,
                                            applies_to,
                                            class_id: applies_to === 'class' ? wizard.data.class_id : '',
                                            athlete_id:
                                                applies_to === 'athlete' ? wizard.data.athlete_id : '',
                                        });
                                        if (applies_to !== 'athlete') setAthleteSearch('');
                                    }}
                                >
                                    {appliesToOptions.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        {wizard.data.applies_to === 'class' ? (
                            <label className="block text-sm">
                                <span className="mb-1 block text-ts-muted">Τμήμα</span>
                                <select
                                    className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                    value={wizard.data.class_id}
                                    onChange={(e) => wizard.setData('class_id', e.target.value)}
                                >
                                    <option value="">Επιλέξτε τμήμα…</option>
                                    {filteredClasses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                            {c.sport ? ` · ${c.sport}` : ''}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}

                        {wizard.data.applies_to === 'athlete' ? (
                            <div className="text-sm">
                                <span className="mb-1 block text-ts-muted">Αθλητής</span>
                                {selectedAthlete ? (
                                    <div className="flex items-center gap-2">
                                        <strong>
                                            {selectedAthlete.last_name} {selectedAthlete.first_name}
                                        </strong>
                                        <button
                                            type="button"
                                            className="text-xs text-ts-accent"
                                            onClick={() => {
                                                wizard.setData('athlete_id', '');
                                                setAthleteSearch('');
                                            }}
                                        >
                                            Αλλαγή
                                        </button>
                                    </div>
                                ) : (
                                    <>
                                        <input
                                            className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                            value={athleteSearch}
                                            onChange={(e) => setAthleteSearch(e.target.value)}
                                            placeholder="Αναζήτηση επωνύμου, ονόματος…"
                                            autoComplete="off"
                                        />
                                        {athleteSearch.trim() ? (
                                            <ul className="mt-1 max-h-40 overflow-y-auto rounded border border-ts-border">
                                                {athleteHits.length === 0 ? (
                                                    <li className="px-2 py-1.5 text-ts-muted">
                                                        Δεν βρέθηκε αθλητής.
                                                    </li>
                                                ) : (
                                                    athleteHits.map((a) => (
                                                        <li key={a.id}>
                                                            <button
                                                                type="button"
                                                                className="block w-full px-2 py-1.5 text-left hover:bg-ts-hover"
                                                                onClick={() => {
                                                                    wizard.setData('athlete_id', a.id);
                                                                    setAthleteSearch('');
                                                                }}
                                                            >
                                                                {a.last_name} {a.first_name}
                                                            </button>
                                                        </li>
                                                    ))
                                                )}
                                            </ul>
                                        ) : (
                                            <p className="mt-1 text-xs text-ts-muted">
                                                Γράψτε επώνυμο ή όνομα για να επιλέξετε τον αθλητή.
                                            </p>
                                        )}
                                    </>
                                )}
                            </div>
                        ) : null}

                        <div>
                            <span className="mb-1 block text-sm text-ts-muted">Μήνες χρέωσης</span>
                            <div className="flex flex-wrap gap-2">
                                {monthLabels.map((item) => (
                                    <label
                                        key={item.month}
                                        className="inline-flex items-center gap-1 rounded-md border border-ts-border px-2 py-1 text-xs"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={(wizard.data.months || []).includes(item.month)}
                                            onChange={() =>
                                                wizard.setData(
                                                    'months',
                                                    toggleMonth(wizard.data.months || [], item.month),
                                                )
                                            }
                                        />
                                        {item.label}
                                    </label>
                                ))}
                            </div>
                            <p className="mt-1 text-xs text-ts-muted">
                                Επίλεξε τους μήνες της σεζόν που θα δημιουργείται η μηνιαία χρέωση.
                            </p>
                        </div>

                        <label className="block text-sm sm:max-w-xs">
                            <span className="mb-1 block text-ts-muted">
                                Υπενθύμιση (ημέρες μετά την οφειλή)
                            </span>
                            <input
                                type="number"
                                min={0}
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                value={wizard.data.reminder_days}
                                onChange={(e) =>
                                    wizard.setData('reminder_days', Number(e.target.value) || 0)
                                }
                            />
                        </label>
                    </section>

                    <section className="space-y-3 border-t border-ts-border pt-4">
                        <h4 className="font-semibold text-ts-fg">Εισιτήριο Διαρκείας</h4>
                        <label className="block text-sm sm:max-w-xs">
                            <span className="mb-1 block text-ts-muted">Συνολικό ποσό (€)</span>
                            <input
                                type="number"
                                min={0}
                                step="0.01"
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5"
                                value={wizard.data.season_ticket_amount || ''}
                                onChange={(e) =>
                                    wizard.setData(
                                        'season_ticket_amount',
                                        e.target.value === '' ? 0 : Number(e.target.value),
                                    )
                                }
                            />
                        </label>
                        <div>
                            <span className="mb-1 block text-sm text-ts-muted">Μήνες εισιτηρίου</span>
                            <div className="flex flex-wrap gap-2">
                                {monthLabels.map((item) => (
                                    <label
                                        key={`ticket-${item.month}`}
                                        className="inline-flex items-center gap-1 rounded-md border border-ts-border px-2 py-1 text-xs"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={(wizard.data.season_ticket_months || []).includes(
                                                item.month,
                                            )}
                                            onChange={() =>
                                                wizard.setData(
                                                    'season_ticket_months',
                                                    toggleMonth(
                                                        wizard.data.season_ticket_months || [],
                                                        item.month,
                                                    ),
                                                )
                                            }
                                        />
                                        {item.label}
                                    </label>
                                ))}
                            </div>
                            <p className="mt-1 text-xs text-ts-muted">
                                Το ποσό μοιράζεται ισόποσα στους μήνες που επιλέγεις.
                            </p>
                        </div>
                    </section>
                </form>
            </ModalShell>

            <ModalShell
                open={panel === 'createCharges'}
                title="Δημιουργία χρεώσεων"
                wide
                onClose={() => setPanel(null)}
                footer={
                    <>
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                            onClick={() => setPanel(null)}
                        >
                            Κλείσιμο
                        </button>
                        <button
                            type="button"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            onClick={generateCharges}
                            disabled={!templates.length}
                        >
                            Δημιουργία χρεώσεων
                        </button>
                    </>
                }
            >
                <p className="mb-3 text-sm text-ts-muted">
                    Επίλεξε αποθηκευμένο πρότυπο και δημιούργησε χρεώσεις στους ενεργούς αθλητές
                    (χωρίς διπλοεγγραφές για τον ίδιο μήνα).
                </p>
                {templates.length === 0 ? (
                    <div className="rounded-md border border-dashed border-ts-border px-4 py-10 text-center">
                        <h4 className="mb-1 font-semibold text-ts-fg">Δεν υπάρχουν πρότυπα</h4>
                        <p className="mb-3 text-sm text-ts-muted">
                            Πάτα «Νέα χρέωση» για να ορίσεις το πρώτο πρότυπο.
                        </p>
                        <button
                            type="button"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            onClick={openNewCharge}
                        >
                            + Νέα χρέωση
                        </button>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-xs uppercase text-ts-muted">
                                <tr>
                                    <th className="w-8 py-1">
                                        <input
                                            type="checkbox"
                                            checked={
                                                templates.length > 0 &&
                                                selectedTemplateIds.length === templates.length
                                            }
                                            onChange={(e) =>
                                                setSelectedTemplateIds(
                                                    e.target.checked
                                                        ? templates.map((t) => t.id)
                                                        : [],
                                                )
                                            }
                                        />
                                    </th>
                                    <th className="py-1 text-left">Σεζόν</th>
                                    <th className="py-1 text-left">Άθλημα</th>
                                    <th className="py-1 text-left">Μηνιαίο</th>
                                    <th className="py-1 text-left">Εγγραφή</th>
                                    <th className="py-1 text-left">Εισιτήριο</th>
                                    <th className="py-1 text-left">Αυτόματο</th>
                                    <th className="py-1 text-right" />
                                </tr>
                            </thead>
                            <tbody>
                                {templates.map((tpl) => {
                                    const checked = selectedTemplateIds.includes(tpl.id);
                                    return (
                                        <tr key={tpl.id} className="border-t border-ts-border">
                                            <td className="py-2">
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() =>
                                                        setSelectedTemplateIds((prev) =>
                                                            prev.includes(tpl.id)
                                                                ? prev.filter((id) => id !== tpl.id)
                                                                : [...prev, tpl.id],
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="py-2" title={templateSummary(tpl)}>
                                                {tpl.season || '—'}
                                            </td>
                                            <td className="py-2">{tpl.sport || 'Όλα'}</td>
                                            <td className="py-2">{formatCurrency(tpl.amount)}</td>
                                            <td className="py-2">
                                                {formatCurrency(tpl.registration_fee || 0)}
                                            </td>
                                            <td className="py-2">
                                                {formatCurrency(tpl.season_ticket_amount || 0)}
                                            </td>
                                            <td className="py-2">
                                                {tpl.auto_generate ? 'Ναι' : 'Όχι'}
                                            </td>
                                            <td className="py-2 text-right">
                                                <button
                                                    type="button"
                                                    className="text-rose-600"
                                                    onClick={() => {
                                                        if (confirm('Διαγραφή προτύπου χρέωσης;')) {
                                                            router.delete(
                                                                route(
                                                                    'admin.fees.templates.destroy',
                                                                    tpl.id,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Διαγραφή
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </ModalShell>

            <ModalShell
                open={panel === 'reminders'}
                title="Υπενθύμιση οφειλών"
                wide
                onClose={() => setPanel(null)}
                footer={
                    <>
                        <button
                            type="button"
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                            onClick={() => setPanel(null)}
                        >
                            Κλείσιμο
                        </button>
                        <button
                            type="button"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                            onClick={() => sendReminders()}
                            disabled={!reminders.length}
                        >
                            Αποστολή όλων
                        </button>
                    </>
                }
            >
                <p className="mb-3 text-sm text-ts-muted">
                    Αθλητές με οφειλή μετά τις ημέρες υπενθύμισης. Η αποστολή γίνεται με email στους
                    κηδεμόνες (ουρά εργασιών).
                </p>
                {reminders.length === 0 ? (
                    <p className="text-sm text-ts-muted">
                        Δεν υπάρχουν οφειλές προς υπενθύμιση αυτή τη στιγμή.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-xs uppercase text-ts-muted">
                                <tr>
                                    <th className="py-1 text-left">Αθλητής</th>
                                    <th className="py-1 text-left">Οφειλή</th>
                                    <th className="py-1 text-left">Ημέρες</th>
                                    <th className="py-1 text-left">Παλαιότερη χρέωση</th>
                                    <th className="py-1 text-right" />
                                </tr>
                            </thead>
                            <tbody>
                                {reminders.map((row) => (
                                    <tr key={row.athlete_id} className="border-t border-ts-border">
                                        <td className="py-2">
                                            <strong>{row.athlete_name}</strong>
                                            <div className="text-xs text-ts-muted">
                                                {row.email || '—'}
                                                {row.phone ? ` · ${row.phone}` : ''}
                                            </div>
                                        </td>
                                        <td className="py-2">{formatCurrency(row.balance)}</td>
                                        <td className="py-2">
                                            {row.days_overdue} / {row.reminder_days}
                                        </td>
                                        <td className="py-2">
                                            {row.oldest_charge_date
                                                ? formatDate(row.oldest_charge_date)
                                                : '—'}
                                        </td>
                                        <td className="py-2 text-right">
                                            <button
                                                type="button"
                                                className="rounded-md border border-ts-border px-2 py-1 text-xs"
                                                onClick={() => sendReminders([row.athlete_id])}
                                                disabled={!row.email}
                                            >
                                                Υπενθύμιση
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </ModalShell>
        </AdminLayout>
    );
}
