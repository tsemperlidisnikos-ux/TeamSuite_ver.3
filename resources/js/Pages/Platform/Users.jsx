import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const btn = 'rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';

export default function Users({ role, roleLabels, rows, clubs, drill }) {
    const [categoryId, setCategoryId] = useState(() => {
        const found = (drill || []).find((c) => c.items.some((i) => i.id === role));
        return found?.id || 'manage';
    });
    const [query, setQuery] = useState('');

    const categories = drill || [];
    const activeCategory = categories.find((c) => c.id === categoryId) || categories[0];
    const items = activeCategory?.items || [];

    const setRole = (nextRole) => {
        router.get(route('platform.users.index'), { role: nextRole }, { preserveState: true, replace: true });
    };

    const form = useForm({
        name: '',
        email: '',
        password: '',
        role,
        club_id: clubs?.[0]?.id || '',
    });

    const title = roleLabels?.[role] || role;
    const needsClub = role !== 'platform_admin';

    const filteredRows = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return rows || [];
        }
        return (rows || []).filter(
            (row) =>
                (row.name || '').toLowerCase().includes(q) ||
                (row.email || '').toLowerCase().includes(q) ||
                (row.club_name || '').toLowerCase().includes(q) ||
                (row.role_label || '').toLowerCase().includes(q),
        );
    }, [rows, query]);

    const enterAs = (row) => {
        router.post(
            route('platform.users.enter'),
            {
                user_id: row.user_id || null,
                athlete_id: row.athlete_id || null,
                club_id: row.club_id || null,
            },
            {
                onError: (errors) => {
                    const first = Object.values(errors || {})[0];
                    window.alert(first || 'Αποτυχία εισόδου ως χρήστης.');
                },
            },
        );
    };

    return (
        <PlatformLayout title="Χρήστες πλατφόρμας" activeNav="users">
            <Head title="Χρήστες πλατφόρμας" />

            <p className="mb-4 text-sm text-ts-muted">
                Όλοι οι εγγεγραμμένοι χρήστες, ομαδοποιημένοι ανά ρόλο. Είσοδος ως χρήστης και
                διαγραφή.
            </p>

            <div className="grid gap-4 lg:grid-cols-[240px_1fr]">
                <aside className="space-y-4 rounded-lg border border-ts-border bg-ts-surface-2 p-3">
                    <div>
                        <div className="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-ts-muted">
                            Κατηγορίες
                        </div>
                        <div className="space-y-1">
                            {categories.map((cat) => (
                                <button
                                    key={cat.id}
                                    type="button"
                                    onClick={() => {
                                        setCategoryId(cat.id);
                                        setRole(cat.items[0].id);
                                    }}
                                    className={`block w-full rounded-md px-2.5 py-1.5 text-left text-sm ${
                                        activeCategory?.id === cat.id
                                            ? 'bg-sky-900/40 text-sky-200'
                                            : 'text-ts-muted hover:bg-ts-hover hover:text-ts-fg'
                                    }`}
                                >
                                    {cat.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div>
                        <div className="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-ts-muted">
                            {activeCategory?.label}
                        </div>
                        <div className="space-y-1">
                            {items.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => setRole(item.id)}
                                    className={`flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm ${
                                        role === item.id
                                            ? 'bg-ts-surface/10 text-ts-fg'
                                            : 'text-ts-muted hover:bg-ts-hover hover:text-ts-fg'
                                    }`}
                                >
                                    <span>{item.label}</span>
                                    <span className="text-[11px] text-ts-muted">{item.count ?? 0}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </aside>

                <section className="space-y-4">
                    <div className="rounded-lg border border-ts-border bg-ts-surface p-4">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-ts-fg">{title}</h2>
                                <p className="text-sm text-ts-muted">
                                    {(rows || []).length} εγγραφές
                                </p>
                            </div>
                            <input
                                className={`${field} max-w-xs`}
                                type="search"
                                placeholder="Αναζήτηση..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>

                        {role !== 'athlete' && (
                            <form
                                className="mb-5 grid gap-2 rounded-md border border-ts-border bg-ts-surface-2 p-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.transform((data) => ({ ...data, role }));
                                    form.post(route('platform.users.store'), {
                                        preserveScroll: true,
                                        onSuccess: () => form.reset('name', 'email', 'password'),
                                    });
                                }}
                            >
                                <input
                                    className={field}
                                    placeholder="Ονοματεπώνυμο"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    required
                                />
                                <input
                                    className={field}
                                    type="email"
                                    placeholder="Email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    required
                                />
                                <input
                                    className={field}
                                    type="password"
                                    placeholder="Κωδικός"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    required
                                />
                                {needsClub && (
                                    <select
                                        className={field}
                                        value={form.data.club_id}
                                        onChange={(e) => form.setData('club_id', e.target.value)}
                                        required
                                    >
                                        {(clubs || []).map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <button type="submit" className={btn} disabled={form.processing}>
                                    Προσθήκη χρήστη
                                </button>
                            </form>
                        )}

                        <ul className="divide-y divide-ts-border">
                            {filteredRows.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-wrap items-start justify-between gap-3 py-3"
                                >
                                    <div className="min-w-0 space-y-0.5">
                                        <div className="font-medium text-ts-fg">{row.name}</div>
                                        <div className="text-sm text-ts-muted">{row.email || '—'}</div>
                                        {row.role_label && (
                                            <div className="text-xs text-ts-muted">{row.role_label}</div>
                                        )}
                                        <div className="text-xs text-ts-muted">
                                            {row.is_active
                                                ? 'Ενεργός λογαριασμός'
                                                : 'Ανενεργός λογαριασμός'}
                                            {row.source === 'athlete' && (
                                                <span className="ms-2 text-ts-muted">αθλητής</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {row.can_enter && (
                                            <button
                                                type="button"
                                                className="rounded-md bg-emerald-700 px-2.5 py-1.5 text-sm text-white hover:bg-emerald-600"
                                                onClick={() => enterAs(row)}
                                            >
                                                Είσοδος →
                                            </button>
                                        )}
                                        {row.can_delete && (
                                            <button
                                                type="button"
                                                className="rounded-md px-2.5 py-1.5 text-sm text-rose-400 hover:bg-rose-50"
                                                onClick={() => {
                                                    const deleteId = row.user_id || row.id;
                                                    if (confirm(`Διαγραφή χρήστη ${row.name};`)) {
                                                        router.delete(
                                                            route('platform.users.destroy', deleteId),
                                                        );
                                                    }
                                                }}
                                            >
                                                Διαγραφή
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                            {!filteredRows.length && (
                                <li className="py-6 text-center text-ts-muted">
                                    Δεν υπάρχουν χρήστες σε αυτή την κατηγορία.
                                </li>
                            )}
                        </ul>
                    </div>

                    <p className="text-xs text-ts-muted">
                        Για preview συλλόγου, μεταβείτε στη{' '}
                        <Link href={route('platform.dashboard')} className="text-ts-accent">
                            Διαχείριση
                        </Link>{' '}
                        → Σύλλογος → Preview συλλόγου.
                    </p>
                </section>
            </div>
        </PlatformLayout>
    );
}
