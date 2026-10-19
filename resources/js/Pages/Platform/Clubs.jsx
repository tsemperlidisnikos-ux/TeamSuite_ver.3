import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router, usePage } from '@inertiajs/react';

export default function Clubs({ clubs, packages = [] }) {
    const currentId = usePage().props.auth?.user?.active_club_id;

    const assignLicense = (clubId, licensePackageId) => {
        router.patch(
            route('platform.clubs.license', clubId),
            { license_package_id: licensePackageId || null },
            { preserveScroll: true },
        );
    };

    return (
        <PlatformLayout title="Σύλλογοι" activeNav="clubs">
            <Head title="Σύλλογοι" />
            <div className="overflow-x-auto rounded-lg border border-ts-border bg-ts-surface">
                <table className="min-w-full text-sm">
                    <thead className="text-left text-[11px] uppercase tracking-wide text-ts-muted">
                        <tr>
                            <th className="px-3 py-3">Όνομα</th>
                            <th className="px-3 py-3">Slug</th>
                            <th className="px-3 py-3">Πακέτο αδειών</th>
                            <th className="px-3 py-3">Αθλητές</th>
                            <th className="px-3 py-3">Κατάσταση</th>
                            <th className="px-3 py-3">Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        {clubs.map((c) => (
                            <tr key={c.id} className="border-t border-ts-border">
                                <td className="px-3 py-3 text-ts-fg">
                                    {c.name}
                                    {c.id === currentId && (
                                        <span className="ms-2 rounded bg-emerald-900/40 px-1.5 py-0.5 text-[10px] text-emerald-800">
                                            ενεργός
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-3 text-ts-muted">{c.slug}</td>
                                <td className="px-3 py-3">
                                    <select
                                        className="max-w-[14rem] rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                                        value={c.license_package_id || ''}
                                        onChange={(e) => assignLicense(c.id, e.target.value)}
                                    >
                                        <option value="">Χωρίς πακέτο</option>
                                        {packages.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} ({p.athlete_cap})
                                            </option>
                                        ))}
                                    </select>
                                </td>
                                <td className="px-3 py-3 text-ts-fg-soft">
                                    {c.athletes_count}
                                    {c.athlete_cap ? ` / ${c.athlete_cap}` : ''}
                                </td>
                                <td className="px-3 py-3">
                                    {c.is_active ? 'Ενεργός' : 'Ανενεργός'}
                                </td>
                                <td className="px-3 py-3">
                                    <div className="flex flex-wrap gap-2">
                                        {c.id !== currentId && (
                                            <button
                                                type="button"
                                                className="text-ts-accent"
                                                onClick={() =>
                                                    router.post(route('platform.clubs.activate', c.id))
                                                }
                                            >
                                                Ενεργοποίηση
                                            </button>
                                        )}
                                        {c.id !== currentId && (
                                            <button
                                                type="button"
                                                className="text-rose-400"
                                                onClick={() => {
                                                    if (confirm(`Διαγραφή συλλόγου «${c.name}»;`)) {
                                                        router.delete(
                                                            route('platform.clubs.destroy', c.id),
                                                        );
                                                    }
                                                }}
                                            >
                                                Διαγραφή συλλόγου
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </PlatformLayout>
    );
}
