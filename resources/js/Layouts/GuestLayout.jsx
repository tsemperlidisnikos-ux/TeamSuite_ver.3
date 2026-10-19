import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';

export default function GuestLayout({
    children,
    brandTitle = 'TeamSuite',
    brandAccent = '',
    tagline = 'Η ολοκληρωμένη πλατφόρμα διαχείρισης για αθλητικούς οργανισμούς και ομάδες.',
    split = true,
}) {
    const logoUrl = usePage().props.platformBranding?.appLogoUrl;

    if (!split) {
        return (
            <div className="flex min-h-screen flex-col items-center bg-ts-bg pt-6 sm:justify-center sm:pt-0">
                <Link href="/">
                    {logoUrl ? (
                        <img src={logoUrl} alt="" className="h-16 object-contain" />
                    ) : (
                        <ApplicationLogo className="h-20 w-20 fill-current text-ts-muted" />
                    )}
                </Link>
                <div className="mt-6 w-full overflow-hidden rounded-lg border border-ts-border bg-ts-surface px-6 py-4 sm:max-w-md">
                    {children}
                </div>
            </div>
        );
    }

    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            <aside className="relative hidden overflow-hidden bg-black p-10 text-white lg:flex lg:flex-col lg:justify-center">
                <div
                    className="pointer-events-none absolute -left-16 top-0 h-80 w-80 rounded-full bg-teal-500/25 blur-3xl"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute left-1/3 top-1/3 h-64 w-64 rounded-full bg-teal-400/10 blur-3xl"
                    aria-hidden
                />
                <div className="relative z-10">
                    <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                        <span className="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                            {brandTitle}
                        </span>
                        {brandAccent ? (
                            <span className="text-4xl font-extrabold tracking-tight text-teal-400 sm:text-5xl">
                                {brandAccent}
                            </span>
                        ) : null}
                    </div>
                    {/* Short teal underline under brand (Team / accent word) */}
                    <div className="mt-3.5 h-1 w-[3.25rem] rounded-full bg-teal-400" aria-hidden />
                    <p className="mt-4 max-w-md text-base font-medium leading-relaxed text-white/80">
                        {tagline}
                    </p>
                </div>
            </aside>

            <div className="flex flex-col items-center justify-center bg-[#f0f4f8] px-4 py-10">
                <div className="mb-6 lg:hidden">
                    <div className="text-center text-2xl font-extrabold text-ts-fg">
                        {brandTitle}{' '}
                        {brandAccent ? (
                            <span className="text-teal-600">{brandAccent}</span>
                        ) : null}
                    </div>
                </div>
                <div className="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white px-6 py-6 shadow-[0_24px_60px_rgba(0,0,0,0.12)] sm:px-7">
                    {children}
                </div>
            </div>
        </div>
    );
}
