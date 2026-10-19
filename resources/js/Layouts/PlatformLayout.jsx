import { Link, usePage } from '@inertiajs/react';

function platformHref(name, fallback) {
    try {
        return route(name);
    } catch {
        return fallback;
    }
}

export default function PlatformLayout({ title = 'Διαχείριση', children, activeNav = 'manage' }) {
    const { auth, flash } = usePage().props;
    const nav = [
        { id: 'manage', label: 'Διαχείριση', href: platformHref('platform.dashboard', '/platform') },
        { id: 'users', label: 'Χρήστες', href: platformHref('platform.users.index', '/platform/users') },
        { id: 'packages', label: 'Πακέτο αδειών', href: platformHref('platform.license-packages.index', '/platform/license-packages') },
        { id: 'clubs', label: 'Σύλλογοι', href: platformHref('platform.clubs.index', '/platform/clubs') },
        { id: 'waitlist', label: 'Λίστα αναμονής', href: platformHref('platform.waitlist.index', '/platform/waitlist') },
        { id: 'logins', label: 'Ιστορικό εισόδων', href: platformHref('platform.logins.index', '/platform/logins') },
        { id: 'activity', label: 'Ημερολόγιο συλλόγου', href: platformHref('platform.activity.index', '/platform/activity') },
    ];

    return (
        <div className="min-h-screen bg-ts-bg text-ts-fg">
            <header className="border-b border-ts-border/80 bg-ts-bg">
                <div className="mx-auto flex max-w-7xl flex-wrap items-start justify-between gap-3 px-4 py-4 lg:px-8">
                    <div>
                        <div className="text-[11px] uppercase tracking-[0.16em] text-ts-accent">
                            Platform Admin
                        </div>
                        <h1 className="text-2xl font-semibold text-ts-fg">{title}</h1>
                        <p className="mt-1 max-w-xl text-sm text-ts-muted">
                            Ρυθμίσεις πλατφόρμας και ακαδημίας για συλλόγους και καταλόγους.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="me-2 text-ts-muted">{auth?.user?.name}</span>
                        {nav.map((item) => (
                            <Link
                                key={item.id}
                                href={item.href}
                                className={`rounded-md px-2.5 py-1.5 ${
                                    activeNav === item.id
                                        ? 'bg-ts-accent text-ts-accent-fg'
                                        : 'text-ts-fg-soft hover:bg-ts-surface hover:text-ts-fg'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="rounded-md px-2.5 py-1.5 text-rose-600 hover:bg-rose-50"
                        >
                            Αποσύνδεση
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-4 py-6 lg:px-8">
                {flash?.success && (
                    <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        {flash.error}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
