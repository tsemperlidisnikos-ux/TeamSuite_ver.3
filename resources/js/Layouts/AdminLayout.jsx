import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function badgeText(n) {
    if (!n || n <= 0) return null;
    return n > 99 ? '99+' : String(n);
}

function routeIsActive(routeName) {
    if (routeName === 'dashboard') {
        return route().current('dashboard');
    }
    const base = routeName.replace(/\.index$/, '');
    return route().current(routeName) || route().current(`${base}.*`);
}

export default function AdminLayout({ title, children, compact = false }) {
    const { auth, navigation, currentClub, appVersion, flash, clubAlerts, platformBranding } =
        usePage().props;
    const isImpersonating = !!auth?.impersonating;
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [exportBusy, setExportBusy] = useState(false);

    const items = navigation?.items || [];
    const isPlatformAdmin = auth?.user?.is_platform_admin;
    const lowStock = Number(clubAlerts?.lowStock || 0);
    const pendingRegs = Number(clubAlerts?.pendingRegistrations || 0);
    const clubName = currentClub?.name || 'TeamSuite';
    const headerTitle =
        (currentClub?.header_title || '').trim() || clubName;
    const brandLogoUrl =
        platformBranding?.appLogoUrl ||
        '/images/brand/apollon-patras-mark.png';
    const roleLabel = auth?.user?.role_label || '';

    const handleClubLogoQuickExport = () => {
        if (exportBusy) return;
        setExportBusy(true);
        window.open(route('admin.settings.backup.download'), '_blank');
        window.open(route('admin.athletes.export'), '_blank');
        window.setTimeout(() => setExportBusy(false), 1500);
    };

    const sidebar = useMemo(
        () => (
            <div
                className="flex h-full flex-col text-[color:var(--ts-chrome-fg)]"
                style={{ backgroundColor: 'var(--ts-chrome)' }}
            >
                <div className="border-b border-white/10 px-4 py-4">
                    {currentClub?.logo_url ? (
                        <button
                            type="button"
                            onClick={handleClubLogoQuickExport}
                            disabled={exportBusy}
                            title="Λήψη backup συλλόγου (JSON) και εξαγωγή αθλητών (Excel)"
                            aria-label="Λήψη backup συλλόγου (JSON) και εξαγωγή αθλητών (Excel)"
                            className="flex w-full items-center justify-center rounded-md border border-white/10 bg-white/5 p-3 hover:bg-white/10 disabled:opacity-60"
                        >
                            <img
                                src={currentClub.logo_url}
                                alt={clubName}
                                className="max-h-32 max-w-full object-contain"
                            />
                        </button>
                    ) : (
                        <Link
                            href={route('admin.settings.index')}
                            className="flex w-full flex-col items-center justify-center gap-1 rounded-md border border-dashed border-white/20 bg-white/5 px-3 py-5 text-center hover:bg-white/10"
                            title="Ανέβασμα λογοτύπου συλλόγου"
                        >
                            <span className="text-xs font-medium text-slate-300">
                                Λογότυπο συλλόγου
                            </span>
                            <span className="text-[11px] text-slate-500">
                                Ανέβασμα από Ρυθμίσεις
                            </span>
                        </Link>
                    )}
                    {currentClub?.logo_url && (
                        <div className="mt-1.5 text-center text-[10px] text-slate-500">
                            Κλικ στο λογότυπο → backup
                        </div>
                    )}
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    {items.map((item) => {
                        const pending =
                            item.id === 'athletes'
                                ? badgeText(clubAlerts?.pendingRegistrations)
                                : item.id === 'warehouse'
                                  ? badgeText(clubAlerts?.lowStock)
                                  : null;
                        const active = routeIsActive(item.route);
                        const titleHint =
                            item.id === 'athletes' && pending
                                ? 'Εκκρεμείς αιτήσεις εγγραφής'
                                : item.id === 'warehouse' && pending
                                  ? 'Χαμηλό απόθεμα'
                                  : undefined;

                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={`flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm transition ${
                                    active
                                        ? 'bg-ts-accent text-ts-accent-fg'
                                        : 'text-slate-300 hover:bg-white/10 hover:text-white'
                                }`}
                            >
                                <span>{item.label}</span>
                                {pending && (
                                    <span
                                        title={titleHint}
                                        className="min-w-[1.25rem] rounded-full bg-rose-600 px-1.5 py-0.5 text-center text-[10px] font-semibold leading-none text-white"
                                    >
                                        {pending}
                                    </span>
                                )}
                            </Link>
                        );
                    })}

                    {isPlatformAdmin && (
                        <Link
                            href={route('platform.dashboard')}
                            className="mt-2 block rounded-md px-3 py-2 text-sm text-amber-400 hover:bg-white/10"
                        >
                            Διαχείριση πλατφόρμας
                        </Link>
                    )}
                </nav>

                <div className="border-t border-white/10 px-4 py-3 text-[11px] text-slate-500">
                    ver. {appVersion || '3'}
                </div>
            </div>
        ),
        [
            items,
            currentClub,
            isPlatformAdmin,
            appVersion,
            clubAlerts,
            exportBusy,
            clubName,
        ],
    );

    return (
        <div
            className={`bg-ts-bg text-ts-fg ${
                compact ? 'admin-layout-compact flex h-dvh flex-col overflow-hidden' : 'min-h-screen'
            }`}
        >
            <header
                className={`z-30 shrink-0 text-[color:var(--ts-chrome-fg)] ${
                    compact ? 'sticky top-0' : 'sticky top-0'
                } ${
                    compact
                        ? 'min-h-0'
                        : 'min-h-[5.5rem] sm:min-h-[7.5rem]'
                }`}
                style={{ backgroundColor: 'var(--ts-chrome)' }}
            >
                <div
                    className={`relative flex items-center justify-between gap-3 px-4 lg:px-6 ${
                        compact
                            ? 'min-h-14 py-1.5'
                            : 'min-h-[5.5rem] py-2 sm:min-h-[7.5rem]'
                    }`}
                >
                    <div className="relative z-10 flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            className="rounded-md border border-white/20 px-2 py-1 text-sm text-slate-200 lg:hidden"
                            onClick={() => setDrawerOpen(true)}
                            aria-label="Άνοιγμα μενού"
                        >
                            Μενού
                        </button>
                    </div>

                    <div
                        className="app-header-brand pointer-events-none absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center gap-2 sm:gap-3"
                        aria-label={`${headerTitle} Suite`}
                    >
                        <img
                            src={brandLogoUrl}
                            alt=""
                            className="app-header-brand-logo"
                        />
                        <span className="app-header-brand-name">{headerTitle}</span>
                        <span className="app-header-brand-suite">Suite</span>
                    </div>

                    <div className="relative z-10 flex shrink-0 items-center gap-3">
                        {pendingRegs > 0 && (
                            <Link
                                href={route('admin.athletes.index')}
                                className="hidden rounded-full bg-rose-600/90 px-2 py-0.5 text-xs font-medium text-white sm:inline"
                                title="Εκκρεμείς αιτήσεις εγγραφής"
                            >
                                {badgeText(pendingRegs)} αιτήσεις
                            </Link>
                        )}
                        <div className="hidden text-right sm:block">
                            <div className="text-sm font-semibold leading-tight text-white">
                                {auth?.user?.name}
                            </div>
                            {roleLabel ? (
                                <div className="text-[11px] text-slate-400">{roleLabel}</div>
                            ) : null}
                        </div>
                        {isImpersonating && !compact && (
                            <Link
                                href={route('platform.users.leave')}
                                method="post"
                                as="button"
                                className="rounded-md bg-amber-600 px-2.5 py-1 text-sm text-white hover:bg-amber-500"
                            >
                                Επιστροφή στη πλατφόρμα
                            </Link>
                        )}
                        {isPlatformAdmin && !isImpersonating && (
                            <Link
                                href={route('platform.dashboard')}
                                className="hidden text-sm text-amber-400 hover:underline md:inline"
                                title="Διαχείριση πλατφόρμας"
                            >
                                Platform
                            </Link>
                        )}
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white"
                            title="Αποσύνδεση"
                            aria-label="Αποσύνδεση"
                        >
                            ⎋
                        </Link>
                    </div>
                </div>
            </header>

            <div
                className={`flex min-h-0 ${compact ? 'flex-1 overflow-hidden' : 'min-h-[calc(100vh-3.5rem)]'}`}
            >
                <aside
                    className="hidden w-64 shrink-0 overflow-y-auto lg:block"
                    style={{ backgroundColor: 'var(--ts-chrome)' }}
                >
                    {sidebar}
                </aside>

                {drawerOpen && (
                    <div className="fixed inset-0 z-40 lg:hidden">
                        <button
                            type="button"
                            className="absolute inset-0 bg-slate-900/50"
                            aria-label="Κλείσιμο μενού"
                            onClick={() => setDrawerOpen(false)}
                        />
                        <aside
                            className="absolute inset-y-0 left-0 w-72 shadow-xl"
                            style={{ backgroundColor: 'var(--ts-chrome)' }}
                        >
                            {sidebar}
                        </aside>
                    </div>
                )}

                <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                    {isImpersonating && (
                        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-1.5 text-sm text-amber-900 lg:px-8">
                            <div className="min-w-0 truncate">
                                <strong>Είσοδος ως χρήστης</strong>
                                <span className="ms-2">
                                    {auth?.user?.name}
                                    {clubName ? ` · ${clubName}` : ''}
                                </span>
                            </div>
                            <Link
                                href={route('platform.users.leave')}
                                method="post"
                                as="button"
                                className="shrink-0 rounded-md border border-amber-300 bg-white px-2.5 py-1 text-sm hover:bg-amber-100"
                            >
                                Επιστροφή στη πλατφόρμα
                            </Link>
                        </div>
                    )}
                    {lowStock > 0 && !compact && (
                        <Link
                            href={`${route('admin.warehouse.index')}?status=low`}
                            className="block border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 lg:px-8"
                        >
                            <strong>{lowStock}</strong> προϊόντα στην αποθήκη είναι κάτω από το
                            ελάχιστο απόθεμα.
                        </Link>
                    )}
                    {flash?.success && !compact && (
                        <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 lg:px-8">
                            {flash.success}
                        </div>
                    )}
                    <main
                        className={`min-h-0 flex-1 px-4 lg:px-8 ${
                            compact
                                ? 'flex flex-col overflow-hidden py-4'
                                : 'overflow-y-auto py-6'
                        }`}
                    >
                        {title ? (
                            <span className="sr-only">{title}</span>
                        ) : null}
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
