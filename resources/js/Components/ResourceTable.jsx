import { FieldInput } from '@/Components/ResourceForm';
import { router } from '@inertiajs/react';
import { useState } from 'react';

function formatCell(value, columnKey) {
    if (columnKey === 'photo_url') {
        if (!value) {
            return (
                <span
                    className="inline-block h-8 w-8 rounded-full bg-ts-surface-2 ring-1 ring-ts-border"
                    aria-hidden
                />
            );
        }
        return (
            <img
                src={value}
                alt=""
                className="h-8 w-8 rounded-full object-cover ring-1 ring-ts-border"
            />
        );
    }
    if (columnKey === 'active' || columnKey === 'is_active') {
        if (value === true || value === 1 || value === '1') {
            return (
                <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                    Ενεργός
                </span>
            );
        }
        if (value === false || value === 0 || value === '0') {
            return (
                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-ts-muted">
                    Ανενεργός
                </span>
            );
        }
    }
    if (value === true || value === 1 || value === '1') return 'Ναι';
    if (value === false || value === 0 || value === '0') return 'Όχι';
    if (value === null || value === undefined || value === '') return '—';
    return String(value);
}

export default function ResourceTable({
    columns,
    rows,
    destroyRoute,
    updateRoute,
    fields = [],
    emptyMessage = 'Δεν υπάρχουν εγγραφές.',
    selectable = false,
    selectedIds = [],
    onToggleSelect = null,
}) {
    const [editingId, setEditingId] = useState(null);
    const [editData, setEditData] = useState({});

    const startEdit = (row) => {
        setEditingId(row.id);
        const data = {};
        fields.forEach((f) => {
            const raw = row[f.name];
            data[f.name] = f.type === 'checkbox' ? !!raw : (raw ?? '');
        });
        setEditData(data);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        if (!editingId || !updateRoute) return;
        router.patch(route(updateRoute, editingId), editData, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                setEditData({});
            },
        });
    };

    const destroy = (id) => {
        if (!destroyRoute) return;
        if (!window.confirm('Διαγραφή εγγραφής;')) return;
        router.delete(route(destroyRoute, id), { preserveScroll: true });
    };

    if (!rows?.length) {
        return (
            <p className="rounded-lg border border-ts-border bg-ts-surface px-4 py-8 text-center text-sm text-ts-muted">
                {emptyMessage}
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-lg border border-ts-border">
            <table className="min-w-full divide-y divide-ts-border text-sm">
                <thead className="bg-ts-surface-2 text-left text-xs uppercase tracking-wider text-ts-muted">
                    <tr>
                        {selectable && (
                            <th className="w-10 px-3 py-2.5">
                                <span className="sr-only">Επιλογή γραμμής</span>
                            </th>
                        )}
                        {columns.map((col) => (
                            <th key={col.key} className="px-3 py-2.5 font-semibold">
                                {col.label}
                            </th>
                        ))}
                        {(updateRoute || destroyRoute) && (
                            <th className="px-3 py-2.5 text-right">Ενέργειες</th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-ts-border bg-ts-surface text-ts-fg">
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            className={
                                editingId === row.id
                                    ? 'bg-sky-950/30'
                                    : 'hover:bg-ts-surface/[0.02]'
                            }
                        >
                            {selectable && (
                                <td className="px-3 py-2.5">
                                    <input
                                        type="checkbox"
                                        aria-label="Επιλογή γραμμής"
                                        checked={selectedIds.includes(row.id)}
                                        onChange={() => onToggleSelect?.(row.id)}
                                    />
                                </td>
                            )}
                            {columns.map((col) => (
                                <td key={col.key} className="whitespace-nowrap px-3 py-2.5">
                                    {col.key === 'last_name' ? (
                                        <div className="flex items-center gap-2.5">
                                            {row.photo_url ? (
                                                <img
                                                    src={row.photo_url}
                                                    alt=""
                                                    className="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200"
                                                />
                                            ) : (
                                                <span
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-400 ring-1 ring-slate-200"
                                                    aria-hidden
                                                >
                                                    👤
                                                </span>
                                            )}
                                            <span className="font-semibold text-slate-900">
                                                {formatCell(row[col.key], col.key)}
                                            </span>
                                        </div>
                                    ) : col.key === 'first_name' ? (
                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {formatCell(row[col.key], col.key)}
                                            </div>
                                            {row.email ? (
                                                <div className="text-xs text-slate-500">
                                                    {row.email}
                                                </div>
                                            ) : null}
                                        </div>
                                    ) : (
                                        formatCell(row[col.key], col.key)
                                    )}
                                </td>
                            ))}
                            {(updateRoute || destroyRoute) && (
                                <td className="whitespace-nowrap px-3 py-2.5 text-right">
                                    <div className="flex justify-end gap-1">
                                        {updateRoute && fields.length > 0 && (
                                            <button
                                                type="button"
                                                onClick={() => startEdit(row)}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                                title="Επεξεργασία"
                                                aria-label="Επεξεργασία"
                                            >
                                                ✎
                                            </button>
                                        )}
                                        {destroyRoute && (
                                            <button
                                                type="button"
                                                onClick={() => destroy(row.id)}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                                                title="Διαγραφή"
                                                aria-label="Διαγραφή"
                                            >
                                                🗑
                                            </button>
                                        )}
                                    </div>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>

            {editingId && updateRoute && (
                <form
                    onSubmit={submitEdit}
                    className="border-t border-ts-border bg-ts-surface-2 p-4"
                >
                    <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-ts-muted">
                        Επεξεργασία εγγραφής
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {fields.map((field) => (
                            <label key={field.name} className="block text-sm">
                                <span className="text-ts-muted">{field.label}</span>
                                <FieldInput
                                    field={field}
                                    value={editData[field.name]}
                                    onChange={(v) =>
                                        setEditData((d) => ({
                                            ...d,
                                            [field.name]: v,
                                        }))
                                    }
                                />
                            </label>
                        ))}
                    </div>
                    <div className="mt-3 flex gap-2">
                        <button
                            type="submit"
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover"
                        >
                            Αποθήκευση αλλαγών
                        </button>
                        <button
                            type="button"
                            onClick={() => setEditingId(null)}
                            className="rounded-md px-3 py-1.5 text-sm text-ts-muted hover:text-ts-fg"
                        >
                            Ακύρωση
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
