import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const TABS = [
    { id: 'overview', label: 'Επισκόπηση' },
    { id: 'schedule', label: 'Πρόγραμμα' },
    { id: 'attendance', label: 'Παρουσίες' },
    { id: 'athletes', label: 'Αθλητές' },
    { id: 'files', label: 'Αρχεία' },
    { id: 'video', label: 'Βίντεο' },
];

const WEEKDAYS = ['Κυρ', 'Δευ', 'Τρί', 'Τετ', 'Πέμ', 'Παρ', 'Σάβ'];

const GENDER_LABELS = {
    male: 'Αγόρια',
    female: 'Κορίτσια',
    mixed: 'Μικτό',
    boys: 'Αγόρια',
    girls: 'Κορίτσια',
};

const STATUS_LABEL = {
    active: 'Ενεργός',
    trial: 'Δοκιμαστικός',
    inactive: 'Ανενεργός',
};

export default function Show({
    classItem,
    athletes,
    availableAthletes,
    coaches,
    classStats = {},
    scheduleSlots = [],
    todayEvents = [],
}) {
    const flash = usePage().props.flash;
    const [tab, setTab] = useState('overview');
    const [editing, setEditing] = useState(false);
    const form = useForm({
        name: classItem.name || '',
        sport: classItem.sport || '',
        age_group: classItem.age_group || '',
        gender: classItem.gender || '',
        monthly_fee: classItem.monthly_fee || 0,
        max_students: classItem.max_students || '',
        schedule_summary: classItem.schedule_summary || '',
        coach_id: classItem.coach_id || '',
    });
    const attachForm = useForm({ athlete_id: '', is_primary: false });

    const rosterPanel = (
        <section className="mb-6 rounded-lg border border-ts-border bg-ts-surface p-4">
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                >
                    Μαζική αλλαγή
                </button>
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                >
                    Φίλτρα
                </button>
                <a
                    href={route('admin.athletes.export')}
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                >
                    Excel
                </a>
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                    onClick={() => window.print()}
                >
                    Print
                </button>
                <label className="ms-auto text-sm text-ts-muted">
                    Δείξε{' '}
                    <select className="rounded-md border border-ts-border px-2 py-1 text-sm">
                        <option>25</option>
                    </select>{' '}
                    εγγραφές
                </label>
                <label className="flex items-center gap-1 text-sm text-ts-muted">
                    Αναζήτηση:
                    <input className="rounded-md border border-ts-border px-2 py-1 text-sm" />
                </label>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    attachForm.post(route('admin.classes.athletes.attach', classItem.id), {
                        onSuccess: () => attachForm.reset('athlete_id'),
                    });
                }}
                className="mb-4 flex flex-wrap gap-2"
            >
                <select
                    required
                    className="min-w-[220px] flex-1 rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                    value={attachForm.data.athlete_id}
                    onChange={(e) => attachForm.setData('athlete_id', e.target.value)}
                >
                    <option value="">Προσθήκη αθλητών…</option>
                    {availableAthletes.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.last_name} {a.first_name}
                        </option>
                    ))}
                </select>
                <button
                    type="submit"
                    className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                >
                    Προσθήκη
                </button>
            </form>

            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-ts-surface-2 text-xs uppercase text-ts-muted">
                        <tr>
                            <th className="px-2 py-1 text-left">
                                <input type="checkbox" aria-label="Επιλογή όλων" />
                            </th>
                            <th className="px-2 py-1 text-left">Αρ. Μητρώου</th>
                            <th className="px-2 py-1 text-left">Επώνυμο</th>
                            <th className="px-2 py-1 text-left">Όνομα</th>
                            <th className="px-2 py-1 text-left">Κατάσταση</th>
                            <th className="px-2 py-1 text-left">Ηλικία</th>
                            <th className="px-2 py-1 text-left">Έτος γέννησης</th>
                            <th className="px-2 py-1 text-left">Τηλέφωνο</th>
                            <th className="px-2 py-1 text-left">Κάρτα υγείας</th>
                            <th className="px-2 py-1 text-left">Δελτίο</th>
                            <th className="px-2 py-1 text-left">Οικ. ενημ.</th>
                        </tr>
                    </thead>
                    <tbody>
                        {athletes.map((a) => (
                            <tr key={a.id} className="border-t border-ts-border">
                                <td className="px-2 py-2">
                                    <input type="checkbox" aria-label={`Επιλογή ${a.last_name}`} />
                                </td>
                                <td className="px-2 py-2 text-ts-muted">
                                    {a.registration_number || '—'}
                                </td>
                                <td className="px-2 py-2">
                                    <Link
                                        href={route('admin.athletes.show', a.id)}
                                        className="text-ts-accent hover:underline"
                                    >
                                        {a.last_name}
                                    </Link>
                                </td>
                                <td className="px-2 py-2">
                                    <Link
                                        href={route('admin.athletes.show', a.id)}
                                        className="text-ts-accent hover:underline"
                                    >
                                        {a.first_name}
                                    </Link>
                                </td>
                                <td className="px-2 py-2">
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs ${
                                            a.status === 'active'
                                                ? 'bg-emerald-50 text-emerald-800'
                                                : a.status === 'trial'
                                                  ? 'bg-amber-50 text-amber-800'
                                                  : 'bg-slate-100 text-ts-muted'
                                        }`}
                                    >
                                        {STATUS_LABEL[a.status] || a.status}
                                    </span>
                                </td>
                                <td className="px-2 py-2 text-ts-muted">{a.age ?? '—'}</td>
                                <td className="px-2 py-2 text-ts-muted">{a.birth_year || '—'}</td>
                                <td className="px-2 py-2 text-ts-muted">{a.phone || '—'}</td>
                                <td className="px-2 py-2 text-center">
                                    {a.health_card ? '✓' : '✗'}
                                </td>
                                <td className="px-2 py-2 text-center">
                                    {a.registration_number ? '✓' : '—'}
                                </td>
                                <td className="px-2 py-2 text-center">✓</td>
                            </tr>
                        ))}
                        {!athletes.length && (
                            <tr>
                                <td colSpan={11} className="px-2 py-6 text-center text-ts-muted">
                                    Δεν υπάρχουν αθλητές στο τμήμα.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );

    return (
        <AdminLayout title={classItem.name}>
            <Head title={classItem.name} />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-2xl font-semibold text-ts-fg">{classItem.name}</h2>
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                            Ενεργό
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg"
                    onClick={() => setEditing((v) => !v)}
                >
                    {editing ? 'Ακύρωση' : 'Επεξεργασία'}
                </button>
            </div>

            <div className="mb-4 flex flex-wrap gap-1 border-b border-ts-border">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`border-b-2 px-3 py-2 text-sm ${
                            tab === t.id
                                ? 'border-teal-600 text-teal-700'
                                : 'border-transparent text-ts-muted hover:text-ts-fg'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {editing && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(route('admin.classes.update', classItem.id), {
                            onSuccess: () => setEditing(false),
                        });
                    }}
                    className="mb-6 grid gap-2 rounded-lg border border-ts-border bg-ts-surface p-4 sm:grid-cols-2"
                >
                    <input
                        required
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Όνομα"
                    />
                    <input
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={form.data.sport}
                        onChange={(e) => form.setData('sport', e.target.value)}
                        placeholder="Άθλημα"
                    />
                    <input
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={form.data.age_group}
                        onChange={(e) => form.setData('age_group', e.target.value)}
                        placeholder="Ηλικιακή ομάδα"
                    />
                    <select
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={form.data.coach_id}
                        onChange={(e) => form.setData('coach_id', e.target.value)}
                    >
                        <option value="">Χωρίς προπονητή</option>
                        {coaches.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.last_name} {c.first_name}
                            </option>
                        ))}
                    </select>
                    <input
                        type="number"
                        step="0.01"
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={form.data.monthly_fee}
                        onChange={(e) => form.setData('monthly_fee', e.target.value)}
                        placeholder="Μηνιαία €"
                    />
                    <input
                        className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg sm:col-span-2"
                        value={form.data.schedule_summary}
                        onChange={(e) => form.setData('schedule_summary', e.target.value)}
                        placeholder="Περίληψη προγράμματος"
                    />
                    <button
                        type="submit"
                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg sm:w-fit"
                    >
                        Αποθήκευση
                    </button>
                </form>
            )}

            {tab === 'overview' && (
                <>
                    <div className="mb-6 grid gap-4 lg:grid-cols-3">
                        <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                            <h3 className="mb-3 text-sm font-semibold text-ts-fg">Προφίλ</h3>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Φύλο</dt>
                                    <dd className="text-ts-fg">
                                        {GENDER_LABELS[classItem.gender] ||
                                            classItem.gender ||
                                            '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Κατηγορία</dt>
                                    <dd className="text-ts-fg">{classItem.age_group || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Εύρος ηλικιών</dt>
                                    <dd className="text-ts-fg">—</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Α' Προπονητής</dt>
                                    <dd className="text-ts-fg">{classItem.coach_name || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Παρουσιολόγιο</dt>
                                    <dd className="text-ts-fg">Απαιτείται</dd>
                                </div>
                            </dl>
                        </section>
                        <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                            <h3 className="mb-3 text-sm font-semibold text-ts-fg">
                                Στατιστικά τμήματος
                            </h3>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Παρουσίες</dt>
                                    <dd className="text-ts-fg">
                                        {classStats.attendance_pct == null
                                            ? 'Χωρίς καταχωρήσεις'
                                            : `${classStats.attendance_pct}%`}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Οφειλές αθλητών</dt>
                                    <dd className="text-ts-fg">
                                        {Number(classStats.owed || 0).toLocaleString('el-GR', {
                                            minimumFractionDigits: 2,
                                        })}{' '}
                                        €
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Πληρότητα τμήματος</dt>
                                    <dd className="text-ts-fg">{classStats.seats || '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-ts-muted">Θέσεις αδειών συλλόγου</dt>
                                    <dd className="text-ts-fg">
                                        {classStats.club_seats == null
                                            ? 'Χωρίς όριο'
                                            : classStats.club_seats}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                            <h3 className="mb-3 text-sm font-semibold text-ts-fg">
                                Σημερινό πρόγραμμα
                            </h3>
                            {todayEvents.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν βρέθηκαν εκδηλώσεις για σήμερα.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="text-xs uppercase text-ts-muted">
                                            <tr>
                                                <th className="px-2 py-1 text-left">Ώρα</th>
                                                <th className="px-2 py-1 text-left">Εγκατάσταση</th>
                                                <th className="px-2 py-1 text-left">Τίτλος</th>
                                                <th className="px-2 py-1 text-left">Τύπος</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {todayEvents.map((ev) => (
                                                <tr
                                                    key={ev.id}
                                                    className="border-t border-ts-border"
                                                >
                                                    <td className="px-2 py-2">
                                                        {ev.starts_at}
                                                        {ev.ends_at ? ` – ${ev.ends_at}` : ''}
                                                    </td>
                                                    <td className="px-2 py-2 text-ts-muted">
                                                        {ev.notes || '—'}
                                                    </td>
                                                    <td className="px-2 py-2">{ev.title}</td>
                                                    <td className="px-2 py-2 text-ts-muted">
                                                        {ev.type || 'Πρόγραμμα'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </div>
                    {rosterPanel}
                </>
            )}

            {tab === 'athletes' && rosterPanel}

            {tab === 'schedule' && (
                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold text-ts-fg">Πρόγραμμα τμήματος</h3>
                        <Link
                            href={route('admin.schedule.index')}
                            className="text-sm text-ts-accent hover:underline"
                        >
                            Άνοιγμα Προγράμματος →
                        </Link>
                    </div>
                    {classItem.schedule_summary ? (
                        <p className="mb-4 whitespace-pre-wrap text-sm text-ts-fg-soft">
                            {classItem.schedule_summary}
                        </p>
                    ) : null}
                    {scheduleSlots.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-xs uppercase text-ts-muted">
                                    <tr>
                                        <th className="px-2 py-1 text-left">Ημέρα</th>
                                        <th className="px-2 py-1 text-left">Ώρα</th>
                                        <th className="px-2 py-1 text-left">Τίτλος</th>
                                        <th className="px-2 py-1 text-left">Τοποθεσία</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {scheduleSlots.map((s) => (
                                        <tr key={s.id} className="border-t border-ts-border">
                                            <td className="px-2 py-2">
                                                {WEEKDAYS[s.weekday] || s.weekday}
                                            </td>
                                            <td className="px-2 py-2">
                                                {s.starts_at || '—'}
                                                {s.ends_at ? ` – ${s.ends_at}` : ''}
                                            </td>
                                            <td className="px-2 py-2">{s.title || 'Προπόνηση'}</td>
                                            <td className="px-2 py-2 text-ts-muted">
                                                {s.location || '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-ts-muted">
                            Δεν έχει οριστεί εβδομαδιαίο πρόγραμμα για το τμήμα.
                        </p>
                    )}
                </section>
            )}

            {tab === 'attendance' && (
                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <h3 className="mb-2 text-sm font-semibold text-ts-fg">Παρουσίες τμήματος</h3>
                    <p className="mb-3 text-sm text-ts-muted">
                        Συνολική παρουσία:{' '}
                        {classStats.attendance_pct == null
                            ? 'Χωρίς καταχωρήσεις'
                            : `${classStats.attendance_pct}%`}{' '}
                        · Roster: {classStats.roster_count ?? athletes.length}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('admin.attendance.index', { class_id: classItem.id })}
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                        >
                            Άνοιγμα παρουσιολογίου
                        </Link>
                        <Link
                            href={route('admin.trainings.index')}
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-hover"
                        >
                            Προπονήσεις
                        </Link>
                    </div>
                </section>
            )}

            {tab === 'files' && (
                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <h3 className="mb-2 text-sm font-semibold text-ts-fg">Αρχεία</h3>
                    <p className="text-sm text-ts-muted">
                        Δεν υπάρχουν αρχεία ή βίντεο για αυτό το τμήμα.
                    </p>
                </section>
            )}

            {tab === 'video' && (
                <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                    <h3 className="mb-2 text-sm font-semibold text-ts-fg">Βίντεο</h3>
                    <p className="text-sm text-ts-muted">
                        Δεν υπάρχουν αρχεία ή βίντεο για αυτό το τμήμα.
                    </p>
                </section>
            )}
        </AdminLayout>
    );
}
