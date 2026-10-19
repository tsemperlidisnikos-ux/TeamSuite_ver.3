import AdminLayout from '@/Layouts/AdminLayout';
import {
    SPORTS_CATALOG,
    isSportSelected,
    resolveCatalogSportName,
} from '@/lib/sportsCatalog';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Index({ title, subtitle, items, routes }) {
    const flash = usePage().props.flash;
    const rows = items?.data ?? items ?? [];
    const [busy, setBusy] = useState(null);

    const activeNames = useMemo(
        () => rows.map((r) => r.name).filter(Boolean),
        [rows],
    );
    const activeCount = activeNames.length;

    const customSports = useMemo(
        () =>
            rows.filter((r) => {
                const resolved = resolveCatalogSportName(r.name || '');
                return !resolved;
            }),
        [rows],
    );

    const toggleCatalog = (name, next) => {
        setBusy(name);
        if (next) {
            router.post(
                route(routes.store),
                { name },
                {
                    preserveScroll: true,
                    onFinish: () => setBusy(null),
                },
            );
            return;
        }

        const existing = rows.find((r) => {
            const resolved = resolveCatalogSportName(r.name || '');
            return resolved === name || r.name === name;
        });
        if (!existing?.id) {
            setBusy(null);
            return;
        }
        router.delete(route(routes.destroy, existing.id), {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    return (
        <AdminLayout title={title || 'Άθλημα'}>
            <Head title={title || 'Άθλημα'} />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4">
                <h2 className="text-xl font-semibold text-ts-fg">{title || 'Άθλημα'}</h2>
                <p className="text-sm text-ts-muted">
                    {subtitle ||
                        'Επίλεξε τα αθλήματα που προσφέρει η ακαδημία από τον κατάλογο.'}
                </p>
            </div>

            <p className="mb-4 text-sm text-sky-700">
                Ενεργά αθλήματα: <strong>{activeCount}</strong>
            </p>

            <div className="space-y-4">
                {SPORTS_CATALOG.map((category) => (
                    <section
                        key={category.id}
                        className="rounded-lg border border-ts-border bg-ts-surface p-4"
                    >
                        <header className="mb-3 flex items-center gap-2">
                            <span className="text-base font-semibold text-ts-fg">
                                {category.label}
                            </span>
                        </header>
                        <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            {category.sports.map((sport) => {
                                const selected = isSportSelected(
                                    activeNames,
                                    sport.name,
                                    sport.aliases || [],
                                );
                                const isBusy = busy === sport.name;
                                return (
                                    <label
                                        key={sport.name}
                                        className={`flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-2 text-sm ${
                                            selected
                                                ? 'border-sky-300 bg-sky-50 text-sky-900'
                                                : 'border-ts-border text-ts-fg-soft hover:bg-ts-hover'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="rounded-full"
                                            checked={selected}
                                            disabled={isBusy}
                                            onChange={(e) =>
                                                toggleCatalog(sport.name, e.target.checked)
                                            }
                                        />
                                        <span>{sport.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </section>
                ))}
            </div>

            {customSports.length > 0 ? (
                <section className="mt-6 rounded-lg border border-ts-border bg-ts-surface p-4">
                    <h3 className="mb-3 text-sm font-semibold text-ts-fg">
                        Προσαρμοσμένα αθλήματα
                    </h3>
                    <ul className="space-y-1 text-sm text-ts-fg">
                        {customSports.map((s) => (
                            <li key={s.id} className="flex items-center justify-between gap-2">
                                <span>{s.name}</span>
                                <button
                                    type="button"
                                    className="text-rose-600 hover:underline"
                                    onClick={() => {
                                        if (confirm('Διαγραφή αθλήματος;')) {
                                            router.delete(route(routes.destroy, s.id));
                                        }
                                    }}
                                >
                                    Διαγραφή
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </AdminLayout>
    );
}
