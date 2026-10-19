import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router, useForm } from '@inertiajs/react';

const STATUS = {
    pending: 'Εκκρεμεί',
    approved: 'Εγκρίθηκε',
    rejected: 'Απορρίφθηκε',
};

export default function Waitlist({ rows }) {
    const form = useForm({
        email: '',
        club_name: '',
        contact_name: '',
        phone: '',
        notes: '',
    });

    return (
        <PlatformLayout title="Λίστα αναμονής ακαδημιών" activeNav="waitlist">
            <Head title="Λίστα αναμονής" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-ts-muted">
                    Πηγή: δημόσια φόρμα εγγραφής ακαδημίας (register). Έγκριση δημιουργεί λογαριασμό
                    συλλόγου.
                </p>
                <button
                    type="button"
                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg-soft"
                    onClick={() => router.reload()}
                >
                    Ανανέωση
                </button>
            </div>

            <form
                className="mb-6 grid gap-2 rounded-lg border border-ts-border bg-ts-surface p-4 sm:grid-cols-2 lg:grid-cols-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('platform.waitlist.store'), {
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <input
                    className="rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    placeholder="Email *"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    required
                />
                <input
                    className="rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    placeholder="Ακαδημία"
                    value={form.data.club_name}
                    onChange={(e) => form.setData('club_name', e.target.value)}
                />
                <input
                    className="rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    placeholder="Υπεύθυνος"
                    value={form.data.contact_name}
                    onChange={(e) => form.setData('contact_name', e.target.value)}
                />
                <input
                    className="rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    placeholder="Τηλέφωνο"
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                />
                <button type="submit" className="rounded bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg">
                    Προσθήκη
                </button>
            </form>

            <div className="overflow-x-auto rounded-lg border border-ts-border bg-ts-surface">
                <table className="min-w-full text-sm">
                    <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                        <tr>
                            <th className="px-3 py-3">Ημ/νία</th>
                            <th className="px-3 py-3">Ακαδημία</th>
                            <th className="px-3 py-3">Υπεύθυνος</th>
                            <th className="px-3 py-3">Κατάσταση</th>
                            <th className="px-3 py-3">Κωδικός</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(rows.data || []).map((r) => (
                            <tr key={r.id} className="border-t border-ts-border align-top">
                                <td className="px-3 py-3 text-ts-muted whitespace-nowrap">
                                    {r.created_at
                                        ? new Date(r.created_at).toLocaleString('el-GR')
                                        : '—'}
                                </td>
                                <td className="px-3 py-3">
                                    <div className="font-medium text-ts-fg">
                                        {r.club_name || '—'}
                                    </div>
                                    {r.notes && (
                                        <div className="text-xs text-ts-muted">{r.notes}</div>
                                    )}
                                </td>
                                <td className="px-3 py-3 text-ts-fg-soft">
                                    <div>{r.contact_name || '—'}</div>
                                    <div className="text-xs text-ts-muted">{r.email}</div>
                                    {r.phone && (
                                        <div className="text-xs text-ts-muted">{r.phone}</div>
                                    )}
                                </td>
                                <td className="px-3 py-3">
                                    <span
                                        className={`rounded px-2 py-0.5 text-xs ${
                                            r.status === 'approved'
                                                ? 'bg-emerald-900/40 text-emerald-800'
                                                : r.status === 'rejected'
                                                  ? 'bg-rose-900/40 text-rose-700'
                                                  : 'bg-amber-900/40 text-amber-200'
                                        }`}
                                    >
                                        {STATUS[r.status] || r.status}
                                    </span>
                                </td>
                                <td className="px-3 py-3">
                                    {r.status === 'pending' ? (
                                        <div className="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                className="rounded bg-emerald-700 px-2 py-1 text-xs text-white"
                                                onClick={() =>
                                                    router.patch(
                                                        route('platform.waitlist.update', r.id),
                                                        { status: 'approved' },
                                                    )
                                                }
                                            >
                                                Έγκριση
                                            </button>
                                            <button
                                                type="button"
                                                className="rounded border border-slate-600 px-2 py-1 text-xs text-ts-fg-soft"
                                                onClick={() =>
                                                    router.patch(
                                                        route('platform.waitlist.update', r.id),
                                                        { status: 'rejected' },
                                                    )
                                                }
                                            >
                                                Απόρριψη
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="space-y-1">
                                            <div className="text-xs text-ts-muted">
                                                {r.status === 'approved'
                                                    ? 'Λογαριασμός δημιουργήθηκε'
                                                    : 'Απορρίφθηκε'}
                                            </div>
                                            <button
                                                type="button"
                                                className="text-xs text-rose-400"
                                                onClick={() => {
                                                    if (confirm('Διαγραφή αίτησης;')) {
                                                        router.delete(
                                                            route('platform.waitlist.destroy', r.id),
                                                        );
                                                    }
                                                }}
                                            >
                                                Διαγραφή
                                            </button>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {!rows.data?.length && (
                            <tr>
                                <td colSpan={5} className="px-3 py-8 text-center text-ts-muted">
                                    Καμία αίτηση στη λίστα αναμονής.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </PlatformLayout>
    );
}
