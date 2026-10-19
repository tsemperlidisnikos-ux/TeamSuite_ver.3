import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

const STATUS_LABELS = {
    active: 'ΕΝΕΡΓΟΣ',
    trial: 'ΔΟΚΙΜΗ',
    inactive: 'ΑΝΕΝΕΡΓΟΣ',
};

function euro(n) {
    return Number(n || 0).toLocaleString('el-GR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** @param {string | null | undefined} isoDate YYYY-MM-DD */
function formatDayMonthYear(isoDate) {
    if (!isoDate) {
        return '';
    }
    const [year, month, day] = String(isoDate).slice(0, 10).split('-');
    if (!year || !month || !day) {
        return String(isoDate);
    }

    return `${day}-${month}-${year}`;
}

function Kpi({ label, value, hint, href, labelClass = 'text-xs uppercase tracking-wide text-ts-muted' }) {
    const body = (
        <div className="rounded-lg border border-ts-border bg-ts-surface p-4">
            <div className={labelClass}>{label}</div>
            <div className="mt-1 text-2xl font-semibold text-ts-fg">{value}</div>
            {hint && <div className="mt-1 text-xs text-ts-muted">{hint}</div>}
        </div>
    );
    return href ? (
        <Link href={href} className="block transition hover:border-sky-700/50">
            {body}
        </Link>
    ) : (
        body
    );
}

function DoctorDashboard({ doctor }) {
    const athletes = doctor?.athletes || [];
    const total = Number(doctor?.totalAthletes || 0);
    const active = Number(doctor?.activeAthletes || 0);
    const withAmka = Number(doctor?.withAmka || 0);
    const withHealthCard = Number(doctor?.withHealthCard || 0);

    return (
        <AdminLayout title="Επισκόπηση ιατρού">
            <Head title="Επισκόπηση ιατρού" />

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-2xl font-semibold text-ts-fg">Επισκόπηση ιατρού</h2>
                    <p className="text-sm text-ts-muted">
                        Αθλητές και κάρτα υγείας — χωρίς οικονομικά ή διαχείριση συλλόγου.
                    </p>
                </div>
                <Link
                    href={route('admin.athletes.index')}
                    className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover"
                >
                    Όλοι οι αθλητές
                </Link>
            </div>

            <div className="mb-5 grid gap-3 sm:grid-cols-3">
                <Kpi
                    label="Ενεργοί αθλητές"
                    value={active}
                    hint="Διαθέσιμοι για κάρτα υγείας"
                    labelClass="text-xs text-ts-muted"
                />
                <Kpi
                    label="Με ΑΜΚΑ"
                    value={withAmka}
                    hint={`${Math.max(0, active - withAmka)} χωρίς ΑΜΚΑ`}
                    labelClass="text-xs text-ts-muted"
                />
                <Kpi
                    label="Κάρτα υγείας"
                    value={withHealthCard}
                    hint="Σημειωμένη ως έγκυρη"
                    labelClass="text-xs text-ts-muted"
                />
            </div>

            <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                <div className="mb-3 flex items-center justify-between gap-2">
                    <h3 className="text-base font-semibold text-ts-fg">Αθλητές</h3>
                    <Link href={route('admin.athletes.index')} className="text-sm text-ts-accent hover:underline">
                        Πλήρης λίστα →
                    </Link>
                </div>
                {athletes.length === 0 ? (
                    <p className="text-sm text-ts-muted">Δεν υπάρχουν ενεργοί αθλητές.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                                <tr>
                                    <th className="pb-2 pe-3">Επώνυμο</th>
                                    <th className="pb-2 pe-3">Όνομα</th>
                                    <th className="pb-2 pe-3">ΑΜΚΑ</th>
                                    <th className="pb-2 pe-3">Γονέας</th>
                                    <th className="pb-2 pe-3">Κατάσταση</th>
                                    <th className="pb-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {athletes.map((row) => (
                                    <tr key={row.id} className="border-t border-ts-border">
                                        <td className="py-2 pe-3 font-medium text-ts-fg">{row.last_name}</td>
                                        <td className="py-2 pe-3 text-ts-fg">{row.first_name}</td>
                                        <td className="py-2 pe-3 font-mono text-ts-fg-soft">
                                            {row.amka || '—'}
                                        </td>
                                        <td className="py-2 pe-3 text-ts-fg-soft">
                                            {row.guardian_name || '—'}
                                        </td>
                                        <td className="py-2 pe-3 text-ts-fg-soft">
                                            {STATUS_LABELS[row.status] || row.status}
                                        </td>
                                        <td className="py-2 text-right">
                                            <Link
                                                href={`${route('admin.athletes.show', row.id)}?tab=health`}
                                                className="text-sm text-ts-accent hover:underline"
                                            >
                                                Κάρτα υγείας
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {total > 12 ? (
                    <p className="mt-3 text-sm text-ts-muted">
                        Εμφανίζονται οι πρώτοι 12. Δες όλους από{' '}
                        <Link href={route('admin.athletes.index')} className="text-ts-accent hover:underline">
                            Αθλητές
                        </Link>
                        .
                    </p>
                ) : null}
            </section>
        </AdminLayout>
    );
}

export default function Dashboard({
    view = 'admin',
    seasonName,
    kpis,
    bySport,
    money,
    financeBySport,
    till,
    uncollectedRentals,
    doctor,
}) {
    if (view === 'doctor') {
        return <DoctorDashboard doctor={doctor} />;
    }

    const sportAthletes = (bySport || []).reduce((s, r) => s + r.athletes, 0);
    const sportClasses = (bySport || []).reduce((s, r) => s + r.classes, 0);
    const sportCoaches = (bySport || []).reduce((s, r) => s + r.coaches, 0);
    const financeCollected = (financeBySport || []).reduce((s, r) => s + Number(r.collected || 0), 0);
    const financeOwed = (financeBySport || []).reduce((s, r) => s + Number(r.owed || 0), 0);
    const uncollectedCount = Number(uncollectedRentals?.count || 0);
    const uncollectedTotal = Number(uncollectedRentals?.total || 0);

    return (
        <AdminLayout title="Επισκόπηση">
            <Head title="Επισκόπηση" />

            <div className="mb-5">
                <h1 className="text-2xl font-semibold text-slate-900">Επισκόπηση</h1>
                <p className="text-sm text-slate-500">
                    {seasonName ? `Τρέχουσα σεζόν ${seasonName}` : 'Σύνοψη συλλόγου'}
                </p>
            </div>

            {uncollectedCount > 0 && (
                <Link
                    href={route('admin.rentals.index')}
                    className="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 transition hover:border-amber-400 hover:bg-amber-100"
                >
                    <span className="font-medium">
                        {uncollectedCount === 1
                            ? '1 ενοικίαση χωρίς είσπραξη'
                            : `${uncollectedCount} ενοικιάσεις χωρίς είσπραξη`}
                        {' — '}
                        {euro(uncollectedTotal)} €
                    </span>
                    <span className="font-semibold text-teal-700 underline-offset-2 hover:underline">
                        άνοιγμα καρτέλας Κρατήσεις
                    </span>
                </Link>
            )}

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Kpi
                    label="Ενεργοί αθλητές"
                    value={kpis.activeAthletes}
                    hint="Στον σύλλογο"
                    href={route('admin.athletes.index')}
                    labelClass="text-xs text-ts-muted"
                />
                <Kpi
                    label="Άδειες πακέτου"
                    value={
                        kpis.athleteCap > 0
                            ? `${kpis.activeAthletes} / ${kpis.athleteCap}`
                            : `${kpis.activeAthletes} / —`
                    }
                    hint={
                        kpis.athleteCap > 0 ? 'Ενεργοί / όριο πακέτου' : 'Δεν έχει οριστεί όριο'
                    }
                    labelClass="text-xs text-ts-muted"
                />
                <Kpi
                    label="Κράτηση γηπέδου"
                    value={kpis.rentalsToday > 0 ? 'Ναι' : 'Όχι'}
                    hint={
                        kpis.rentalsToday > 0
                            ? kpis.rentalsToday === 1
                                ? '1 κράτηση σήμερα'
                                : `${kpis.rentalsToday} κρατήσεις σήμερα`
                            : 'Καμία κράτηση σήμερα'
                    }
                    href={route('admin.rentals.index')}
                    labelClass="text-xs text-ts-muted"
                />
                <Kpi
                    label="Αιτήσεις εγγραφής"
                    value={kpis.pendingApplications}
                    hint="Σε αναμονή"
                    href={route('admin.athletes.index')}
                    labelClass="text-xs text-ts-muted"
                />
            </div>

            <section className="mb-5 rounded-lg border border-ts-border bg-ts-surface p-4">
                <div className="mb-3">
                    <h3 className="text-base font-semibold text-ts-fg">Ανά άθλημα</h3>
                    <p className="text-xs text-ts-muted">
                        Σύνοψη ενεργών αθλητών και στελέχωσης
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                            <tr>
                                <th className="pb-2 pe-3">Άθλημα</th>
                                <th className="pb-2 pe-3">Ενεργοί αθλητές</th>
                                <th className="pb-2 pe-3">Τμήματα</th>
                                <th className="pb-2">Προπονητές</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(bySport || []).map((row) => (
                                <tr key={row.sport} className="border-t border-ts-border">
                                    <td className="py-2 pe-3 text-ts-fg">{row.sport}</td>
                                    <td className="py-2 pe-3 text-ts-fg-soft">{row.athletes}</td>
                                    <td className="py-2 pe-3 text-ts-fg-soft">{row.classes}</td>
                                    <td className="py-2 text-ts-fg-soft">{row.coaches}</td>
                                </tr>
                            ))}
                            {!bySport?.length && (
                                <tr>
                                    <td colSpan={4} className="py-6 text-center text-ts-muted">
                                        Δεν υπάρχουν δεδομένα αθλημάτων.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        {bySport?.length > 0 && (
                            <tfoot>
                                <tr className="border-t border-ts-border bg-teal-50 text-ts-fg">
                                    <td className="py-2 pe-3 font-semibold">Σύνολο</td>
                                    <td className="py-2 pe-3 font-semibold">{sportAthletes}</td>
                                    <td className="py-2 pe-3 font-semibold">{sportClasses}</td>
                                    <td className="py-2 font-semibold">{sportCoaches}</td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </section>

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Kpi
                    label="ΤΑΜΕΙΟ"
                    value={`${euro(money?.cash)} €`}
                    labelClass="text-xs font-semibold uppercase tracking-wide text-ts-muted"
                />
                <Kpi
                    label="ΟΦΕΙΛΕΣ"
                    value={`${euro(money?.debts)} €`}
                    href={route('admin.fees.index')}
                    labelClass="text-xs font-semibold uppercase tracking-wide text-ts-muted"
                />
                <Kpi
                    label="ΕΣΟΔΑ ΗΜΕΡΑΣ"
                    value={<span className="text-teal-700">{`${euro(money?.revenueToday)} €`}</span>}
                    href={route('admin.finance.index')}
                    labelClass="text-xs font-semibold uppercase tracking-wide text-ts-muted"
                />
                <Kpi
                    label="ΕΙΣΠΡΑΞΕΙΣ ΜΗΝΑ"
                    value={
                        <span className="text-teal-700">{`${euro(money?.collectionsMonth)} €`}</span>
                    }
                    labelClass="text-xs font-semibold uppercase tracking-wide text-ts-muted"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <div className="mb-3">
                        <h3 className="text-base font-semibold text-ts-fg">Οικονομικά ανά άθλημα</h3>
                        <p className="text-xs text-ts-muted">Τρέχων μήνας</p>
                    </div>
                    <table className="min-w-full text-sm">
                        <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                            <tr>
                                <th className="pb-2 pe-3">Άθλημα</th>
                                <th className="pb-2 pe-3">Εισπράξεις μήνα</th>
                                <th className="pb-2">Ανοιχτές οφειλές</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(financeBySport || []).map((row) => (
                                <tr key={row.sport} className="border-t border-ts-border">
                                    <td className="py-2 pe-3 text-ts-fg">{row.sport}</td>
                                    <td className="py-2 pe-3 text-ts-fg-soft">{euro(row.collected)} €</td>
                                    <td className="py-2 text-ts-fg-soft">{euro(row.owed)} €</td>
                                </tr>
                            ))}
                            {!financeBySport?.length && (
                                <tr>
                                    <td colSpan={3} className="py-6 text-center text-ts-muted">
                                        Δεν υπάρχουν οικονομικά ανά άθλημα.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        {financeBySport?.length > 0 && (
                            <tfoot>
                                <tr className="border-t border-ts-border text-ts-fg">
                                    <td className="py-2 pe-3 font-medium">Σύνολο</td>
                                    <td className="py-2 pe-3 font-medium">{euro(financeCollected)} €</td>
                                    <td className="py-2 font-medium">{euro(financeOwed)} €</td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </section>

                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <div>
                            <h3 className="text-base font-semibold text-ts-fg">Ταμείο ημέρας</h3>
                            <p className="text-xs text-ts-muted">{formatDayMonthYear(till?.date)}</p>
                        </div>
                        <Link
                            href={route('admin.finance.index')}
                            className="text-sm text-ts-accent hover:text-sky-300"
                        >
                            Οικονομικά →
                        </Link>
                    </div>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between border-b border-ts-border pb-2">
                            <dt className="text-ts-muted">Εισπράξεις ημέρας</dt>
                            <dd className="text-ts-fg">{euro(till?.collections)} €</dd>
                        </div>
                        <div className="flex justify-between border-b border-ts-border pb-2">
                            <dt className="text-ts-muted">Έσοδα ημέρας</dt>
                            <dd className="text-ts-fg">{euro(till?.revenues)} €</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-ts-muted">Ενοικιάσεις ημέρας</dt>
                            <dd className="text-ts-fg">{euro(till?.rentals)} €</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </AdminLayout>
    );
}
