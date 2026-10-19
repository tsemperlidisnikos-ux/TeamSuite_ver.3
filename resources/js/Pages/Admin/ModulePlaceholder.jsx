import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

export default function ModulePlaceholder({ title, module, phaseHint }) {
    return (
        <AdminLayout title={title}>
            <Head title={title} />
            <div className="rounded-lg border border-ts-border bg-ts-surface p-6">
                <h2 className="text-xl font-semibold text-ts-fg">{title}</h2>
                <p className="mt-2 text-sm text-ts-muted">
                    Module <code className="text-sky-300">{module}</code> — σκελετός
                    UI. Πλήρης υλοποίηση: {phaseHint || 'επόμενες φάσεις'}.
                </p>
            </div>
        </AdminLayout>
    );
}
