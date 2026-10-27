import {
    BalanceBadge,
    PortalBottomTabs,
    PortalHeader,
    PortalPanel,
    PortalPillTabs,
    PortalStatCard,
    PortalTable,
} from '@/Components/PortalShell';
import {
    attendanceStatusLabel,
    downloadIcs,
    formatCurrency,
    formatDate,
    formatTimeRange,
    gdprLabel,
    healthDocStatus,
} from '@/lib/portalFormat';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const TABS = [
    { id: 'overview', label: 'Αρχική', glyph: '▦' },
    { id: 'schedule', label: 'Πρόγραμμα', glyph: '📅' },
    { id: 'payments', label: 'Πληρωμές', glyph: '💳' },
    { id: 'documents', label: 'Έγγραφα', glyph: '📄' },
];

function resolveInitialTab(tabProp) {
    if (TABS.some((t) => t.id === tabProp)) {
        return tabProp;
    }
    if (typeof window !== 'undefined') {
        const raw = new URLSearchParams(window.location.search).get('tab');
        if (TABS.some((t) => t.id === raw)) {
            return raw;
        }
    }
    return 'overview';
}

export default function ParentHome({
    athletes = [],
    announcements = [],
    summary = {},
    balances = [],
    readyProviders = [],
    checkoutUrl = '/app/parent/checkout',
    trainings = [],
    matches = [],
    recent_attendance = [],
    payment_history = [],
    today = new Date().toISOString().slice(0, 10),
    tab: tabProp = 'overview',
}) {
    const flash = usePage().props.flash;
    const [tab, setTabState] = useState(() => resolveInitialTab(tabProp));
    const [absenceAthleteId, setAbsenceAthleteId] = useState(athletes[0]?.id || '');
    const [absenceDate, setAbsenceDate] = useState(today);
    const [absenceReason, setAbsenceReason] = useState('sick');
    const [absenceNotes, setAbsenceNotes] = useState('');
    const [absenceBusy, setAbsenceBusy] = useState(false);
    const [absenceMsg, setAbsenceMsg] = useState('');
    const [payBusyId, setPayBusyId] = useState('');
    const [payMsg, setPayMsg] = useState('');

    async function startCheckout(athleteId, providerId, amount) {
        setPayMsg('');
        setPayBusyId(`${athleteId}:${providerId}`);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch(checkoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    athlete_id: athleteId,
                    provider: providerId,
                    amount,
                }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json?.ok) {
                throw new Error(json?.error || 'Αποτυχία έναρξης πληρωμής');
            }
            if (json.method === 'POST' && json.fields && json.checkoutUrl) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = json.checkoutUrl;
                form.style.display = 'none';
                Object.entries(json.fields).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = String(value ?? '');
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
                return;
            }
            if (json.checkoutUrl) {
                window.location.href = json.checkoutUrl;
                return;
            }
            throw new Error('Λείπει το URL πληρωμής');
        } catch (err) {
            setPayMsg(err?.message || 'Σφάλμα πληρωμής');
        } finally {
            setPayBusyId('');
        }
    }

    const athleteNameById = useMemo(() => {
        const map = new Map();
        for (const a of athletes) {
            map.set(a.id, `${a.last_name} ${a.first_name}`.trim());
        }
        for (const row of balances) {
            if (row.athlete_id) {
                map.set(row.athlete_id, row.name);
            }
        }
        return map;
    }, [athletes, balances]);

    const nextTraining = summary.next_training || trainings[0] || null;
    const nextMatch = summary.next_match || matches[0] || null;
    const totalBalance = Number(summary.total_balance ?? 0);

    function setTab(id) {
        setTabState(id);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', id);
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    }

    function handleDownloadSchedule() {
        const events = [
            ...trainings.map((t) => ({
                uid: t.id,
                title: t.class_name || t.title || 'Προπόνηση',
                date: t.starts_at?.slice(0, 10),
                startTime: t.starts_at ? new Date(t.starts_at).toTimeString().slice(0, 5) : '18:00',
                endTime: t.ends_at ? new Date(t.ends_at).toTimeString().slice(0, 5) : '19:30',
                location: t.location,
            })),
            ...matches.map((m) => ({
                uid: m.id,
                title: `Αγώνας vs ${m.opponent || '—'}`,
                date: m.starts_at?.slice(0, 10),
                startTime: m.starts_at ? new Date(m.starts_at).toTimeString().slice(0, 5) : '00:00',
                endTime: '02:00',
                location: m.venue,
            })),
        ].filter((e) => e.date);
        downloadIcs(events, 'programma-teamsuite.ics');
    }

    function handleAbsenceSubmit() {
        const athleteId = absenceAthleteId || athletes[0]?.id;
        if (!athleteId) {
            setAbsenceMsg('Επιλέξτε αθλητή.');
            return;
        }
        setAbsenceBusy(true);
        setAbsenceMsg('');
        router.post(
            route('portal.parent.absence'),
            {
                athlete_id: athleteId,
                date: absenceDate,
                reason: absenceReason,
                notes: absenceNotes.trim() || null,
            },
            {
                preserveScroll: true,
                onFinish: () => setAbsenceBusy(false),
                onSuccess: () => {
                    setAbsenceNotes('');
                    setAbsenceMsg(
                        'Η αιτιολόγηση απουσίας καταχωρήθηκε και είναι ορατή στον προπονητή.',
                    );
                },
                onError: (errors) => {
                    setAbsenceMsg(errors?.athlete_id || errors?.date || 'Αποτυχία καταχώρησης');
                },
            },
        );
    }

    return (
        <div className="min-h-screen bg-ts-bg pb-24 text-ts-fg">
            <Head title="Περιοχή γονέα" />
            <PortalHeader title="Περιοχή γονέα" />

            <main className="space-y-4 px-4 py-5 pb-8 md:pb-5">
                <PortalPillTabs tabs={TABS} active={tab} onChange={setTab} />

                {athletes.length === 0 ? (
                    <PortalPanel title="Σύνδεση αθλητών">
                        <p className="text-sm text-ts-muted">
                            Δεν υπάρχουν συνδεδεμένοι αθλητές. Ζητήστε από τη γραμματεία να σας συνδέσει
                            μέσω «Γονείς → Σύνδεση γονέα».
                        </p>
                    </PortalPanel>
                ) : null}

                {tab === 'overview' && athletes.length > 0 ? (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <PortalStatCard
                                label="Συνδεδεμένοι αθλητές"
                                value={String(summary.athlete_count ?? athletes.length)}
                                hint="Ενεργοί στον σύλλογο"
                            />
                            <PortalStatCard
                                label="Συνολικές οφειλές"
                                value={formatCurrency(totalBalance)}
                                hint={totalBalance > 0 ? 'Πληρωμή online διαθέσιμη' : 'Καθαρό υπόλοιπο'}
                            />
                            <PortalStatCard
                                label="Επόμενη προπόνηση"
                                value={nextTraining ? formatDate(nextTraining.starts_at) : '—'}
                                hint={
                                    nextTraining
                                        ? formatTimeRange(nextTraining.starts_at, nextTraining.ends_at)
                                        : 'Δεν υπάρχει προγραμματισμένη'
                                }
                            />
                            <PortalStatCard
                                label="Επόμενος αγώνας"
                                value={nextMatch ? formatDate(nextMatch.starts_at) : '—'}
                                hint={
                                    nextMatch
                                        ? `vs ${nextMatch.opponent || '—'}`
                                        : 'Δεν υπάρχει προγραμματισμένος'
                                }
                            />
                        </div>

                        <PortalPanel title="Αθλητές" icon="👥">
                            <ul className="divide-y divide-ts-border">
                                {athletes.map((a) => (
                                    <li key={a.id} className="flex flex-col py-2 first:pt-0 last:pb-0">
                                        <strong className="text-sm text-ts-fg">
                                            {a.last_name} {a.first_name}
                                        </strong>
                                        <span className="text-xs text-ts-muted">{a.class_names}</span>
                                    </li>
                                ))}
                            </ul>
                        </PortalPanel>

                        <PortalPanel title="Τελευταίες ανακοινώσεις" icon="🔔">
                            {announcements.length === 0 ? (
                                <p className="text-sm text-ts-muted">Δεν υπάρχουν ανακοινώσεις.</p>
                            ) : (
                                <ul className="divide-y divide-ts-border">
                                    {announcements.slice(0, 5).map((n) => (
                                        <li key={n.id} className="py-2 first:pt-0 last:pb-0">
                                            <strong className="text-sm">{n.title}</strong>
                                            {n.image_url ? (
                                                <img
                                                    src={n.image_url}
                                                    alt=""
                                                    className="mt-1 max-h-28 w-full rounded-md object-cover"
                                                />
                                            ) : null}
                                            <p className="text-xs text-ts-muted">
                                                {n.published_at ? formatDate(n.published_at) : ''}
                                                {n.body
                                                    ? ` · ${String(n.body).slice(0, 120)}${
                                                          String(n.body).length > 120 ? '…' : ''
                                                      }`
                                                    : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </PortalPanel>
                    </>
                ) : null}

                {tab === 'schedule' && athletes.length > 0 ? (
                    <>
                        <PortalPanel
                            title="Επόμενες προπονήσεις"
                            icon="📅"
                            action={
                                trainings.length || matches.length ? (
                                    <button
                                        type="button"
                                        onClick={handleDownloadSchedule}
                                        className="rounded-md border border-ts-border bg-ts-surface-2 px-2 py-1 text-xs font-medium text-ts-fg-soft hover:bg-ts-hover"
                                    >
                                        Λήψη ημερολογίου (.ics)
                                    </button>
                                ) : null
                            }
                        >
                            {trainings.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν προγραμματισμένες προπονήσεις.
                                </p>
                            ) : (
                                <ul className="divide-y divide-ts-border">
                                    {trainings.map((t) => (
                                        <li key={t.id} className="py-2 first:pt-0">
                                            <strong className="text-sm">
                                                {formatDate(t.starts_at)} ·{' '}
                                                {formatTimeRange(t.starts_at, t.ends_at)}
                                            </strong>
                                            <p className="text-xs text-ts-muted">
                                                {t.class_name}
                                                {t.location ? ` · ${t.location}` : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="mt-3 text-xs text-ts-muted">
                                Μπορείτε να εισάγετε το αρχείο .ics στο Google Calendar ή Outlook για
                                ειδοποιήσεις.
                            </p>
                        </PortalPanel>

                        <PortalPanel title="Επόμενοι αγώνες" icon="📅">
                            {matches.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν προγραμματισμένοι αγώνες.
                                </p>
                            ) : (
                                <ul className="divide-y divide-ts-border">
                                    {matches.map((m) => (
                                        <li key={m.id} className="py-2">
                                            <strong className="text-sm">{formatDate(m.starts_at)}</strong>
                                            <p className="text-xs text-ts-muted">
                                                vs {m.opponent}
                                                {m.home_away === 'away' ? ' · Εκτός' : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </PortalPanel>

                        <PortalPanel title="Πρόσφατες παρουσίες" icon="👥">
                            <PortalTable
                                columns={[
                                    { key: 'date', label: 'Ημ/νία', render: (r) => formatDate(r.date) },
                                    {
                                        key: 'athlete',
                                        label: 'Αθλητής',
                                        render: (r) => r.athlete_name,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Κατάσταση',
                                        render: (r) => attendanceStatusLabel(r.status),
                                    },
                                ]}
                                rows={recent_attendance}
                                empty="Δεν υπάρχουν καταχωρήσεις παρουσίας."
                            />
                        </PortalPanel>

                        <PortalPanel title="Αιτιολόγηση απουσίας" icon="👥">
                            <p className="mb-3 text-sm text-ts-muted">
                                Δηλώστε ασθένεια ή άδεια. Η σημείωση εμφανίζεται στον προπονητή στο
                                παρουσιολόγιο.
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="block text-sm">
                                    <span className="text-ts-muted">Αθλητής</span>
                                    <select
                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5"
                                        value={absenceAthleteId || athletes[0]?.id || ''}
                                        onChange={(e) => setAbsenceAthleteId(e.target.value)}
                                    >
                                        {athletes.map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.last_name} {a.first_name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="block text-sm">
                                    <span className="text-ts-muted">Ημερομηνία</span>
                                    <input
                                        type="date"
                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5"
                                        value={absenceDate}
                                        onChange={(e) => setAbsenceDate(e.target.value)}
                                    />
                                </label>
                                <label className="block text-sm">
                                    <span className="text-ts-muted">Λόγος</span>
                                    <select
                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5"
                                        value={absenceReason}
                                        onChange={(e) => setAbsenceReason(e.target.value)}
                                    >
                                        <option value="sick">Ασθένεια</option>
                                        <option value="leave">Άδεια</option>
                                    </select>
                                </label>
                                <label className="block text-sm sm:col-span-2">
                                    <span className="text-ts-muted">Σημειώσεις (προαιρετικά)</span>
                                    <input
                                        className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5"
                                        value={absenceNotes}
                                        onChange={(e) => setAbsenceNotes(e.target.value)}
                                        placeholder="π.χ. πυρετός"
                                    />
                                </label>
                            </div>
                            <button
                                type="button"
                                disabled={absenceBusy}
                                onClick={handleAbsenceSubmit}
                                className="mt-3 rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg disabled:opacity-60"
                            >
                                {absenceBusy ? 'Αποθήκευση…' : 'Υποβολή'}
                            </button>
                            {(absenceMsg || flash?.success) && (
                                <p className="mt-2 text-xs text-emerald-700">
                                    {absenceMsg || flash.success}
                                </p>
                            )}
                            {flash?.error && (
                                <p className="mt-2 text-xs text-rose-700">{flash.error}</p>
                            )}
                        </PortalPanel>
                    </>
                ) : null}

                {tab === 'payments' && athletes.length > 0 ? (
                    <>
                        <PortalPanel title="Υπόλοιπα συνδρομών" icon="💳">
                            <PortalTable
                                columns={[
                                    { key: 'name', label: 'Αθλητής', render: (r) => r.name },
                                    {
                                        key: 'balance',
                                        label: 'Υπόλοιπο',
                                        render: (r) => <BalanceBadge amount={r.balance} />,
                                    },
                                    {
                                        key: 'pay',
                                        label: 'Πληρωμή',
                                        render: (r) => {
                                            const due = Number(r.balance || 0);
                                            if (due < 0.3 || readyProviders.length === 0) {
                                                return <span className="text-xs text-ts-muted">—</span>;
                                            }
                                            return (
                                                <div className="flex flex-wrap gap-1">
                                                    {readyProviders.map((p) => {
                                                        const busy = payBusyId === `${r.athlete_id}:${p.id}`;
                                                        return (
                                                            <button
                                                                key={p.id}
                                                                type="button"
                                                                disabled={!!payBusyId}
                                                                onClick={() =>
                                                                    startCheckout(r.athlete_id, p.id, due)
                                                                }
                                                                className="rounded-md bg-ts-primary px-2 py-1 text-xs font-medium text-white disabled:opacity-50"
                                                            >
                                                                {busy ? '…' : p.label}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            );
                                        },
                                    },
                                ]}
                                rows={balances.map((b) => ({ ...b, id: b.athlete_id }))}
                            />
                            {payMsg ? (
                                <p className="mt-3 text-xs text-red-600">{payMsg}</p>
                            ) : null}
                            {readyProviders.length === 0 ? (
                                <p className="mt-3 text-xs text-ts-muted">
                                    Η online πληρωμή θα εμφανιστεί όταν ο σύλλογος ενεργοποιήσει Viva /
                                    Stripe / Eurobank στις Ρυθμίσεις → Πληρωμές.
                                </p>
                            ) : (
                                <p className="mt-3 text-xs text-ts-muted">
                                    Επιλέξτε πάροχο για να πληρώσετε το ανοιχτό υπόλοιπο.
                                </p>
                            )}
                        </PortalPanel>

                        <PortalPanel title="Αποδείξεις είσπραξης" icon="🧾">
                            <p className="text-sm text-ts-muted">
                                Δεν έχουν κοπεί αποδείξεις για τους αθλητές σας.
                            </p>
                        </PortalPanel>

                        <PortalPanel title="Ιστορικό πληρωμών" icon="💳">
                            <PortalTable
                                columns={[
                                    {
                                        key: 'date',
                                        label: 'Ημ/νία',
                                        render: (r) => formatDate(r.created_at),
                                    },
                                    {
                                        key: 'athlete',
                                        label: 'Αθλητής',
                                        render: (r) => athleteNameById.get(r.athlete_id) || '—',
                                    },
                                    {
                                        key: 'amount',
                                        label: 'Ποσό',
                                        render: (r) => formatCurrency(r.amount),
                                    },
                                    {
                                        key: 'comments',
                                        label: 'Σχόλιο',
                                        render: (r) => (
                                            <span className="text-ts-muted">{r.comments || '—'}</span>
                                        ),
                                    },
                                ]}
                                rows={payment_history}
                                empty="Δεν υπάρχουν καταχωρημένες πληρωμές."
                            />
                        </PortalPanel>
                    </>
                ) : null}

                {tab === 'documents' && athletes.length > 0 ? (
                    <PortalPanel title="Ιατρικά & συναίνεση" icon="📄">
                        <p className="mb-3 text-xs text-ts-muted">
                            Ενημερωτική εικόνα από τα στοιχεία του συλλόγου. Για διορθώσεις επικοινωνήστε με
                            τη γραμματεία.
                        </p>
                        <PortalTable
                            columns={[
                                {
                                    key: 'name',
                                    label: 'Αθλητής',
                                    render: (r) => (
                                        <strong>
                                            {r.last_name} {r.first_name}
                                        </strong>
                                    ),
                                },
                                {
                                    key: 'health',
                                    label: 'Ιατρική / κάρτα υγείας',
                                    render: (r) => {
                                        const health = healthDocStatus(r, today);
                                        const badgeCls =
                                            health.tone === 'negative'
                                                ? 'bg-red-100 text-red-900'
                                                : health.tone === 'warn'
                                                  ? 'bg-amber-100 text-amber-900'
                                                  : 'bg-ts-accent-soft text-teal-900';
                                        return (
                                            <div>
                                                <span
                                                    className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${badgeCls}`}
                                                >
                                                    {health.label}
                                                </span>
                                                {r.health_card_status ? (
                                                    <div className="mt-1 text-xs text-ts-muted">
                                                        {r.health_card_status}
                                                    </div>
                                                ) : health.tone === 'positive' ? (
                                                    <div className="mt-1 text-xs text-ts-muted">Έγκυρη</div>
                                                ) : null}
                                            </div>
                                        );
                                    },
                                },
                                {
                                    key: 'gdpr',
                                    label: 'GDPR',
                                    render: (r) => gdprLabel(r.gdpr_consent),
                                },
                                {
                                    key: 'medical',
                                    label: 'Συναίνεση ιατρικών',
                                    render: (r) => {
                                        const items = r.gdpr_items || {};
                                        const parts = [];
                                        parts.push(items.medical ? 'Ναι' : 'Όχι');
                                        if (items.amkaHealthCard) {
                                            parts.push('ΑΜΚΑ OK');
                                        }
                                        return parts.join(' · ');
                                    },
                                },
                            ]}
                            rows={athletes.map((a) => ({ ...a, id: a.id }))}
                        />
                    </PortalPanel>
                ) : null}
            </main>

            <PortalBottomTabs tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
