import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/portalFormat';
import { Head, router } from '@inertiajs/react';
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

function matchTri(value, flag) {
    if (value === 'yes') return !!flag;
    if (value === 'no') return !flag;
    return true;
}

export default function Index({
    athletes = [],
    classes = [],
    sports = [],
    filters = {},
    yearOptions = [],
}) {
    const [selected, setSelected] = useState('athletes-registry');
    const [q, setQ] = useState('');
    const [sport, setSport] = useState('');
    const [teamId, setTeamId] = useState('');
    const [gender, setGender] = useState('');
    const [active, setActive] = useState('yes');
    const [monthlyCharge, setMonthlyCharge] = useState('');
    const [registrationFee, setRegistrationFee] = useState('');
    const [seasonTicket, setSeasonTicket] = useState('');
    const [customCharge, setCustomCharge] = useState('');
    const [hasBalance, setHasBalance] = useState('yes');
    const [month, setMonth] = useState(filters.month || new Date().getMonth() + 1);
    const [year, setYear] = useState(filters.year || new Date().getFullYear());
    const [payStatus, setPayStatus] = useState('');
    const [showResults, setShowResults] = useState(false);

    const current = PRINT_TYPES.find((t) => t.id === selected) || PRINT_TYPES[0];

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

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return (athletes || []).filter((a) => {
            if (needle && !`${a.last_name} ${a.first_name} ${a.sport || ''}`.toLowerCase().includes(needle)) {
                return false;
            }
            if (sport && a.sport !== sport) return false;
            if (teamId && !(a.class_ids || []).includes(teamId)) return false;
            if (gender && a.gender !== gender) return false;
            if (active === 'yes' && a.status !== 'active') return false;
            if (active === 'no' && a.status === 'active') return false;
            if (!matchTri(monthlyCharge, a.monthly_charge !== false)) return false;
            if (!matchTri(registrationFee, a.registration_charge)) return false;
            if (!matchTri(seasonTicket, a.season_ticket)) return false;
            if (!matchTri(customCharge, a.custom_charge)) return false;
            if (selected === 'debtors' || selected === 'athlete-balances') {
                const bal = Number(a.month_remaining ?? 0);
                // balances use month snapshot as proxy when full ledger not loaded
                if (hasBalance === 'yes' && bal <= 0 && selected === 'athlete-balances') {
                    /* keep all for balances unless month remaining filter matters */
                }
            }
            if (selected === 'month-subscription') {
                const expected = a.status !== 'inactive' && a.monthly_charge !== false;
                if (a.month_pay_status === 'no_charge' && !expected) return false;
                if (payStatus && a.month_pay_status !== payStatus) return false;
            }
            if (selected === 'debtors') {
                if (Number(a.month_remaining) <= 0) return false;
            }
            return true;
        });
    }, [
        athletes,
        q,
        sport,
        teamId,
        gender,
        active,
        monthlyCharge,
        registrationFee,
        seasonTicket,
        customCharge,
        selected,
        payStatus,
        hasBalance,
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
        if (selected !== 'month-subscription') {
            return null;
        }
        return filtered.reduce(
            (acc, a) => ({
                charged: acc.charged + Number(a.month_charged || 0),
                paid: acc.paid + Number(a.month_paid || 0),
                remaining: acc.remaining + Number(a.month_remaining || 0),
            }),
            { charged: 0, paid: 0, remaining: 0 },
        );
    }, [filtered, selected]);

    const exportCsv = () => {
        const header =
            selected === 'month-subscription'
                ? 'Επώνυμο,Όνομα,Τμήμα,Κατάσταση,Χρέωση,Πληρωμή,Υπόλοιπο,Τηλ. γονέα'
                : 'Επώνυμο,Όνομα,Άθλημα,Τμήμα';
        const lines = filtered.map((a) => {
            if (selected === 'month-subscription') {
                return [
                    a.last_name,
                    a.first_name,
                    a.class_names || '',
                    a.month_pay_status_label || '',
                    a.month_charged,
                    a.month_paid,
                    a.month_remaining,
                    a.guardian_phone || a.phone || '',
                ]
                    .map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`)
                    .join(',');
            }
            return `"${a.last_name}","${a.first_name}","${a.sport || ''}","${a.class_names || ''}"`;
        });
        const blob = new Blob([[header, ...lines].join('\n')], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${current.id}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const inputCls = 'w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm';

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
                            <p className="text-sm text-ts-muted">
                                {selected === 'athletes-registry'
                                    ? 'Φίλτρα αναζήτησης και εκτύπωση λίστας. Η «Χρέωση μήνα = Όχι» δείχνει μόνο αθλητές με απενεργοποιημένη μηνιαία χρέωση στο προφίλ.'
                                    : selected === 'month-subscription'
                                      ? 'Ποιοι έχουν πληρώσει τη μηνιαία συνδρομή συγκεκριμένου μήνα και ποιοι όχι.'
                                      : selected === 'legal-forms'
                                        ? 'Εκτύπωση φόρμας συναίνεσης GDPR και φόρμας εγγραφής αθλητή.'
                                        : 'Φίλτρα · Αναζήτηση · Εξαγωγή XLSX · Εκτύπωση'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={exportCsv}
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

                    <div className="mb-4 space-y-2 rounded-md border border-ts-border bg-ts-bg/40 p-3">
                        {selected === 'month-subscription' ? (
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
                            </>
                        ) : null}

                        {selected === 'athletes-registry' ? (
                            <>
                                <FilterRow label="Από Ημερομηνία">
                                    <input className={inputCls} type="date" />
                                </FilterRow>
                                <FilterRow label="Έως Ημερομηνία">
                                    <input className={inputCls} type="date" />
                                </FilterRow>
                            </>
                        ) : (
                            <FilterRow label="Αναζήτηση">
                                <input
                                    className={inputCls}
                                    placeholder="Επώνυμο ή όνομα"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                />
                            </FilterRow>
                        )}
                        <FilterRow label="Άθλημα">
                            <select
                                className={inputCls}
                                value={sport}
                                onChange={(e) => {
                                    setSport(e.target.value);
                                    setTeamId('');
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
                        <FilterRow label="Τμήματα">
                            <select
                                className={inputCls}
                                value={teamId}
                                onChange={(e) => setTeamId(e.target.value)}
                            >
                                <option value="">Όλα τα τμήματα</option>
                                {teamOptions.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </FilterRow>
                        {selected === 'athletes-registry' ? (
                            <FilterRow label="Έτος Γέννησης">
                                <div className="flex gap-2">
                                    <select className={inputCls} defaultValue="=">
                                        <option value="=">=</option>
                                        <option value=">">&gt;</option>
                                        <option value="<">&lt;</option>
                                    </select>
                                    <input className={inputCls} placeholder="YYYY" />
                                </div>
                            </FilterRow>
                        ) : null}
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
                        <FilterRow label="Ενεργοί">
                            <TriState value={active} onChange={setActive} />
                        </FilterRow>
                        <FilterRow label="Χρέωση μήνα">
                            <TriState value={monthlyCharge} onChange={setMonthlyCharge} />
                        </FilterRow>

                        {selected === 'athletes-registry' ? (
                            <>
                                <FilterRow label="Χρέωση Εγγραφής">
                                    <TriState value={registrationFee} onChange={setRegistrationFee} />
                                </FilterRow>
                                <FilterRow label="Φωτογραφία">
                                    <TriState value={seasonTicket} onChange={setSeasonTicket} />
                                </FilterRow>
                                <FilterRow label="Κάρτα Υγείας">
                                    <TriState value={customCharge} onChange={setCustomCharge} />
                                </FilterRow>
                                <FilterRow label="Σωματείο">
                                    <select className={inputCls} defaultValue="">
                                        <option value="">Όλα</option>
                                    </select>
                                </FilterRow>
                            </>
                        ) : null}

                        {selected === 'athlete-balances' ? (
                            <FilterRow label="Έχει υπόλοιπο">
                                <TriState value={hasBalance} onChange={setHasBalance} />
                            </FilterRow>
                        ) : null}

                        {selected === 'month-subscription' ? (
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
                        ) : null}

                        <div className="pt-1">
                            <button
                                type="button"
                                className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                                onClick={() => setShowResults(true)}
                            >
                                Αναζήτηση
                            </button>
                            {selected === 'month-subscription' && showResults ? (
                                <p className="mt-2 text-xs text-ts-muted">{monthSummary}</p>
                            ) : null}
                        </div>
                    </div>

                    {showResults ||
                    selected === 'athletes-registry' ||
                    selected === 'month-subscription' ||
                    selected === 'medical-data' ||
                    selected === 'debtors' ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm print:text-black">
                                <thead>
                                    <tr className="text-left text-xs uppercase text-ts-muted">
                                        <th className="py-1 pe-3">#</th>
                                        <th className="py-1 pe-3">Επώνυμο</th>
                                        <th className="py-1 pe-3">Όνομα</th>
                                        {selected === 'month-subscription' ? (
                                            <>
                                                <th className="py-1 pe-3">Τμήμα</th>
                                                <th className="py-1 pe-3">Κατάσταση</th>
                                                <th className="py-1 pe-3">Χρέωση</th>
                                                <th className="py-1 pe-3">Πληρωμή</th>
                                                <th className="py-1 pe-3">Υπόλοιπο μήνα</th>
                                                <th className="py-1">Τηλ. γονέα</th>
                                            </>
                                        ) : (
                                            <>
                                                <th className="py-1 pe-3">Άθλημα</th>
                                                <th className="py-1">Τμήμα</th>
                                            </>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((a, index) => (
                                        <tr key={a.id} className="border-t border-ts-border">
                                            <td className="py-1.5 pe-3">{index + 1}</td>
                                            <td className="py-1.5 pe-3">{a.last_name}</td>
                                            <td className="py-1.5 pe-3">{a.first_name}</td>
                                            {selected === 'month-subscription' ? (
                                                <>
                                                    <td className="py-1.5 pe-3">
                                                        {a.class_names || '—'}
                                                    </td>
                                                    <td className="py-1.5 pe-3">
                                                        {a.month_pay_status_label}
                                                    </td>
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
                                            ) : (
                                                <>
                                                    <td className="py-1.5 pe-3">{a.sport || '—'}</td>
                                                    <td className="py-1.5">{a.class_names || '—'}</td>
                                                </>
                                            )}
                                        </tr>
                                    ))}
                                    {!filtered.length && (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="py-8 text-center text-ts-muted"
                                            >
                                                Δεν βρέθηκαν εγγραφές με αυτά τα φίλτρα.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                {selected === 'month-subscription' &&
                                    filtered.length > 0 &&
                                    monthTotals && (
                                        <tfoot>
                                            <tr className="border-t-2 border-ts-border bg-ts-surface-2/60 font-semibold text-ts-fg">
                                                <td className="py-2 pe-3" colSpan={5}>
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
                    ) : (
                        <div className="rounded-md border border-dashed border-ts-border px-4 py-10 text-center text-sm text-ts-muted">
                            Προεπισκόπηση «{current.title}» — πατήστε Αναζήτηση ή χρησιμοποιήστε
                            Εκτύπωση / Εξαγωγή με τα κοινά φίλτρα.
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
