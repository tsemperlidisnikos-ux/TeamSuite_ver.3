import { Link, usePage } from '@inertiajs/react';

export default function PortalLayout({ title, children, activeTab }) {
    const { currentClub } = usePage().props;

    const tabs = [
        { key: 'parent', label: 'Γονέας', route: 'portal.parent' },
        { key: 'coach', label: 'Προπονητής', route: 'portal.coach' },
        { key: 'athlete', label: 'Αθλητής', route: 'portal.athlete' },
    ];

    return (
        <div className="flex min-h-screen flex-col bg-ts-bg text-ts-fg">
            <header className="border-b border-ts-border bg-ts-surface px-4 py-3">
                <h1 className="text-lg font-semibold text-ts-fg">{title}</h1>
                <p className="text-xs text-ts-muted">{currentClub?.name || 'TeamSuite'}</p>
            </header>

            <main className="flex-1 overflow-y-auto px-4 py-4 pb-24">{children}</main>

            <nav className="fixed bottom-0 left-0 right-0 border-t border-ts-border bg-ts-surface/95 backdrop-blur">
                <div className="mx-auto flex max-w-lg">
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={route(tab.route)}
                            className={`flex flex-1 flex-col items-center py-3 text-xs ${
                                activeTab === tab.key
                                    ? 'text-ts-accent'
                                    : 'text-ts-muted hover:text-ts-fg-soft'
                            }`}
                        >
                            <span className="font-medium">{tab.label}</span>
                        </Link>
                    ))}
                </div>
            </nav>
        </div>
    );
}
