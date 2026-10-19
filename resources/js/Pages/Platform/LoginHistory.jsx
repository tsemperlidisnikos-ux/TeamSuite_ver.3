import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head } from '@inertiajs/react';

export default function LoginHistory({ rows }) {
    return (
        <PlatformLayout title="Ιστορικό εισόδων" activeNav="logins">
            <Head title="Ιστορικό εισόδων" />
            <p className="mb-4 text-sm text-ts-muted">Έλεγχος συνδέσεων χρηστών στην πλατφόρμα.</p>
            <div className="overflow-x-auto rounded-lg border border-ts-border bg-ts-surface">
                <table className="min-w-full text-sm">
                    <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                        <tr>
                            <th className="px-3 py-3">Ημ/νία</th>
                            <th className="px-3 py-3">Χρήστης</th>
                            <th className="px-3 py-3">Email</th>
                            <th className="px-3 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(rows || []).map((r) => (
                            <tr key={r.id} className="border-t border-ts-border">
                                <td className="px-3 py-2 text-ts-muted whitespace-nowrap">
                                    {r.logged_in_at
                                        ? new Date(r.logged_in_at).toLocaleString('el-GR')
                                        : '—'}
                                </td>
                                <td className="px-3 py-2 text-ts-fg">{r.user || '—'}</td>
                                <td className="px-3 py-2 text-ts-muted">{r.email || '—'}</td>
                                <td className="px-3 py-2 text-ts-muted">{r.ip_address || '—'}</td>
                            </tr>
                        ))}
                        {!rows?.length && (
                            <tr>
                                <td colSpan={4} className="px-3 py-8 text-center text-ts-muted">
                                    Δεν υπάρχουν καταγραφές εισόδων ακόμα.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </PlatformLayout>
    );
}
