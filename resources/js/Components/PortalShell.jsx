import { Link, usePage } from '@inertiajs/react';

export function PortalPanel({ title, icon, children, action }) {
    return (
        <section className="rounded-xl border border-ts-border bg-ts-surface p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold text-ts-fg">
                    {icon ? <span className="text-ts-accent">{icon}</span> : null}
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export function PortalStatCard({ label, value, hint }) {
    return (
        <article className="rounded-xl border border-ts-border bg-ts-surface p-3 shadow-sm">
            <div className="text-[11px] font-medium uppercase tracking-wide text-ts-muted">{label}</div>
            <div className="mt-1 text-lg font-semibold text-ts-fg">{value}</div>
            {hint ? <div className="mt-0.5 text-xs text-ts-muted">{hint}</div> : null}
        </article>
    );
}

export function PortalTable({ columns, rows, empty }) {
    if (!rows?.length) {
        return empty ? <p className="text-sm text-ts-muted">{empty}</p> : null;
    }
    return (
        <div className="overflow-x-auto rounded-lg border border-ts-border">
            <table className="min-w-full text-left text-sm">
                <thead className="bg-ts-surface-2 text-[11px] uppercase tracking-wide text-ts-muted">
                    <tr>
                        {columns.map((col) => (
                            <th key={col.key} className="px-3 py-2 font-semibold">
                                {col.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-t border-ts-border">
                            {columns.map((col) => (
                                <td key={col.key} className="px-3 py-2 align-top text-ts-fg-soft">
                                    {col.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function PortalHeader({ title, subtitle }) {
    const { auth, platformBranding } = usePage().props;
    const userName = auth?.user?.name || 'χρήστης';
    const roleLabel = auth?.user?.role_label || '';
    const appName = platformBranding?.appName || 'TeamSuite';

    return (
        <header className="border-b border-ts-border bg-ts-surface px-4 py-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="text-[11px] font-medium uppercase tracking-wider text-ts-accent">
                        {appName}
                    </div>
                    <h1 className="text-xl font-semibold text-ts-fg">{title}</h1>
                    <p className="mt-1 text-sm text-ts-muted">
                        {subtitle ||
                            `Καλώς ήρθατε, ${userName}. Πρόγραμμα, πληρωμές και έγγραφα των αθλητών σας.`}
                    </p>
                </div>
                <div className="text-right">
                    {roleLabel ? (
                        <span className="inline-block rounded-full bg-ts-accent-soft px-2 py-0.5 text-xs font-medium text-ts-accent">
                            {roleLabel}
                        </span>
                    ) : null}
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="mt-2 block text-sm text-ts-muted hover:text-ts-fg"
                    >
                        Έξοδος
                    </Link>
                </div>
            </div>
        </header>
    );
}

export function PortalPillTabs({ tabs, active, onChange }) {
    return (
        <nav className="mb-4 flex flex-wrap gap-2" aria-label="Καρτέλες πύλης">
            {tabs.map((tab) => (
                <button
                    key={tab.id}
                    type="button"
                    onClick={() => onChange(tab.id)}
                    className={`inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm ${
                        active === tab.id
                            ? 'border-ts-accent bg-ts-accent text-ts-accent-fg'
                            : 'border-ts-border bg-ts-surface text-ts-fg-soft hover:bg-ts-hover'
                    }`}
                >
                    {tab.glyph ? (
                        <span className="text-sm leading-none" aria-hidden>
                            {tab.glyph}
                        </span>
                    ) : null}
                    {tab.label}
                </button>
            ))}
        </nav>
    );
}

export function PortalBottomTabs({ tabs, active, onChange }) {
    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-10 flex border-t border-ts-border bg-ts-surface/95 backdrop-blur md:hidden"
            aria-label="Ενότητες πύλης"
        >
            {tabs.map((tab) => (
                <button
                    key={tab.id}
                    type="button"
                    onClick={() => onChange(tab.id)}
                    className={`flex flex-1 flex-col items-center py-2.5 text-[11px] ${
                        active === tab.id ? 'font-semibold text-ts-accent' : 'text-ts-fg-soft'
                    }`}
                >
                    <span className="text-base leading-none" aria-hidden>
                        {tab.glyph}
                    </span>
                    <span className="mt-1">{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

export function BalanceBadge({ amount }) {
    const n = Number(amount || 0);
    const cls =
        n > 0
            ? 'bg-amber-100 text-amber-900'
            : 'bg-ts-accent-soft text-teal-900';
    return (
        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>
            {formatCurrencyInline(n)}
        </span>
    );
}

function formatCurrencyInline(amount) {
    return Number(amount || 0).toLocaleString('el-GR', {
        style: 'currency',
        currency: 'EUR',
    });
}
