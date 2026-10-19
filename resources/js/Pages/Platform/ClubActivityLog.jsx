import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const field =
    'rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';

export default function ClubActivityLog({
    groups = [],
    clubs = [],
    users = [],
    actions = [],
    filters = {},
}) {
    const [clubId, setClubId] = useState(filters.club_id || '');
    const [userId, setUserId] = useState(filters.user_id || '');
    const [action, setAction] = useState(filters.action || '');
    const [date, setDate] = useState(filters.date || '');
    const [q, setQ] = useState(filters.q || '');

    const apply = (patch = {}) => {
        router.get(
            route('platform.activity.index'),
            {
                club_id: clubId,
                user_id: userId,
                action,
                date,
                q,
                ...patch,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <PlatformLayout title="Ημερολόγιο συλλόγου" activeNav="activity">
            <Head title="Ημερολόγιο συλλόγου" />

            <p className="mb-4 text-sm text-ts-muted">
                Αναλυτικές κινήσεις χρηστών ανά σύλλογο (ημερομηνία, ώρα, χρήστης, περιγραφή).
                Καταγράφονται από τη στιγμή ενεργοποίησης — παλιότερες ενέργειες δεν υπάρχουν.
            </p>

            <form
                className="mb-6 grid gap-2 rounded-lg border border-ts-border bg-ts-surface p-3 sm:grid-cols-2 lg:grid-cols-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply();
                }}
            >
                <label className="text-xs text-ts-muted lg:col-span-2">
                    Σύλλογος
                    <select
                        className={`mt-1 w-full ${field}`}
                        value={clubId}
                        onChange={(e) => {
                            setClubId(e.target.value);
                            setUserId('');
                            apply({ club_id: e.target.value, user_id: '' });
                        }}
                    >
                        {(clubs || []).map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-ts-muted">
                    Χρήστης
                    <select
                        className={`mt-1 w-full ${field}`}
                        value={userId}
                        onChange={(e) => setUserId(e.target.value)}
                    >
                        <option value="">Όλοι</option>
                        {(users || []).map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-ts-muted">
                    Κίνηση
                    <select
                        className={`mt-1 w-full ${field}`}
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                    >
                        <option value="">Όλες</option>
                        {(actions || []).map((a) => (
                            <option key={a} value={a}>
                                {a}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-xs text-ts-muted">
                    Ημερομηνία
                    <input
                        type="date"
                        className={`mt-1 w-full ${field}`}
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                    />
                </label>
                <label className="text-xs text-ts-muted">
                    Αναζήτηση
                    <input
                        type="search"
                        className={`mt-1 w-full ${field}`}
                        value={q}
                        placeholder="Περιγραφή…"
                        onChange={(e) => setQ(e.target.value)}
                    />
                </label>
                <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                    <button
                        type="submit"
                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover"
                    >
                        Εφαρμογή
                    </button>
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg-soft"
                        onClick={() => {
                            setUserId('');
                            setAction('');
                            setDate('');
                            setQ('');
                            apply({ user_id: '', action: '', date: '', q: '' });
                        }}
                    >
                        Καθαρισμός
                    </button>
                </div>
            </form>

            {(groups || []).length === 0 ? (
                <div className="rounded-lg border border-ts-border bg-ts-surface px-4 py-10 text-center text-sm text-ts-muted">
                    Δεν υπάρχουν καταγραφές για τα επιλεγμένα φίλτρα.
                </div>
            ) : (
                <div className="space-y-6">
                    {(groups || []).map((group) => (
                        <section key={group.date}>
                            <h2 className="mb-2 border-b border-ts-border pb-1 text-sm font-semibold capitalize text-ts-fg">
                                {group.label}
                            </h2>
                            <ul className="divide-y divide-ts-border rounded-lg border border-ts-border bg-ts-surface">
                                {(group.items || []).map((item) => (
                                    <li key={item.id} className="px-3 py-3 sm:px-4">
                                        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                            <time className="font-mono text-xs text-ts-muted tabular-nums">
                                                {item.time}
                                            </time>
                                            <span className="text-sm font-medium text-ts-fg">
                                                {item.user_name}
                                            </span>
                                            {item.impersonator_name ? (
                                                <span className="text-xs text-amber-700">
                                                    (είσοδος από {item.impersonator_name})
                                                </span>
                                            ) : null}
                                            <span className="rounded bg-ts-bg px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-ts-muted">
                                                {item.action}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm font-medium text-ts-fg">
                                            {item.summary}
                                        </p>
                                        <p className="mt-0.5 text-sm leading-relaxed text-ts-muted">
                                            {item.description}
                                        </p>
                                        {item.ip_address ? (
                                            <p className="mt-1 text-[11px] text-ts-muted">
                                                IP {item.ip_address}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </PlatformLayout>
    );
}
