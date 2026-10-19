import { Head } from '@inertiajs/react';

export function PublicClubShell({ title, children }) {
    return (
        <div className="min-h-screen bg-[#f0f4f8] px-4 py-8 text-slate-900">
            {title ? <Head title={title} /> : null}
            <div className="mx-auto w-full max-w-lg">
                <div className="rounded-2xl border border-slate-200/80 bg-white px-6 py-8 shadow-sm">
                    {children}
                </div>
            </div>
        </div>
    );
}

export function PublicClubLoading({ message = 'Φόρτωση...' }) {
    return (
        <PublicClubShell>
            <p className="text-sm text-slate-500">{message}</p>
        </PublicClubShell>
    );
}

export function PublicClubMessage({ title, message, children }) {
    return (
        <PublicClubShell title={title}>
            {title ? <h1 className="text-2xl font-semibold text-slate-900">{title}</h1> : null}
            {message ? <p className="mt-2 text-sm text-slate-500">{message}</p> : null}
            {children}
        </PublicClubShell>
    );
}
