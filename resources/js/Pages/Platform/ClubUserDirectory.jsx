import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const btn = 'rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';
const btnSecondary =
    'rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-surface-2 disabled:opacity-50';

export default function ClubUserDirectory({
    clubs = [],
    clubId,
    clubName,
    rows = [],
    passwordNote,
    missingPasswordCount = 0,
}) {
    const [query, setQuery] = useState('');
    const [filling, setFilling] = useState(false);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return rows;
        }
        return rows.filter(
            (row) =>
                (row.last_name || '').toLowerCase().includes(q) ||
                (row.first_name || '').toLowerCase().includes(q) ||
                (row.email || '').toLowerCase().includes(q) ||
                (row.username || '').toLowerCase().includes(q) ||
                (row.role_label || '').toLowerCase().includes(q),
        );
    }, [rows, query]);

    const selectClub = (nextId) => {
        router.get(
            route('platform.club-users.index'),
            { club_id: nextId || undefined },
            { preserveState: true, replace: true },
        );
    };

    const exportXlsx = () => {
        if (!clubId) {
            return;
        }
        window.location.href = route('platform.club-users.export', { club_id: clubId });
    };

    const fillPasswords = () => {
        if (!clubId || missingPasswordCount < 1 || filling) {
            return;
        }
        const ok = window.confirm(
            `Θα δημιουργηθούν ${missingPasswordCount} νέοι προσωρινοί κωδικοί.\n` +
                'Οι παλιοί κωδικοί αυτών των χρηστών ΔΕΝ θα ισχύουν πλέον.\nΣυνέχεια;',
        );
        if (!ok) {
            return;
        }
        setFilling(true);
        router.post(
            route('platform.club-users.fill-passwords'),
            { club_id: clubId },
            {
                preserveScroll: true,
                onFinish: () => setFilling(false),
            },
        );
    };

    return (
        <PlatformLayout title="Χρήστες συλλόγου" activeNav="club-users">
            <Head title="Χρήστες συλλόγου" />

            <p className="mb-4 text-sm text-ts-muted">
                Κατάλογος χρηστών ανά σύλλογο (μόνο Platform Admin). Εξαγωγή σε XLSX με στήλες ΕΠΩΝΥΜΟ,
                ΟΝΟΜΑ, EMAIL, USERNAME, PASSWORD.
            </p>

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <label className="min-w-[16rem] flex-1 text-sm">
                    <span className="mb-1 block text-ts-muted">Σύλλογος</span>
                    <select
                        className={field}
                        value={clubId || ''}
                        onChange={(e) => selectClub(e.target.value)}
                    >
                        {clubs.length === 0 && <option value="">Δεν υπάρχουν σύλλογοι</option>}
                        {clubs.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="min-w-[14rem] flex-1 text-sm">
                    <span className="mb-1 block text-ts-muted">Αναζήτηση</span>
                    <input
                        className={field}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Επώνυμο, email…"
                    />
                </label>
                <button
                    type="button"
                    className={btnSecondary}
                    disabled={!clubId || missingPasswordCount < 1 || filling}
                    onClick={fillPasswords}
                >
                    {filling
                        ? 'Δημιουργία…'
                        : missingPasswordCount > 0
                          ? `Δημιουργία κωδικών (${missingPasswordCount})`
                          : 'Όλοι έχουν κωδικό'}
                </button>
                <button type="button" className={btn} disabled={!clubId || rows.length === 0} onClick={exportXlsx}>
                    Εξαγωγή XLSX
                </button>
            </div>

            {passwordNote ? (
                <p className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {passwordNote}
                </p>
            ) : null}

            <div className="overflow-x-auto rounded-lg border border-ts-border bg-ts-surface">
                <table className="min-w-full text-sm">
                    <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                        <tr>
                            <th className="px-3 py-3">Επώνυμο</th>
                            <th className="px-3 py-3">Όνομα</th>
                            <th className="px-3 py-3">Email</th>
                            <th className="px-3 py-3">Username</th>
                            <th className="px-3 py-3">Password</th>
                            <th className="px-3 py-3">Ρόλος</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 ? (
                            <tr>
                                <td className="px-3 py-6 text-ts-muted" colSpan={6}>
                                    {clubId
                                        ? 'Δεν βρέθηκαν χρήστες για αυτόν τον σύλλογο.'
                                        : 'Επιλέξτε σύλλογο.'}
                                </td>
                            </tr>
                        ) : (
                            filtered.map((row, idx) => (
                                <tr key={`${row.email}-${idx}`} className="border-t border-ts-border">
                                    <td className="px-3 py-2.5 text-ts-fg">{row.last_name || '—'}</td>
                                    <td className="px-3 py-2.5 text-ts-fg">{row.first_name || '—'}</td>
                                    <td className="px-3 py-2.5 text-ts-fg">{row.email || '—'}</td>
                                    <td className="px-3 py-2.5 text-ts-muted">{row.username || '—'}</td>
                                    <td className="px-3 py-2.5 font-mono text-ts-fg">{row.password || '—'}</td>
                                    <td className="px-3 py-2.5 text-ts-muted">
                                        {row.role_label || row.role || '—'}
                                        {row.is_active === false ? (
                                            <span className="ms-2 text-[10px] text-rose-600">ανενεργός</span>
                                        ) : null}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {clubName ? (
                <p className="mt-3 text-xs text-ts-muted">
                    {filtered.length} / {rows.length} χρήστες · {clubName}
                    {missingPasswordCount > 0 ? ` · ${missingPasswordCount} χωρίς κωδικό` : ''}
                </p>
            ) : null}
        </PlatformLayout>
    );
}
