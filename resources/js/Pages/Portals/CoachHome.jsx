import PortalPushOptIn from '@/Components/PortalPushOptIn';
import {
    PortalBottomTabs,
    PortalPanel,
    PortalPillTabs,
    PortalStatCard,
} from '@/Components/PortalShell';
import {
    athleteDisplayName,
    formatDate,
    formatLongDate,
    formatTimeRange,
    initials,
} from '@/lib/portalFormat';
import { applyPortalChrome } from '@/utils/portalApp';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const TABS = [
    { id: 'home', label: 'Αρχική', glyph: '▦' },
    { id: 'schedule', label: 'Πρόγραμμα', glyph: '📅' },
    { id: 'attendance', label: 'Παρουσίες', glyph: '✓' },
    { id: 'announcements', label: 'Ανακοινώσεις', glyph: '📢' },
];

function upcomingLine(t) {
    const d = t.starts_at ? new Date(t.starts_at) : null;
    if (!d || Number.isNaN(d.getTime())) {
        return t.class_name || 'Προπόνηση';
    }
    const day = d.toLocaleDateString('el-GR', { weekday: 'short' });
    const date = `${d.getDate()}/${String(d.getMonth() + 1).padStart(2, '0')}`;
    const time = formatTimeRange(t.starts_at, t.ends_at);
    return `${day} ${date}, ${time}, ${t.class_name}${t.location ? ` - ${t.location}` : ''}`;
}

function toggleId(list, id) {
    return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
}

