import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, useForm } from '@inertiajs/react';

export default function LicensePackages({ packages }) {
    const form = useForm({ name: '', athlete_cap: 500 });

    return (
        <PlatformLayout title="Πακέτο αδειών" activeNav="packages">
            <Head title="Πακέτο αδειών" />
            <form
                className="mb-6 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('platform.license-packages.store'), {
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <input
                    className="rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    placeholder="Όνομα"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <input
                    type="number"
                    className="rounded border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                    value={form.data.athlete_cap}
                    onChange={(e) => form.setData('athlete_cap', e.target.value)}
                    required
                />
                <button type="submit" className="rounded bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg">
                    Προσθήκη
                </button>
            </form>
            <ul className="space-y-2 text-sm">
                {packages.map((p) => (
                    <li key={p.id} className="rounded border border-ts-border px-3 py-2">
                        {p.name} — όριο {p.athlete_cap}
                    </li>
                ))}
            </ul>
        </PlatformLayout>
    );
}
