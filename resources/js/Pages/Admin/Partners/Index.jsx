import ResourceForm from '@/Components/ResourceForm';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function initials(name) {
    return String(name || '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() || '')
        .join('');
}

export default function Index({
    title = 'Συμβεβλημένες Επιχειρήσεις',
    subtitle = 'Αποκλειστικές προσφορές και προνόμια για τα μέλη του συλλόγου μας.',
    createLabel = '+ Νέος συνεργάτης',
    fields,
    items,
    routes,
    emptyMessage = 'Δεν υπάρχουν συνεργάτες ακόμη.',
}) {
    const flash = usePage().props.flash;
    const [tab, setTab] = useState('all');
    const [showCreate, setShowCreate] = useState(false);
    const rows = items?.data ?? [];

    const filtered = useMemo(() => {
        if (tab === 'offers') {
            return rows.filter((r) => (r.contact || '').toLowerCase().includes('προσφορ'));
        }
        if (tab === 'recent') {
            return [...rows].slice(0, 6);
        }
        return rows;
    }, [rows, tab]);

    const from = filtered.length ? 1 : 0;
    const to = filtered.length;

    return (
        <AdminLayout title={title}>
            <Head title={title} />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold text-ts-fg">{title}</h2>
                    <p className="text-sm text-ts-muted">{subtitle}</p>
                </div>
                <button
                    type="button"
                    onClick={() => setShowCreate((v) => !v)}
                    className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                >
                    {createLabel}
                </button>
            </div>

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-1 border-b border-ts-border">
                    {[
                        ['all', 'Όλες'],
                        ['offers', 'Προσφορές'],
                        ['recent', 'Πρόσφατα'],
                    ].map(([id, label]) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            className={`px-3 py-2 text-sm ${
                                tab === id
                                    ? 'border-b-2 border-ts-accent font-medium text-ts-accent'
                                    : 'text-ts-muted hover:text-ts-fg'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <select
                    className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    defaultValue=""
                    aria-label="Κατηγορία"
                >
                    <option value="">Όλες οι κατηγορίες</option>
                </select>
            </div>

            {showCreate && (
                <ResourceForm
                    fields={fields}
                    storeRoute={routes.store}
                    submitLabel="Αποθήκευση"
                    formTitle="Νέος συνεργάτης"
                />
            )}

            {!filtered.length ? (
                <p className="rounded-lg border border-ts-border bg-ts-surface px-4 py-8 text-center text-sm text-ts-muted">
                    {emptyMessage}
                </p>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((row) => {
                        const category = (row.contact || 'ΣΥΝΕΡΓΑΤΗΣ').split(/[|/·]/)[0].trim();
                        const offer = row.contact || 'Προσφορά για μέλη';
                        return (
                            <article
                                key={row.id}
                                className="rounded-lg border border-ts-border bg-ts-surface p-4"
                            >
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <span className="flex h-9 w-9 items-center justify-center rounded bg-slate-100 text-xs font-semibold text-ts-muted">
                                            {initials(row.name)}
                                        </span>
                                        <div>
                                            <p className="text-[10px] font-semibold uppercase tracking-wide text-ts-muted">
                                                {category}
                                            </p>
                                            <h3 className="font-semibold text-ts-fg">{row.name}</h3>
                                        </div>
                                    </div>
                                    <span className="text-ts-muted" aria-hidden>
                                        ★
                                    </span>
                                </div>
                                <div className="rounded-md bg-sky-50 px-3 py-2 text-sm text-sky-900">
                                    <p>{offer}</p>
                                    <p className="text-xs text-sky-700/80">Ισχύει για όλα τα μέλη</p>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-3 text-sm">
                                    <button type="button" className="text-amber-700">
                                        Προβολή προσφοράς →
                                    </button>
                                    <button
                                        type="button"
                                        className="text-ts-muted"
                                        onClick={() => {
                                            /* inline edit via resource patch not shown here */
                                        }}
                                    >
                                        Επεξεργασία
                                    </button>
                                    <button
                                        type="button"
                                        className="text-rose-600"
                                        onClick={() => {
                                            if (confirm('Διαγραφή συνεργάτη;')) {
                                                router.delete(route(routes.destroy, row.id));
                                            }
                                        }}
                                    >
                                        Διαγραφή
                                    </button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-ts-muted">
                <span>
                    Εμφάνιση {from}-{to} από {rows.length} συνεργάτες
                </span>
            </div>
        </AdminLayout>
    );
}