function RecipientChecklist({ title, rows, selectedIds, onToggle, emptyText }) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return rows;
        }
        return rows.filter(
            (row) =>
                (row.label || '').toLowerCase().includes(q) ||
                (row.hint || '').toLowerCase().includes(q) ||
                (row.name || '').toLowerCase().includes(q),
        );
    }, [rows, query]);

    return (
        <div>
            <div className="mb-1 flex items-center justify-between gap-2">
                <p className="text-xs text-ts-muted">
                    {title}
                    {selectedIds.length > 0 ? ` · ${selectedIds.length}` : ''}
                </p>
                {rows.length > 6 ? (
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Αναζήτηση…"
                        className="w-36 rounded-md border border-ts-border bg-ts-surface px-2 py-1 text-xs"
                    />
                ) : null}
            </div>
            {rows.length === 0 ? (
                <p className="text-xs text-ts-muted">{emptyText}</p>
            ) : (
                <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border border-ts-border bg-ts-surface p-2">
                    {filtered.length === 0 ? (
                        <p className="text-xs text-ts-muted">Κανένα αποτέλεσμα.</p>
                    ) : (
                        filtered.map((row) => (
                            <label
                                key={row.id}
                                className="flex items-start gap-2 rounded px-1 py-0.5 text-sm hover:bg-ts-bg"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={selectedIds.includes(row.id)}
                                    onChange={() => onToggle(row.id)}
                                />
                                <span className="min-w-0">
                                    <span className="block truncate">{row.label || row.name}</span>
                                    {row.hint ? (
                                        <span className="block truncate text-xs text-ts-muted">{row.hint}</span>
                                    ) : null}
                                </span>
                            </label>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

export default function CoachHome({
    coach = null,
    classes = [],
    audience_options = {},
    trainings = [],
    announcements = [],
    classes_count = 0,
    athlete_count = 0,
    roster_by_class = {},
    attendance_today = {},
    attendance_date = null,
    today = new Date().toISOString().slice(0, 10),
}) {
    const flash = usePage().props.flash;
    const pageErrors = usePage().props.errors || {};
    const [tab, setTab] = useState('home');
    const [classId, setClassId] = useState(classes[0]?.id || '');
    const [date, setDate] = useState(attendance_date || today);
    const [notifyAbsence, setNotifyAbsence] = useState(false);
    const [presentOverrides, setPresentOverrides] = useState({});
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState('');
    const [annTitle, setAnnTitle] = useState('');
    const [annBody, setAnnBody] = useState('');
    const [annClassIds, setAnnClassIds] = useState([]);
    const [annAthleteIds, setAnnAthleteIds] = useState([]);
    const [annCoachIds, setAnnCoachIds] = useState([]);
    const [annStaffIds, setAnnStaffIds] = useState([]);
    const [annSendEmail, setAnnSendEmail] = useState(true);
    const [annSendSms, setAnnSendSms] = useState(false);
    const [annSendPush, setAnnSendPush] = useState(false);
    const [annSending, setAnnSending] = useState(false);

    const audienceAthletes = audience_options.athletes ?? [];
    const audienceCoaches = audience_options.coaches ?? [];
    const audienceStaff = audience_options.staff ?? [];

    useEffect(() => {
        applyPortalChrome('coach');
    }, []);

    useEffect(() => {
        const athleteIds = new Set((audience_options.athletes ?? []).map((a) => a.id));
        const coachIds = new Set((audience_options.coaches ?? []).map((c) => c.id));
        const staffIds = new Set((audience_options.staff ?? []).map((s) => s.id));
        setAnnClassIds((prev) => prev.filter((id) => classes.some((c) => c.id === id)));
        setAnnAthleteIds((prev) => prev.filter((id) => athleteIds.has(id)));
        setAnnCoachIds((prev) => prev.filter((id) => coachIds.has(id)));
        setAnnStaffIds((prev) => prev.filter((id) => staffIds.has(id)));
    }, [classes, audience_options]);

    useEffect(() => {
        if (attendance_date && attendance_date !== date) {
            setDate(attendance_date);
            setPresentOverrides({});
        }
    }, [attendance_date]);

    const activeClassId = classId && classes.some((c) => c.id === classId) ? classId : classes[0]?.id || '';
    const roster = roster_by_class[activeClassId] || [];
    const attendanceForClass = attendance_today[activeClassId] || {};

    const nextTraining = trainings.find((t) => t.starts_at && t.starts_at >= today) || trainings[0] || null;
    const upcoming = useMemo(
        () => trainings.filter((t) => !t.starts_at || t.starts_at.slice(0, 10) >= today).slice(0, 5),
        [trainings, today],
    );

    function isPresent(athleteId) {
        if (presentOverrides[athleteId] !== undefined) {
            return presentOverrides[athleteId];
        }
        const st = attendanceForClass[athleteId];
        return st === 'present';
    }

    function togglePresent(athleteId) {
        setPresentOverrides((prev) => ({
            ...prev,
            [athleteId]: !isPresent(athleteId),
        }));
        setNotice('');
    }

    function changeDate(nextDate) {
        setDate(nextDate);
        setPresentOverrides({});
        setNotice('');
        router.get(
            route('portal.coach'),
            { date: nextDate },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const filteredAudienceAthletes = useMemo(() => {
        if (annClassIds.length === 0) {
            return audienceAthletes;
        }
        return audienceAthletes.filter((row) =>
            (row.class_ids || []).some((id) => annClassIds.includes(id)),
        );
    }, [audienceAthletes, annClassIds]);

    const hasRecipients =
        annClassIds.length > 0 ||
        annAthleteIds.length > 0 ||
        annCoachIds.length > 0 ||
        annStaffIds.length > 0;

    const canComposeAnnouncement =
        Boolean(coach) &&
        (classes.length > 0 || audienceCoaches.length > 0 || audienceStaff.length > 0);

    function sendAnnouncement(e) {
        e.preventDefault();
        if (!annTitle.trim() || !annBody.trim() || !hasRecipients) {
            return;
        }
        setAnnSending(true);
        router.post(
            route('portal.coach.announcements.store'),
            {
                title: annTitle.trim(),
                body: annBody.trim(),
                class_ids: annClassIds,
                athlete_ids: annAthleteIds,
                coach_ids: annCoachIds,
                staff_ids: annStaffIds,
                send_email: annSendEmail,
                send_sms: annSendSms,
                send_push: annSendPush,
            },
            {
                preserveScroll: true,
                onFinish: () => setAnnSending(false),
                onSuccess: () => {
                    setAnnTitle('');
                    setAnnBody('');
                    setAnnClassIds([]);
                    setAnnAthleteIds([]);
                    setAnnCoachIds([]);
                    setAnnStaffIds([]);
                },
            },
        );
    }

    function saveAttendance() {
        if (!activeClassId || roster.length === 0) {
            return;
        }
        setSaving(true);
        setNotice('');
        const marks = roster.map((student) => ({
            athlete_id: student.id,
            status: isPresent(student.id) ? 'present' : 'absent',
        }));
        router.post(
            route('portal.coach.attendance'),
            {
                class_id: activeClassId,
                date,
                marks,
                notify_absence: notifyAbsence,
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => {
                    setPresentOverrides({});
                    setNotice('Οι παρουσίες αποθηκεύτηκαν.');
                },
                onError: () => setNotice('Αποτυχία αποθήκευσης παρουσιών.'),
            },
        );
    }

    const attendanceSection = (
        <PortalPanel title="Τα Τμήματά μου — Απουσίες / Παρουσίες" icon="✓">
            {!coach ? (
                <p className="text-sm text-amber-800">
                    Ο λογαριασμός δεν είναι συνδεδεμένος με καρτέλα προπονητή. Ζητήστε από τον διαχειριστή
                    (Ρυθμίσεις → Χρήστες) να ορίσει «Σύνδεση με προπονητή».
                </p>
            ) : classes.length === 0 ? (
                <p className="text-sm text-ts-muted">Δεν βρέθηκαν τμήματα συνδεδεμένα με τον λογαριασμό σας.</p>
            ) : (
                <>
                    <div className="mb-3 flex flex-wrap gap-2">
                        <select
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={activeClassId}
                            onChange={(e) => {
                                setClassId(e.target.value);
                                setPresentOverrides({});
                                setNotice('');
                            }}
                        >
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                            value={date}
                            onChange={(e) => changeDate(e.target.value)}
                        />
                        <label className="flex items-center gap-2 text-sm text-ts-muted">
                            <input
                                type="checkbox"
                                checked={notifyAbsence}
                                onChange={(e) => setNotifyAbsence(e.target.checked)}
                            />
                            Email / SMS σε απουσία
                        </label>
                    </div>

                    {roster.length === 0 ? (
                        <p className="text-sm text-ts-muted">Δεν υπάρχουν ενεργοί αθλητές στο τμήμα.</p>
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-ts-border">
                            <div className="grid grid-cols-[1fr_auto] gap-3 bg-ts-surface-2 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-ts-muted">
                                <span>Αθλητής</span>
                                <span>Παρουσία</span>
                            </div>
                            <ul className="divide-y divide-ts-border">
                                {roster.map((student) => {
                                    const name = athleteDisplayName(student);
                                    const present = isPresent(student.id);
                                    return (
                                        <li
                                            key={student.id}
                                            className="flex items-center justify-between gap-3 px-3 py-2"
                                        >
                                            <div className="flex min-w-0 items-center gap-2">
                                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ts-accent-soft text-xs font-semibold text-ts-accent">
                                                    {initials(name)}
                                                </span>
                                                <span className="truncate text-sm font-medium">{name}</span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => togglePresent(student.id)}
                                                className={`rounded-full px-3 py-1 text-xs font-medium ${
                                                    present
                                                        ? 'bg-ts-accent text-ts-accent-fg'
                                                        : 'border border-ts-border bg-ts-surface-2 text-ts-muted'
                                                }`}
                                            >
                                                {present ? 'Παρουσία' : 'Απουσία'}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            disabled={saving || roster.length === 0}
                            onClick={saveAttendance}
                            className="rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg disabled:opacity-60"
                        >
                            {saving ? 'Αποθήκευση…' : 'Αποθήκευση Παρουσιών'}
                        </button>
                        {(notice || flash?.success) && (
                            <span className="text-xs text-emerald-700">{notice || flash.success}</span>
                        )}
                        {flash?.error && <span className="text-xs text-rose-700">{flash.error}</span>}
                    </div>
                </>
            )}
        </PortalPanel>
    );

    const sideWidgets = (
        <div className="space-y-4">
            <PortalPanel title="Προσεχείς Προπονήσεις">
                {upcoming.length === 0 ? (
                    <p className="text-sm text-ts-muted">Δεν υπάρχουν προσεχείς προπονήσεις.</p>
                ) : (
                    <ul className="space-y-2 text-sm">
                        {upcoming.map((t) => (
                            <li key={t.id} className="text-ts-fg-soft">
                                {upcomingLine(t)}
                            </li>
                        ))}
                    </ul>
                )}
                <button
                    type="button"
                    onClick={() => setTab('schedule')}
                    className="mt-2 text-xs font-medium text-ts-accent"
                >
                    Προβολή πλήρους προγράμματος
                </button>
            </PortalPanel>

            <PortalPanel title="Τελευταίες Ανακοινώσεις">
                {announcements.length === 0 ? (
                    <p className="text-sm text-ts-muted">Δεν υπάρχουν ανακοινώσεις.</p>
                ) : (
                    <ul className="divide-y divide-ts-border">
                        {announcements.slice(0, 4).map((a) => (
                            <li key={a.id} className="py-2 first:pt-0">
                                <strong className="text-sm">{a.title}</strong>
                                <div className="text-xs text-ts-muted">
                                    {a.published_at ? formatDate(a.published_at) : ''}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <button
                    type="button"
                    onClick={() => setTab('announcements')}
                    className="mt-2 text-xs font-medium text-ts-accent"
                >
                    Προβολή όλων των ανακοινώσεων
                </button>
            </PortalPanel>
        </div>
    );

    return (
        <div className="min-h-screen bg-ts-bg pb-24 text-ts-fg">
            <Head title="Πύλη προπονητή" />

            <header className="border-b border-ts-border bg-ts-surface px-4 py-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="text-[11px] font-medium uppercase tracking-wider text-ts-accent">
                            Coach Portal
                        </div>
                        <h1 className="text-xl font-semibold">Καλωσήρθες, Coach!</h1>
                        <p className="text-sm text-ts-muted">Αρχική επισκόπηση</p>
                    </div>
                    <div className="text-right">
                        <span className="inline-block rounded-full bg-ts-accent-soft px-2 py-0.5 text-xs font-medium text-ts-accent">
                            Προπονητής
                        </span>
                        <time className="mt-1 block text-xs text-ts-muted">{formatLongDate(new Date())}</time>
                    </div>
                </div>
            </header>

            <main className="space-y-4 px-4 py-5 pb-8 md:pb-5">
                <PortalPillTabs tabs={TABS} active={tab} onChange={setTab} />
                <PortalPushOptIn kind="coach" />

                {tab === 'home' ? (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <PortalStatCard
                                label="Τα Τμήματά μου"
                                value={`${classes_count} ενεργά τμήματα`}
                                hint="Προβολή τμημάτων"
                            />
                            <PortalStatCard
                                label="Επόμενη Προπόνηση"
                                value={
                                    nextTraining
                                        ? `${formatDate(nextTraining.starts_at)}, ${formatTimeRange(
                                              nextTraining.starts_at,
                                              nextTraining.ends_at,
                                          )}`
                                        : '—'
                                }
                                hint={nextTraining?.location || '—'}
                            />
                            <PortalStatCard
                                label="Ανακοινώσεις"
                                value={`${Math.min(announcements.length, 4)} πρόσφατες`}
                                hint="Προβολή όλων"
                            />
                            <PortalStatCard
                                label="Συνολικοί Αθλητές"
                                value={`${athlete_count} σε όλα τα τμήματα`}
                                hint="Προβολή αθλητών"
                            />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                            {attendanceSection}
                            {sideWidgets}
                        </div>
                    </>
                ) : null}

                {tab === 'schedule' ? (
                    <PortalPanel title="Πρόγραμμα προπονήσεων" icon="📅">
                        {trainings.length === 0 ? (
                            <p className="text-sm text-ts-muted">Κενό πρόγραμμα.</p>
                        ) : (
                            <ul className="divide-y divide-ts-border">
                                {trainings.map((t) => (
                                    <li key={t.id} className="py-2">
                                        <strong className="text-sm">{t.class_name || t.title}</strong>
                                        <p className="text-xs text-ts-muted">
                                            {formatDate(t.starts_at)} ·{' '}
                                            {formatTimeRange(t.starts_at, t.ends_at)}
                                            {t.location ? ` · ${t.location}` : ''}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </PortalPanel>
                ) : null}

                {tab === 'attendance' ? attendanceSection : null}

                {tab === 'announcements' ? (
                    <div className="space-y-4">
                        {flash?.success ? (
                            <p className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                {flash.success}
                            </p>
                        ) : null}
                        {flash?.error ? (
                            <p className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                {flash.error}
                            </p>
                        ) : null}
                        <PortalPanel title="Νέα ανακοίνωση" icon="✈">
                            {!coach ? (
                                <p className="text-sm text-amber-800">
                                    Ο λογαριασμός δεν είναι συνδεδεμένος με καρτέλα προπονητή.
                                </p>
                            ) : !canComposeAnnouncement ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν διαθέσιμοι παραλήπτες για ανακοίνωση.
                                </p>
                            ) : (
                                <form onSubmit={sendAnnouncement} className="space-y-3">
                                    <label className="block text-sm">
                                        <span className="mb-1 block text-xs text-ts-muted">Τίτλος *</span>
                                        <input
                                            required
                                            className="w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                                            value={annTitle}
                                            onChange={(e) => setAnnTitle(e.target.value)}
                                            placeholder="Π.χ. Ακύρωση προπόνησης"
                                        />
                                    </label>
                                    <label className="block text-sm">
                                        <span className="mb-1 block text-xs text-ts-muted">Κείμενο *</span>
                                        <textarea
                                            required
                                            rows={4}
                                            className="w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                                            value={annBody}
                                            onChange={(e) => setAnnBody(e.target.value)}
                                            placeholder="Γράψτε την ανακοίνωση…"
                                        />
                                    </label>
                                    <p className="text-xs text-ts-muted">
                                        Επιλέξτε παραλήπτες: τμήμα (γονείς), αθλητές, προπονητές ή προσωπικό.
                                    </p>
                                    <RecipientChecklist
                                        title="Τμήματα"
                                        rows={classes.map((c) => ({ id: c.id, label: c.name }))}
                                        selectedIds={annClassIds}
                                        onToggle={(id) => setAnnClassIds((prev) => toggleId(prev, id))}
                                        emptyText="Δεν έχετε συνδεδεμένα τμήματα."
                                    />
                                    <RecipientChecklist
                                        title="Αθλητές"
                                        rows={filteredAudienceAthletes}
                                        selectedIds={annAthleteIds}
                                        onToggle={(id) => setAnnAthleteIds((prev) => toggleId(prev, id))}
                                        emptyText="Δεν υπάρχουν αθλητές στα τμήματά σας."
                                    />
                                    <RecipientChecklist
                                        title="Προπονητές"
                                        rows={audienceCoaches}
                                        selectedIds={annCoachIds}
                                        onToggle={(id) => setAnnCoachIds((prev) => toggleId(prev, id))}
                                        emptyText="Δεν υπάρχουν ενεργοί προπονητές."
                                    />
                                    <RecipientChecklist
                                        title="Προσωπικό"
                                        rows={audienceStaff}
                                        selectedIds={annStaffIds}
                                        onToggle={(id) => setAnnStaffIds((prev) => toggleId(prev, id))}
                                        emptyText="Δεν υπάρχει ενεργό προσωπικό."
                                    />
                                    {pageErrors.class_ids ? (
                                        <p className="text-sm text-rose-700">{pageErrors.class_ids}</p>
                                    ) : null}
                                    {pageErrors.send_email ? (
                                        <p className="text-sm text-rose-700">{pageErrors.send_email}</p>
                                    ) : null}
                                    {pageErrors.send_sms ? (
                                        <p className="text-sm text-rose-700">{pageErrors.send_sms}</p>
                                    ) : null}
                                    <div className="flex flex-wrap gap-3 text-sm">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={annSendEmail}
                                                onChange={(e) => setAnnSendEmail(e.target.checked)}
                                            />
                                            <span>Email</span>
                                        </label>
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={annSendSms}
                                                onChange={(e) => setAnnSendSms(e.target.checked)}
                                            />
                                            <span>SMS</span>
                                        </label>
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={annSendPush}
                                                onChange={(e) => setAnnSendPush(e.target.checked)}
                                            />
                                            <span>Push</span>
                                        </label>
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={annSending || !hasRecipients}
                                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm font-medium text-ts-accent-fg disabled:opacity-50"
                                    >
                                        {annSending ? 'Αποστολή…' : 'Αποστολή ανακοίνωσης'}
                                    </button>
                                </form>
                            )}
                        </PortalPanel>

                        <PortalPanel title="Τελευταίες Ανακοινώσεις" icon="📢">
                            {announcements.length === 0 ? (
                                <p className="text-sm text-ts-muted">Δεν υπάρχουν ανακοινώσεις.</p>
                            ) : (
                                <ul className="divide-y divide-ts-border">
                                    {announcements.map((a) => (
                                        <li key={a.id} className="py-2">
                                            <strong className="text-sm">{a.title}</strong>
                                            {a.image_url ? (
                                                <img
                                                    src={a.image_url}
                                                    alt=""
                                                    className="mt-2 max-h-36 w-full rounded-md object-cover"
                                                />
                                            ) : null}
                                            {a.body ? (
                                                <p className="mt-1 whitespace-pre-wrap text-sm text-ts-fg-soft">
                                                    {a.body}
                                                </p>
                                            ) : null}
                                            <div className="text-xs text-ts-muted">
                                                {a.published_at ? formatDate(a.published_at) : ''}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </PortalPanel>
                    </div>
                ) : null}
            </main>

            <PortalBottomTabs tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
