import LoginHistoryPanel from '@/Components/Platform/LoginHistoryPanel';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head } from '@inertiajs/react';

export default function LoginHistory({ rows, clubs, filters }) {
    return (
        <PlatformLayout title="Ιστορικό εισόδων" activeNav="logins">
            <Head title="Ιστορικό εισόδων" />
            <div className="rounded-xl border border-ts-border bg-ts-surface p-5">
                <LoginHistoryPanel rows={rows} clubs={clubs} filters={filters} />
            </div>
        </PlatformLayout>
    );
}
