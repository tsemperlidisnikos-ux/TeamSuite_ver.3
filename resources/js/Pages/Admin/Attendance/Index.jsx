import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const markBtn = (active, tone) =>
    `rounded px-2 py-1 text-xs font-medium ${
        active
            ? tone
            : 'border border-ts-border text-ts-muted hover:border-slate-500 hover:text-ts-fg'
    }`;

const histCell = (status) => {
    if (status === 'present') return { text: 'Π', className: 'bg-emerald-100 text-emerald-900' };
    if (status === 'absent') return { text: 'Α', className: 'bg-rose-100 text-rose-800' };
    if (status === 'excused') return { text: 'Δ', className: 'bg-amber-100 text-amber-900' };
    return { text: '—', className: 'text-ts-muted' };
};

export default function Index({
    classes,
    sports,
    athletes,
    filters,
    summary,
    historyDates = [],
    dateLabel = '',
    classSport = null,
}) {
    const flash = usePage().props.flash;
    const [notifyAbsence, setNotifyAbsence] = useState(false);

    const apply = (patch) => {
        router.get(route('admin.attendance.index'), { ...filters, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const setMark = (athleteId, status) => {
        if (!filters.class_id) return;
        router.post(
            route('admin.attendance.upsert'),
            {
                class_id: filters.class_id,
                date: filters.date,
                athlete_id: athleteId,
                status,
                notify_absence: notifyAbsence && status === 'absent',
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const markAll = (status) => {
        if (!filters.class_id) return;
        router.post(
            route('admin.attendance.mark-all'),
            { class_id: filters.class_id, date: filters.date, status },
            { preserveScroll: true },
        );
    };

    const exportCsv = () => {
        const className =
            classes.find((c) => c.id === filters.class_id)?.name || '';
        const header = 'Α/Α,Αθλητής,Κατάσταση,Ημερομηνία,Τμήμα';
        const lines = (athletes || []).map((a, i) => {
            const status =
                a.mark === 'present'
                    ? 'Παρών'
                    : a.mark === 'absent'
                      ? 'Απών'
                      : a.mark === 'excused'
                        ? 'Δικαιολογημένος'
                        : 'Μη καταχωρημένο';
            return `${i + 1},"${a.last_name} ${a.first_name}","${status}","${filters.date}","${className}"`;
        });
        const blob = new Blob([[header, ...lines].join('\n')], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `parousies-${filters.date || 'export'}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const subtitle = classSport
        ? `Καταγραφή παρουσίας για ${classSport}.`
        : 'Καταγραφή παρουσίας ανά τμήμα και ημερομηνία.';

    return (
        <AdminLayout title="Παρουσίες">
            <Head title="Παρουσίες" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-3">
                <h2 className="text-xl font-semibold text-ts-fg">Παρουσίες</h2>
                <p className="text-sm text-ts-muted">{subtitle}</p>
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-2">
                <label className="block text-xs text-ts-muted">
                    Άθλημα
                    <select
                        className="mt-1 block rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={filters.sport || ''}
                        onChange={(e) => apply({ sport: e.target.value, class_id: '' })}
                    >
                        <option value="">Όλα τα αθλήματα</option>
                        {(sports || []).map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block text-xs text-ts-muted">
                    Ομάδα / Τμήμα
                    <select
                        className="mt-1 block min-w-[180px] rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                        value={filters.class_id || ''}
                        onChange={(e) => apply({ class_id: e.target.value })}
                    >
                        <option value="">Επίλεξε τμήμα</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                                {c.sport ? ` · ${c.sport}` : ''}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block text-xs text-ts-muted">
                    Ημερομηνία
                    <div className="mt-1 flex flex-col gap-1">
                        <span className="text-sm font-medium capitalize text-ts-fg">
                            {dateLabel || filters.date}
                        </span>
                        <input
                            type="date"
                            className="rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                            value={filters.date}
                            onChange={(e) => apply({ date: e.target.value })}
                        />
                    </div>
                </label>
                <label className="flex items-center gap-2 rounded-md border border-ts-border px-3 py-1.5 text-sm">
                    <input
                        type="checkbox"
                        checked={notifyAbsence}
                        onChange={(e) => setNotifyAbsence(e.target.checked)}
                    />
                    Ειδοποίηση σε απουσία
                </label>
                <button
                    type="button"
                    className="rounded-md border border-emerald-200 px-3 py-1.5 text-sm text-emerald-800"
                    onClick={() => markAll('present')}
                >
                    Όλοι παρόντες
                </button>
                <button
                    type="button"
                    className="rounded-md border border-rose-200 px-3 py-1.5 text-sm text-rose-700"
                    onClick={() => markAll('absent')}
                >
                    Όλοι απόντες
                </button>
                <button
                    type="button"
                    className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                    onClick={() => alert('Η καταγραφή αποθηκεύεται αυτόματα σε κάθε αλλαγή.')}
                >
                    Αποθήκευση
                </button>
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                    onClick={exportCsv}
                >
                    Εξαγωγή
                </button>
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm hover:bg-ts-hover"
                    onClick={() => window.print()}
                >
                    Εκτύπωση
                </button>
            </div>

            <p className="mb-3 text-xs text-ts-muted">
                Η καταγραφή αποθηκεύεται αυτόματα όταν αλλάζει η κατάσταση παρουσίας.
            </p>

            <div className="mb-4 flex flex-wrap gap-3 text-sm">
                <span className="text-emerald-800">Παρόντες: {summary.present}</span>
                <span className="text-rose-700">Απόντες: {summary.absent}</span>
                <span className="text-amber-800">Δικαιολογημένοι: {summary.excused}</span>
                <span className="text-ts-muted">Μη καταχωρημένο: {summary.none}</span>
                <span className="text-ts-muted">Σύνολο αθλητών: {athletes?.length || 0}</span>
            </div>

            {!filters.class_id ? (
                <p className="text-ts-muted">Επίλεξε τμήμα για καταγραφή παρουσιών.</p>
            ) : (
                <>
                    <div className="mb-6 overflow-x-auto rounded-lg border border-ts-border print:block">
                        <div className="border-b border-ts-border bg-ts-surface-2 px-3 py-2 text-sm font-semibold">
                            Λίστα Αθλητών ({athletes.length})
                        </div>
                        <table className="min-w-full text-sm">
                            <thead className="bg-ts-surface-2 text-xs uppercase text-ts-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Αθλητής</th>
                                    <th className="px-3 py-2 text-left">Κατάσταση</th>
                                    <th className="px-3 py-2 text-left print:table-cell hidden">
                                        Αιτιολόγηση
                                    </th>
                                    <th className="px-3 py-2 text-right print:hidden">Σήμανση</th>
                                </tr>
                            </thead>
                            <tbody>
                                {athletes.map((a) => (
                                    <tr key={a.id} className="border-t border-ts-border">
                                        <td className="px-3 py-2 text-ts-fg">
                                            {a.last_name} {a.first_name}
                                        </td>
                                        <td className="px-3 py-2 text-ts-muted">
                                            {a.mark === 'present'
                                                ? 'Παρών'
                                                : a.mark === 'absent'
                                                  ? 'Απών'
                                                  : a.mark === 'excused'
                                                    ? 'Δικαιολογημένος'
                                                    : 'Μη καταχωρημένο'}
                                        </td>
                                        <td className="hidden px-3 py-2 print:table-cell"> </td>
                                        <td className="px-3 py-2 print:hidden">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    className={markBtn(
                                                        a.mark === 'present',
                                                        'bg-emerald-900/60 text-emerald-200',
                                                    )}
                                                    onClick={() => setMark(a.id, 'present')}
                                                >
                                                    Παρών
                                                </button>
                                                <button
                                                    type="button"
                                                    className={markBtn(
                                                        a.mark === 'absent',
                                                        'bg-rose-900/60 text-rose-700',
                                                    )}
                                                    onClick={() => setMark(a.id, 'absent')}
                                                >
                                                    Απών
                                                </button>
                                                <button
                                                    type="button"
                                                    className={markBtn(
                                                        a.mark === 'excused',
                                                        'bg-amber-900/50 text-amber-100',
                                                    )}
                                                    onClick={() => setMark(a.id, 'excused')}
                                                >
                                                    Δικαιολ.
                                                </button>
                                                <button
                                                    type="button"
                                                    className={markBtn(
                                                        a.mark === 'none',
                                                        'bg-slate-700 text-ts-fg',
                                                    )}
                                                    onClick={() => setMark(a.id, 'none')}
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!athletes.length && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-3 py-8 text-center text-ts-muted"
                                        >
                                            Δεν υπάρχουν ενεργοί αθλητές στο τμήμα.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {historyDates.length > 0 && (
                        <section className="overflow-x-auto rounded-lg border border-ts-border">
                            <div className="border-b border-ts-border bg-ts-surface-2 px-3 py-2 text-sm font-semibold">
                                Ιστορικό Παρουσιών
                            </div>
                            <table className="min-w-full text-xs">
                                <thead className="bg-ts-surface-2 text-xs uppercase text-ts-muted">
                                    <tr>
                                        <th className="px-2 py-2 text-left">Επώνυμο</th>
                                        <th className="px-2 py-2 text-left">Όνομα</th>
                                        {historyDates.map((d) => (
                                            <th key={d} className="px-1 py-2 text-center">
                                                {d.slice(8, 10)}/{d.slice(5, 7)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {athletes.map((a) => (
                                        <tr key={a.id} className="border-t border-ts-border">
                                            <td className="px-2 py-1.5">{a.last_name}</td>
                                            <td className="px-2 py-1.5">{a.first_name}</td>
                                            {historyDates.map((d) => {
                                                const cell = histCell(a.history?.[d]);
                                                return (
                                                    <td
                                                        key={d}
                                                        className={`px-1 py-1.5 text-center ${cell.className}`}
                                                    >
                                                        {cell.text}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="flex flex-wrap gap-3 border-t border-ts-border px-3 py-2 text-xs text-ts-muted">
                                <span className="inline-flex items-center gap-1">
                                    <span className="inline-block h-2 w-2 rounded-full bg-teal-500" />{' '}
                                    Παρών
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <span className="inline-block h-2 w-2 rounded-full border border-ts-border" />{' '}
                                    Απών
                                </span>
                                <span>—</span>
                            </div>
                        </section>
                    )}
                </>
            )}
        </AdminLayout>
    );
}
