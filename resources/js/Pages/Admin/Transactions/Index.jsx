import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const MONTHS = [
    [1, 'Ιανουάριος'],
    [2, 'Φεβρουάριος'],
    [3, 'Μάρτιος'],
    [4, 'Απρίλιος'],
    [5, 'Μάιος'],
    [6, 'Ιούνιος'],
    [7, 'Ιούλιος'],
    [8, 'Αύγουστος'],
    [9, 'Σεπτέμβριος'],
    [10, 'Οκτώβριος'],
    [11, 'Νοέμβριος'],
    [12, 'Δεκέμβριος'],
];

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const btnPrimary = 'rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';

function euro(n) {
    return Number(n || 0).toLocaleString('el-GR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function Panel({ title, onPrev, onNext, children, footer }) {
    return (
        <section className="flex min-h-0 flex-col overflow-hidden rounded-lg border border-ts-border bg-ts-surface">
            <div className="flex items-center justify-between gap-2 border-b border-ts-border bg-gradient-to-r from-[#0b1f3a] to-[#123a4a] px-3 py-2.5">
                {onPrev ? (
                    <button
                        type="button"
                        onClick={onPrev}
                        className="rounded px-2 py-1 text-ts-fg-soft hover:bg-ts-surface/10"
                        aria-label="Προηγούμενο"
                    >
                        ‹
                    </button>
                ) : (
                    <span className="w-7" />
                )}
                <h3 className="text-center text-sm font-semibold text-ts-fg">{title}</h3>
                {onNext ? (
                    <button
                        type="button"
                        onClick={onNext}
                        className="rounded px-2 py-1 text-ts-fg-soft hover:bg-ts-surface/10"
                        aria-label="Επόμενο"
                    >
                        ›
                    </button>
                ) : (
                    <span className="w-7" />
                )}
            </div>
            <div className="min-h-0 flex-1 p-3">{children}</div>
            {footer && (
                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-ts-border px-3 py-2 text-xs text-ts-muted">
                    {footer}
                </div>
            )}
        </section>
    );
}

const MEMBERS_SPORT_FILTER = '__members__';

export default function Index({
    athletes = [],
    members = [],
    showingMembers = false,
    selected,
    transactions = [],
    monthRows = [],
    balance,
    sports = [],
    seasonStart,
    seasonLabel,
    filters,
}) {
    const flash = usePage().props.flash;
    const [query, setQuery] = useState(filters?.q || '');
    const now = new Date();
    const isMember = showingMembers || selected?.party_type === 'member';
    const listRows = isMember ? members : athletes;
    const partyLabel = isMember ? 'Μέλη' : 'Αθλητές';

    const form = useForm({
        athlete_id: selected?.party_type === 'member' ? '' : selected?.id || '',
        club_member_id: selected?.party_type === 'member' ? selected?.id || '' : '',
        type: 'charge',
        amount: '',
        day: now.getDate(),
        month: now.getMonth() + 1,
        year: now.getFullYear(),
        payment_method: 'cash',
        receipt_number: '',
        comments: '',
    });

    const apply = (patch) => {
        router.get(
            route('admin.transactions.index'),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    };

    const selectParty = (id, partyType = 'athlete') => {
        if (partyType === 'member') {
            form.setData({
                ...form.data,
                athlete_id: '',
                club_member_id: id,
            });
            apply({
                member_id: id,
                athlete_id: '',
                sport: MEMBERS_SPORT_FILTER,
            });
            return;
        }
        form.setData({
            ...form.data,
            athlete_id: id,
            club_member_id: '',
        });
        apply({ athlete_id: id, member_id: '' });
    };

    const seasonNavPatch = {
        season_start: undefined,
        athlete_id: filters.athlete_id || '',
        member_id: filters.member_id || '',
        sport: filters.sport || '',
    };

    const totals = useMemo(
        () =>
            (monthRows || []).reduce(
                (acc, row) => ({
                    charge: acc.charge + Number(row.charge || 0),
                    payment: acc.payment + Number(row.payment || 0),
                    balance: acc.balance + Number(row.balance || 0),
                    trainings: acc.trainings + Number(row.trainings || 0),
                    present: acc.present + Number(row.present || 0),
                    absent: acc.absent + Number(row.absent || 0),
                }),
                { charge: 0, payment: 0, balance: 0, trainings: 0, present: 0, absent: 0 },
            ),
        [monthRows],
    );

    const seasonTitle = seasonLabel || `${seasonStart}-${seasonStart + 1}`;

    return (
        <AdminLayout title="Συναλλαγές">
            <Head title="Συναλλαγές" />

            <div className="mb-4">
                <h2 className="text-xl font-semibold text-ts-fg">Συναλλαγές</h2>
                <p className="text-sm text-ts-muted">
                    Χρεώσεις, πληρωμές και υπόλοιπα αθλητών και μελών ανά σεζόν.
                </p>
            </div>

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-2">
                <div className="space-y-4">
                    <Panel
                        title={`${partyLabel} - ${seasonTitle}`}
                        onPrev={() =>
                            apply({
                                ...seasonNavPatch,
                                season_start: seasonStart - 1,
                            })
                        }
                        onNext={() =>
                            apply({
                                ...seasonNavPatch,
                                season_start: seasonStart + 1,
                            })
                        }
                        footer={
                            <>
                                <input
                                    className={`${field} max-w-xs`}
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') apply({ q: query });
                                    }}
                                    placeholder={
                                        isMember ? 'Αναζήτηση μελών...' : 'Αναζήτηση αθλητών...'
                                    }
                                />
                                <span>
                                    {listRows.length} Εγγραφές
                                </span>
                            </>
                        }
                    >
                        <label className="mb-3 block text-[11px] font-semibold uppercase tracking-wide text-ts-muted">
                            Άθλημα
                            <select
                                className={`${field} mt-1`}
                                value={filters.sport || ''}
                                onChange={(e) =>
                                    apply({
                                        sport: e.target.value,
                                        q: query,
                                        athlete_id: '',
                                        member_id: '',
                                    })
                                }
                            >
                                <option value="">Όλα τα αθλήματα</option>
                                <option value={MEMBERS_SPORT_FILTER}>Μέλη</option>
                                {sports.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="max-h-[22rem] overflow-auto rounded border border-ts-border">
                            <table className="min-w-full text-left text-xs">
                                <thead className="sticky top-0 bg-ts-surface-2 text-[10px] uppercase tracking-wide text-ts-muted">
                                    {isMember ? (
                                        <tr>
                                            <th className="px-2 py-2">Επώνυμο</th>
                                            <th className="px-2 py-2">Όνομα</th>
                                            <th className="px-2 py-2">Πόλη</th>
                                            <th className="px-2 py-2">Email</th>
                                            <th className="px-2 py-2">Τηλέφωνο</th>
                                            <th className="px-2 py-2 text-right">Υπόλοιπο σεζόν</th>
                                        </tr>
                                    ) : (
                                        <tr>
                                            <th className="px-2 py-2">ΑΜΚΑ</th>
                                            <th className="px-2 py-2">Αρ. Δελτίου</th>
                                            <th className="px-2 py-2">Επώνυμο</th>
                                            <th className="px-2 py-2">Όνομα</th>
                                            <th className="px-2 py-2">Πατρώνυμο</th>
                                            <th className="px-2 py-2">Ημ. Γέννησης</th>
                                            <th className="px-2 py-2 text-right">Υπόλοιπο σεζόν</th>
                                        </tr>
                                    )}
                                </thead>
                                <tbody>
                                    {isMember
                                        ? members.map((m) => (
                                              <tr
                                                  key={m.id}
                                                  onClick={() => selectParty(m.id, 'member')}
                                                  className={`cursor-pointer border-t border-ts-border ${
                                                      selected?.id === m.id
                                                          ? 'bg-sky-950/50 text-sky-100'
                                                          : 'text-ts-fg-soft hover:bg-ts-surface/[0.03]'
                                                  }`}
                                              >
                                                  <td className="px-2 py-1.5 font-medium">{m.last_name}</td>
                                                  <td className="px-2 py-1.5">{m.first_name}</td>
                                                  <td className="px-2 py-1.5">{m.city || '—'}</td>
                                                  <td className="px-2 py-1.5">{m.email || '—'}</td>
                                                  <td className="px-2 py-1.5">{m.phone || '—'}</td>
                                                  <td className="px-2 py-1.5 text-right tabular-nums">
                                                      {euro(m.season_balance)} €
                                                  </td>
                                              </tr>
                                          ))
                                        : athletes.map((a) => (
                                              <tr
                                                  key={a.id}
                                                  onClick={() => selectParty(a.id, 'athlete')}
                                                  className={`cursor-pointer border-t border-ts-border ${
                                                      selected?.id === a.id
                                                          ? 'bg-sky-950/50 text-sky-100'
                                                          : 'text-ts-fg-soft hover:bg-ts-surface/[0.03]'
                                                  }`}
                                              >
                                                  <td className="px-2 py-1.5 font-mono text-[11px]">
                                                      {a.amka || '—'}
                                                  </td>
                                                  <td className="px-2 py-1.5">
                                                      {a.registration_number || '—'}
                                                  </td>
                                                  <td className="px-2 py-1.5 font-medium">{a.last_name}</td>
                                                  <td className="px-2 py-1.5">{a.first_name}</td>
                                                  <td className="px-2 py-1.5">
                                                      {a.father_first_name || a.father_name || '—'}
                                                  </td>
                                                  <td className="px-2 py-1.5">{a.birth_date || '—'}</td>
                                                  <td className="px-2 py-1.5 text-right tabular-nums">
                                                      {euro(a.season_balance)} €
                                                  </td>
                                              </tr>
                                          ))}
                                    {!listRows.length && (
                                        <tr>
                                            <td
                                                colSpan={isMember ? 6 : 7}
                                                className="px-3 py-8 text-center text-ts-muted"
                                            >
                                                {isMember
                                                    ? 'Δεν βρέθηκαν μέλη.'
                                                    : 'Δεν βρέθηκαν αθλητές.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel title="Νέα κίνηση">
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (!selected?.id) return;
                                const payload =
                                    selected.party_type === 'member'
                                        ? { athlete_id: '', club_member_id: selected.id }
                                        : { athlete_id: selected.id, club_member_id: '' };
                                form.transform((data) => ({ ...data, ...payload }));
                                form.post(
                                    route('admin.transactions.store'),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            form.setData({
                                                ...form.data,
                                                ...payload,
                                                amount: '',
                                                receipt_number: '',
                                                comments: '',
                                                type: 'charge',
                                            });
                                        },
                                    },
                                );
                            }}
                        >
                            <label className="block text-xs text-ts-muted">
                                Ονοματεπώνυμο
                                <input
                                    className={`${field} mt-1 text-ts-fg-soft`}
                                    readOnly
                                    disabled
                                    value={
                                        selected
                                            ? `${selected.last_name} ${selected.first_name}`
                                            : ''
                                    }
                                    placeholder={
                                        isMember
                                            ? 'Επιλέξτε μέλος από τη λίστα'
                                            : 'Επιλέξτε αθλητή από τη λίστα'
                                    }
                                />
                            </label>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="block text-xs text-ts-muted">
                                    Τύπος κίνησης
                                    <select
                                        className={`${field} mt-1`}
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                    >
                                        <option value="charge">Χρέωση</option>
                                        <option value="payment">Πληρωμή</option>
                                    </select>
                                </label>
                                <label className="block text-xs text-ts-muted">
                                    Ποσό (€)
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        className={`${field} mt-1`}
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                    />
                                </label>
                            </div>
                            <div className="grid grid-cols-3 gap-2">
                                <label className="block text-xs text-ts-muted">
                                    Ημέρα
                                    <input
                                        type="number"
                                        min={1}
                                        max={31}
                                        className={`${field} mt-1`}
                                        value={form.data.day}
                                        onChange={(e) => form.setData('day', Number(e.target.value))}
                                    />
                                </label>
                                <label className="block text-xs text-ts-muted">
                                    Μήνας
                                    <select
                                        className={`${field} mt-1`}
                                        value={form.data.month}
                                        onChange={(e) => form.setData('month', Number(e.target.value))}
                                    >
                                        {MONTHS.map(([v, l]) => (
                                            <option key={v} value={v}>
                                                {l}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="block text-xs text-ts-muted">
                                    Έτος
                                    <input
                                        type="number"
                                        min={2000}
                                        max={2100}
                                        className={`${field} mt-1`}
                                        value={form.data.year}
                                        onChange={(e) => form.setData('year', Number(e.target.value))}
                                    />
                                </label>
                            </div>
                            <label className="block text-xs text-ts-muted">
                                Αρ. Απόδειξης
                                <input
                                    className={`${field} mt-1`}
                                    value={form.data.type === 'charge' ? '' : form.data.receipt_number}
                                    disabled={form.data.type === 'charge'}
                                    readOnly={form.data.type === 'charge'}
                                    onChange={(e) => {
                                        if (form.data.type === 'charge') {
                                            return;
                                        }
                                        form.setData('receipt_number', e.target.value);
                                    }}
                                />
                            </label>
                            <label className="block text-xs text-ts-muted">
                                Τρόπος πληρωμής
                                <select
                                    className={`${field} mt-1`}
                                    value={form.data.type === 'charge' ? '' : form.data.payment_method}
                                    disabled={form.data.type === 'charge'}
                                    onChange={(e) => {
                                        if (form.data.type === 'charge') {
                                            return;
                                        }
                                        form.setData('payment_method', e.target.value);
                                    }}
                                >
                                    <option value="">—</option>
                                    <option value="cash">Μετρητά</option>
                                    <option value="card">Κάρτα</option>
                                    <option value="transfer">Μεταφορά</option>
                                    <option value="online">Online</option>
                                    <option value="other">Άλλο</option>
                                </select>
                            </label>
                            <label className="block text-xs text-ts-muted">
                                Σχόλια
                                <textarea
                                    rows={2}
                                    maxLength={90}
                                    className={`${field} mt-1`}
                                    placeholder="Προαιρετικά σχόλια για τη κίνηση"
                                    value={form.data.comments}
                                    onChange={(e) =>
                                        form.setData('comments', e.target.value.slice(0, 90))
                                    }
                                />
                            </label>
                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    className={btnPrimary}
                                    disabled={!selected || form.processing}
                                >
                                    Αποθήκευση
                                </button>
                            </div>
                        </form>
                    </Panel>
                </div>

                <div className="space-y-4">
                    <Panel
                        title={seasonTitle}
                        onPrev={() =>
                            apply({
                                ...seasonNavPatch,
                                season_start: seasonStart - 1,
                            })
                        }
                        onNext={() =>
                            apply({
                                ...seasonNavPatch,
                                season_start: seasonStart + 1,
                            })
                        }
                    >
                        <p className="mb-3 text-xs text-ts-muted">
                            Εμφανίζονται μόνο καταχωρημένες χρεώσεις και πληρωμές της επιλεγμένης
                            σεζόν
                            {selected ? (
                                <>
                                    {' · '}
                                    {selected.party_type === 'member' ? (
                                        <span className="text-ts-fg">
                                            {selected.last_name} {selected.first_name}
                                        </span>
                                    ) : (
                                        <Link
                                            href={route('admin.athletes.show', selected.id)}
                                            className="text-ts-accent hover:text-sky-300"
                                        >
                                            {selected.last_name} {selected.first_name}
                                        </Link>
                                    )}
                                    {balance ? (
                                        <span className="ms-2 text-ts-muted">
                                            (υπόλοιπο {euro(balance.owed)} €)
                                        </span>
                                    ) : null}
                                </>
                            ) : null}
                        </p>
                        <div className="max-h-[22rem] overflow-auto rounded border border-ts-border">
                            <table className="min-w-full text-left text-xs">
                                <thead className="sticky top-0 bg-ts-surface-2 text-[10px] uppercase tracking-wide text-ts-muted">
                                    <tr>
                                        <th className="px-2 py-2" rowSpan={2}>
                                            Μήνας/Έτος
                                        </th>
                                        <th className="px-2 py-2 text-center" colSpan={3}>
                                            Οικονομικά στοιχεία
                                        </th>
                                        <th className="px-2 py-2 text-center" colSpan={3}>
                                            Παρουσιολόγιο
                                        </th>
                                    </tr>
                                    <tr>
                                        <th className="px-2 py-1 text-right">Χρέωση</th>
                                        <th className="px-2 py-1 text-right">Πληρωμή</th>
                                        <th className="px-2 py-1 text-right">Υπόλοιπο</th>
                                        <th className="px-2 py-1 text-right">Προπονήσεις</th>
                                        <th className="px-2 py-1 text-right">Παρουσίες</th>
                                        <th className="px-2 py-1 text-right">Απουσίες</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(monthRows || []).map((row) => (
                                        <tr key={`${row.year}-${row.month}`} className="border-t border-ts-border text-ts-fg-soft">
                                            <td className="px-2 py-1.5">{row.label}</td>
                                            <td className="px-2 py-1.5 text-right tabular-nums">{euro(row.charge)}</td>
                                            <td className="px-2 py-1.5 text-right tabular-nums">{euro(row.payment)}</td>
                                            <td className="px-2 py-1.5 text-right tabular-nums">{euro(row.balance)}</td>
                                            <td className="px-2 py-1.5 text-right">{row.trainings}</td>
                                            <td className="px-2 py-1.5 text-right">{row.present}</td>
                                            <td className="px-2 py-1.5 text-right">{row.absent}</td>
                                        </tr>
                                    ))}
                                    {selected && (
                                        <tr className="border-t border-ts-border bg-ts-surface-2 font-medium text-ts-fg">
                                            <td className="px-2 py-2">Σύνολα</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{euro(totals.charge)}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{euro(totals.payment)}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{euro(totals.balance)}</td>
                                            <td className="px-2 py-2 text-right">{totals.trainings}</td>
                                            <td className="px-2 py-2 text-right">{totals.present}</td>
                                            <td className="px-2 py-2 text-right">{totals.absent}</td>
                                        </tr>
                                    )}
                                    {!selected && (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-8 text-center text-ts-muted">
                                                {isMember
                                                    ? 'Επιλέξτε μέλος για μηνιαία σύνοψη.'
                                                    : 'Επιλέξτε αθλητή για μηνιαία σύνοψη.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel
                        title={`Κινήσεις ${seasonTitle}`}
                        footer={<span>{transactions.length} Εγγραφές</span>}
                    >
                        {!selected ? (
                            <div className="rounded-md border border-sky-900/50 bg-sky-950/30 px-3 py-3 text-sm text-sky-100/90">
                                {isMember
                                    ? 'Αναζητήστε και επιλέξτε μέλος για να εμφανιστούν τα οικονομικά στοιχεία και οι συναλλαγές.'
                                    : 'Αναζητήστε και επιλέξτε αθλητή για να εμφανιστούν τα οικονομικά στοιχεία και οι συναλλαγές.'}
                            </div>
                        ) : (
                            <div className="max-h-[18rem] overflow-auto rounded border border-ts-border">
                                <table className="min-w-full text-sm">
                                    <thead className="sticky top-0 bg-ts-surface-2 text-[10px] uppercase tracking-wide text-ts-muted">
                                        <tr>
                                            <th className="px-2 py-2 text-left">Τύπος</th>
                                            <th className="px-2 py-2 text-right">Ποσό</th>
                                            <th className="px-2 py-2 text-left">Περίοδος</th>
                                            <th className="px-2 py-2 text-left">Απόδειξη</th>
                                            <th className="px-2 py-2 text-left">Τρόπος</th>
                                            <th className="px-2 py-2 text-left">Σχόλια</th>
                                            <th className="px-2 py-2 text-right">Ενέργεια</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {transactions.map((t) => (
                                            <tr key={t.id} className="border-t border-ts-border text-ts-fg-soft">
                                                <td className="px-2 py-1.5">
                                                    <span
                                                        className={
                                                            t.type === 'charge'
                                                                ? 'text-orange-400'
                                                                : 'text-emerald-800'
                                                        }
                                                    >
                                                        {t.type === 'charge' ? 'Χρέωση' : 'Πληρωμή'}
                                                    </span>
                                                </td>
                                                <td className="px-2 py-1.5 text-right tabular-nums text-ts-fg">
                                                    {euro(t.amount)} €
                                                </td>
                                                <td className="px-2 py-1.5">
                                                    {t.month || '—'}/{t.year || '—'}
                                                </td>
                                                <td className="px-2 py-1.5">{t.receipt_number || '—'}</td>
                                                <td className="px-2 py-1.5">{t.payment_method || '—'}</td>
                                                <td className="max-w-[10rem] truncate px-2 py-1.5">
                                                    {t.comments || '—'}
                                                </td>
                                                <td className="px-2 py-1.5 text-right">
                                                    <button
                                                        type="button"
                                                        className="text-rose-400 hover:text-rose-700"
                                                        onClick={() => {
                                                            if (confirm('Διαγραφή συναλλαγής;')) {
                                                                router.delete(
                                                                    route('admin.transactions.destroy', t.id),
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        Διαγραφή
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                        {!transactions.length && (
                                            <tr>
                                                <td colSpan={7} className="px-3 py-8 text-center text-ts-muted">
                                                    Δεν υπάρχουν κινήσεις στη σεζόν.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </AdminLayout>
    );
}
