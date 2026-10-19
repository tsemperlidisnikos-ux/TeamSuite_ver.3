import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const TABS = [
    { id: 'home', label: 'Αρχική' },
    { id: 'schedule', label: 'Πρόγραμμα' },
    { id: 'card', label: 'Κάρτα' },
    { id: 'announcements', label: 'Ανακοινώσεις' },
];

function greetingName(fullName) {
    const first = (fullName || '').trim().split(/\s+/)[0];
    if (!first) return 'αθλητή';
    return first.charAt(0).toLocaleUpperCase('el') + first.slice(1).toLocaleLowerCase('el');
}

export default function AthleteHome({
    athlete,
    balance = 0,
    trainings = [],
    announcements = [],
    attendanceStats = { present: 0, absent: 0, rate: 0 },
}) {
    const [tab, setTab] = useState('home');
    const name = athlete
        ? `${athlete.first_name || ''} ${athlete.last_name || ''}`.trim()
        : '';

    return (
        <div className="min-h-screen bg-ts-bg text-ts-fg pb-20">
            <Head title="Πύλη αθλητή" />
            <header className="flex items-start justify-between border-b border-ts-border px-4 py-4">
                <div>
                    <div className="text-xs uppercase tracking-wider text-ts-accent">TeamSuite</div>
                    <h1 className="text-xl font-semibold">
                        Καλωσήρθες στο TeamSuite, {greetingName(name)}!
                    </h1>
                    {athlete?.class_name && (
                        <p className="text-sm text-ts-muted">Τμήμα · {athlete.class_name}</p>
                    )}
                </div>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-sm text-ts-muted hover:text-ts-fg"
                >
                    Έξοδος
                </Link>
            </header>

            <main className="space-y-4 px-4 py-5">
                {!athlete && (
                    <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Δεν βρέθηκε συνδεδεμένο προφίλ αθλητή. Ζητήστε από τη γραμματεία να συνδέσει τον
                        λογαριασμό σας με καρτέλα αθλητή.
                    </p>
                )}

                {tab === 'home' && athlete && (
                    <>
                        <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
                            <div className="text-[11px] uppercase text-ts-muted">Υπόλοιπο λογαριασμού</div>
                            <div className="mt-1 text-2xl font-semibold">
                                {Number(balance || 0).toLocaleString('el-GR', {
                                    style: 'currency',
                                    currency: 'EUR',
                                })}
                            </div>
                            <p className="mt-1 text-xs text-ts-muted">
                                {balance > 0 ? 'Υπάρχει οφειλή.' : 'Δεν υπάρχει οφειλή.'}
                            </p>
                        </section>
                        <section>
                            <h2 className="mb-2 text-sm font-semibold text-ts-muted">
                                Πρόγραμμα προπονήσεων
                            </h2>
                            {trainings.length === 0 && (
                                <p className="text-sm text-ts-muted">Δεν υπάρχουν προσεχείς προπονήσεις.</p>
                            )}
                            {trainings.map((t) => (
                                <div
                                    key={t.id}
                                    className="mb-2 rounded-lg border border-ts-border bg-ts-surface px-3 py-2"
                                >
                                    <div className="font-medium">{t.title || 'Προπόνηση'}</div>
                                    <div className="text-xs text-ts-accent">{t.starts_at}</div>
                                </div>
                            ))}
                        </section>
                    </>
                )}

                {tab === 'schedule' && (
                    <section>
                        <h2 className="mb-2 text-sm font-semibold text-ts-muted">Πρόγραμμα</h2>
                        {trainings.map((t) => (
                            <div
                                key={t.id}
                                className="mb-2 rounded-lg border border-ts-border bg-ts-surface px-3 py-2"
                            >
                                <div className="font-medium">{t.title || 'Προπόνηση'}</div>
                                <div className="text-xs text-ts-muted">{t.starts_at}</div>
                            </div>
                        ))}
                        {!trainings.length && (
                            <p className="text-sm text-ts-muted">Κενό πρόγραμμα.</p>
                        )}
                    </section>
                )}

                {tab === 'card' && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-ts-muted">Κάρτα υγείας & παρουσίες</h2>
                        <div className="grid grid-cols-3 gap-2">
                            <div className="rounded-lg border border-ts-border bg-ts-surface p-3 text-center">
                                <div className="text-xl font-semibold">{attendanceStats.present}</div>
                                <div className="text-[11px] text-ts-muted">Παρουσίες</div>
                            </div>
                            <div className="rounded-lg border border-ts-border bg-ts-surface p-3 text-center">
                                <div className="text-xl font-semibold">{attendanceStats.absent}</div>
                                <div className="text-[11px] text-ts-muted">Απουσίες</div>
                            </div>
                            <div className="rounded-lg border border-ts-border bg-ts-surface p-3 text-center">
                                <div className="text-xl font-semibold">{attendanceStats.rate}%</div>
                                <div className="text-[11px] text-ts-muted">Συμμετοχή</div>
                            </div>
                        </div>
                        {athlete && (
                            <p className="text-sm text-ts-muted">
                                Κάρτα υγείας:{' '}
                                {athlete.health_card
                                    ? athlete.health_card_expires
                                        ? `έως ${athlete.health_card_expires}`
                                        : 'ενεργή'
                                    : 'Όχι'}
                            </p>
                        )}
                    </section>
                )}

                {tab === 'announcements' && (
                    <section>
                        <h2 className="mb-2 text-sm font-semibold text-ts-muted">Ανακοινώσεις</h2>
                        {announcements.length === 0 && (
                            <p className="text-sm text-ts-muted">Δεν υπάρχουν ανακοινώσεις.</p>
                        )}
                        {announcements.map((n) => (
                            <div
                                key={n.id}
                                className="mb-2 rounded-lg border border-ts-border bg-ts-surface px-3 py-2"
                            >
                                <div className="font-medium">{n.title}</div>
                                {n.image_url ? (
                                    <img
                                        src={n.image_url}
                                        alt=""
                                        className="mt-2 max-h-36 w-full rounded-md object-cover"
                                    />
                                ) : null}
                                {n.body ? (
                                    <p className="mt-1 whitespace-pre-wrap text-sm text-ts-fg-soft">
                                        {n.body}
                                    </p>
                                ) : null}
                                <div className="text-xs text-ts-muted">{n.published_at}</div>
                            </div>
                        ))}
                    </section>
                )}
            </main>

            <nav className="fixed inset-x-0 bottom-0 flex border-t border-ts-border bg-ts-surface">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`flex-1 py-3 text-center text-xs ${
                            tab === t.id ? 'font-semibold text-ts-accent' : 'text-ts-fg-soft'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </nav>
        </div>
    );
}
