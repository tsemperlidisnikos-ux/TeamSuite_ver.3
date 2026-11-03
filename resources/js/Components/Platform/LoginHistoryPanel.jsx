import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const field =
    'w-full rounded-lg border border-ts-border bg-ts-surface px-3 py-2 text-sm text-ts-fg';

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('el-GR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export default function LoginHistoryPanel({
    rows = [],
    clubs = [],
    filters = {},
    embedded = false,
}) {
    const [clubId, setClubId] = useState(filters.club_id || '');
    const [query, setQuery] = useState(filters.q || '');

    const filtered = useMemo(() => {
        if (!embedded) {
            return rows;
        }

        const needle = query.trim().toLowerCase();

        return (rows || []).filter((row) => {
            if (clubId === 'platform' && row.club_id) {
                return false;
            }
            if (clubId && clubId !== 'platform' && row.club_id !== clubId) {
                return false;
            }
            if (!needle) {
                return true;
            }

            const hay = `${row.user || ''} ${row.email || ''}`.toLowerCase();

            return hay.includes(needle);
        });
    }, [rows, clubId, query, embedded]);

    const applyServerFilters = (nextClub = clubId, nextQuery = query) => {
        router.get(
            route('platform.logins.index'),
            {
                club_id: nextClub || undefined,
                q: nextQuery.trim() || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const refresh = () => {
        if (embedded) {
            router.reload({ only: ['loginHistory'] });
            return;
        }

        applyServerFilters();
    };

    const destroyOne = (id) => {
        if (!window.confirm('Διαγραφή αυτής της καταγραφής;')) {
            return;
        }

        router.delete(route('platform.logins.destroy', id), {
            preserveScroll: true,
        });
    };

    const destroyAll = () => {
        if (!window.confirm('Διαγραφή όλου του ιστορικού εισόδων; Η ενέργεια δεν αναιρείται.')) {
            return;
        }

        router.delete(route('platform.logins.destroy-all'), {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-4">
            {!embedded && (
                <p className="text-sm text-ts-muted">
                    Έλεγχος συνδέσεων χρηστών στην πλατφόρμα και στους συλλόγους.
                </p>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <label className="min-w-[180px] flex-1 space-y-1 text-sm">
                    <span className="text-ts-muted">Σύλλογος</span>
                    <select
                        className={field}
                        value={clubId}
                        onChange={(e) => {
                            const value = e.target.value;
                            setClubId(value);
                            if (!embedded) {
                                applyServerFilters(value, query);
                            }
                        }}
                    >
                        <option value="">Όλοι</option>
                        <option value="platform">Πλατφόρμα</option>
                        {(clubs || []).map((club) => (
                            <option key={club.id} value={club.id}>
                                {club.name}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="min-w-[220px] flex-[2] space-y-1 text-sm">
                    <span className="text-ts-muted">Αναζήτηση</span>
                    <input
                        className={field}
                        value={query}
                        placeholder="Όνομα, email..."
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !embedded) {
                                e.preventDefault();
                                applyServerFilters(clubId, e.currentTarget.value);
                            }
                        }}
                    />
                </label>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={refresh}
                        className="rounded-lg bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover"
                    >
                        Ανανέωση
                    </button>
                    <button
                        type="button"
                        onClick={destroyAll}
                        className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                    >
                        Διαγραφή ιστορικού
                    </button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-xl border border-ts-border">
                <table className="min-w-full text-sm">
                    <thead className="bg-ts-surface-2 text-left text-[11px] uppercase tracking-wide text-ts-muted">
                        <tr>
                            <th className="px-3 py-3">Ώρα</th>
                            <th className="px-3 py-3">Χρήστης</th>
                            <th className="px-3 py-3">Σύλλογος</th>
                            <th className="px-3 py-3">Ρόλος</th>
                            <th className="px-3 py-3">Τύπος</th>
                            <th className="px-3 py-3 text-right"> </th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((row) => (
                            <tr key={row.id} className="border-t border-ts-border">
                                <td className="whitespace-nowrap px-3 py-3 text-ts-muted">
                                    {formatWhen(row.logged_in_at)}
                                </td>
                                <td className="px-3 py-3">
                                    <div className="font-medium text-ts-fg">{row.user || '—'}</div>
                                    <div className="text-xs text-ts-muted">{row.email || '—'}</div>
                                </td>
                                <td className="px-3 py-3 text-ts-fg-soft">{row.club_name || '—'}</td>
                                <td className="px-3 py-3 text-ts-fg-soft">{row.role_label || '—'}</td>
                                <td className="px-3 py-3 text-ts-fg-soft">{row.type_label || 'Σύνδεση'}</td>
                                <td className="px-3 py-3 text-right">
                                    <button
                                        type="button"
                                        onClick={() => destroyOne(row.id)}
                                        className="text-sm text-rose-600 hover:underline"
                                    >
                                        Διαγραφή
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {!filtered.length && (
                            <tr>
                                <td colSpan={6} className="px-3 py-10 text-center text-ts-muted">
                                    Δεν υπάρχουν καταγραφές εισόδων ακόμα.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="space-y-1 border-t border-ts-border pt-3 text-xs leading-relaxed text-ts-muted">
                <p>
                    <span className="font-medium text-ts-fg-soft">Καταγράφει:</span> επιτυχημένες
                    συνδέσεις χρηστών (πλατφόρμα και σύλλογοι), συμπεριλαμβανομένης εισόδου ως άλλος
                    χρήστης.
                </p>
                <p>
                    <span className="font-medium text-ts-fg-soft">Πεδία:</span> ώρα, όνομα, email,
                    σύλλογος, ρόλος, τύπος σύνδεσης.
                </p>
                <p>
                    <span className="font-medium text-ts-fg-soft">Αποθήκευση:</span> πίνακας{' '}
                    <code className="rounded bg-ts-surface-2 px-1">login_activities</code> στη βάση
                    της εφαρμογής.
                </p>
            </div>
        </div>
    );
}
