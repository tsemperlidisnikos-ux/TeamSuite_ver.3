import { Link, router } from '@inertiajs/react';

/**
 * Simple reopen / continue banner for the first-week club setup wizard.
 *
 * @param {{ setupBanner: { doneCount: number, total: number, nextLabel?: string|null, dismissed?: boolean, href: string } | null | undefined }} props
 */
export default function ClubSetupBanner({ setupBanner }) {
    if (!setupBanner) {
        return null;
    }

    if (setupBanner.dismissed) {
        return (
            <div className="mb-5">
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg hover:bg-sky-50"
                    onClick={() =>
                        router.post(route('admin.setup.wizard.update'), { action: 'reopen' })
                    }
                >
                    Οδηγός πρώτης εβδομάδας
                </button>
            </div>
        );
    }

    return (
        <Link
            href={setupBanner.href || route('admin.setup.wizard')}
            className="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-950 transition hover:border-sky-400 hover:bg-sky-100"
        >
            <span className="font-medium">
                Οδηγός πρώτης εβδομάδας: {setupBanner.doneCount}/{setupBanner.total}
                {setupBanner.nextLabel ? ` · επόμενο: ${setupBanner.nextLabel}` : ''}
            </span>
            <em className="not-italic font-semibold text-teal-700 underline-offset-2 hover:underline">
                Άνοιγμα
            </em>
        </Link>
    );
}
