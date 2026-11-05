import PlatformLayout from '@/Layouts/PlatformLayout';
import { downloadClubUsersCsv, downloadClubUsersXlsx } from '@/utils/downloadClubUsersXlsx';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const btn = 'rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';
const btnSecondary =
    'rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-surface-2 disabled:opacity-50';

function Cell({ children, className = '', title }) {
    return (
        <td className={`px-2 py-1.5 align-middle ${className}`} title={title || undefined}>
            <span className="block truncate">{children}</span>
        </td>
    );
}

export default function ClubUserDirectory({ clubs = [], clubId, clubName, rows = [], passwordNote }) {
    const [query, setQuery] = useState('');

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
                (row.role_label || '').toLowerCase().includes(q) ||
                (row.password || '').toLowerCase().includes(q),
        );
    }, [rows, query]);

    const selectClub = (nextId) => {
        router.get(
            route('platform.club-users.index'),
            { club_id: nextId || undefined },
            { preserveState: true, replace: true },
        );
    };

    const fileBase = () => {
        const safe = String(clubName || clubId || 'club')
            .replace(/[^\w.\-Α-Ωα-ωΆ-Ώά-ώ ]+/gu, '_')
            .trim();
        return `club-users-${safe || 'club'}`;
    };

    const exportXlsx = () => {
        if (!clubId || rows.length === 0) {
            return;
        }
        downloadClubUsersXlsx(filtered.length ? filtered : rows, `${fileBase()}.xlsx`);
    };

    const exportCsv = () => {
        if (!clubId || rows.length === 0) {
            return;
        }
        downloadClubUsersCsv(filtered.length ? filtered : rows, `${fileBase()}.csv`);
    };

    return (
        <PlatformLayout title="Χρήστες συλλόγου" activeNav="club-users" wide>
            <Head title="Χρήστες συλλόγου" />

            <p className="mb-3 text-sm text-ts-muted">
                Κατάλογος χρηστών ανά σύλλογο (μόνο Platform Admin). Στην εξαγωγή XLSX/CSV μπαίνουν
                ΕΠΩΝΥΜΟ, ΟΝΟΜΑ, EMAIL, USERNAME, PASSWORD.
            </p>

            <div className="mb-3 flex flex-wrap items-end gap-2">
                <label className="min-w-[14rem] flex-1 text-sm">
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
                <label className="min-w-[12rem] flex-1 text-sm">
                    <span className="mb-1 block text-ts-muted">Αναζήτηση</span>
                    <input
                        className={field}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Επώνυμο, email…"
                    />
                </label>
                <button type="button" className={btn} disabled={!clubId || rows.length === 0} onClick={exportXlsx}>
                    Εξαγωγή XLSX
                </button>
                <button
                    type="button"
                    className={btnSecondary}
                    disabled={!clubId || rows.length === 0}
                    onClick={exportCsv}
                >
                    Εξαγωγή CSV
                </button>
            </div>

            {passwordNote ? (
                <p className="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {passwordNote}
                </p>
            ) : null}

            <div className="rounded-lg border border-ts-border bg-ts-surface">
                <table className="w-full table-fixed text-sm">
                    <colgroup>
                        <col className="w-[16%]" />
                        <col className="w-[14%]" />
                        <col className="w-[30%]" />
                        <col className="w-[20%]" />
                        <col className="w-[20%]" />
                    </colgroup>
                    <thead className="bg-ts-surface text-left text-[11px] uppercase tracking-wide text-ts-muted">
                        <tr>
                            <th className="px-2 py-2">Επώνυμο</th>
                            <th className="px-2 py-2">Όνομα</th>
                            <th className="px-2 py-2">Email / Username</th>
                            <th className="px-2 py-2">Password</th>
                            <th className="px-2 py-2">Ρόλος</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 ? (
                            <tr>
                                <td className="px-2 py-6 text-ts-muted" colSpan={5}>
                                    {clubId
                                        ? 'Δεν βρέθηκαν χρήστες για αυτόν τον σύλλογο.'
                                        : 'Επιλέξτε σύλλογο.'}
                                </td>
                            </tr>
                        ) : (
                            filtered.map((row, idx) => {
                                const login = row.username || row.email || '';
                                const emailTitle =
                                    row.username && row.username !== row.email
                                        ? `Email: ${row.email || '—'} · Username: ${row.username}`
                                        : login;
                                return (
                                    <tr key={`${row.email}-${idx}`} className="border-t border-ts-border">
                                        <Cell className="text-ts-fg" title={row.last_name}>
                                            {row.last_name || '—'}
                                        </Cell>
                                        <Cell className="text-ts-fg" title={row.first_name}>
                                            {row.first_name || '—'}
                                        </Cell>
                                        <Cell className="text-ts-fg" title={emailTitle}>
                                            {login || '—'}
                                        </Cell>
                                        <Cell className="font-mono text-ts-fg" title={row.password}>
                                            {row.password || '—'}
                                        </Cell>
                                        <Cell
                                            className="text-ts-muted"
                                            title={row.role_label || row.role}
                                        >
                                            {row.role_label || row.role || '—'}
                                            {row.is_active === false ? (
                                                <span className="ms-1 text-[10px] text-rose-600">ανενεργός</span>
                                            ) : null}
                                        </Cell>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {clubName ? (
                <p className="mt-2 text-xs text-ts-muted">
                    {filtered.length} / {rows.length} χρήστες · {clubName}
                </p>
            ) : null}
        </PlatformLayout>
    );
}
