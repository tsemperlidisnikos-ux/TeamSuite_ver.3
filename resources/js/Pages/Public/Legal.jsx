import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Legal({ doc, docs = [], html }) {
    const [consent, setConsent] = useState(() => {
        if (typeof window === 'undefined') return null;
        try {
            return JSON.parse(localStorage.getItem('ts-cookie-consent-v1') || 'null');
        } catch {
            return null;
        }
    });

    const saveConsent = (analytics, marketing) => {
        const next = {
            analytics,
            marketing,
            updatedAt: new Date().toISOString(),
        };
        localStorage.setItem('ts-cookie-consent-v1', JSON.stringify(next));
        setConsent(next);
    };

    return (
        <div className="min-h-screen bg-ts-bg px-4 py-8 text-ts-fg">
            <Head
                title={
                    doc === 'privacy'
                        ? 'Πολιτική Απορρήτου — TeamSuite'
                        : `Νομικά — ${docs.find((d) => d.id === doc)?.label || 'Έγγραφα'}`
                }
            />
            <div className="mx-auto max-w-3xl">
                <nav className="mb-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm">
                    <Link href={route('login')} className="text-sky-600 hover:underline">
                        Σύνδεση
                    </Link>
                    {docs.map((item) => (
                        <Link
                            key={item.id}
                            href={route('public.legal.show', item.id)}
                            className={
                                item.id === doc
                                    ? 'border-b border-sky-600 pb-0.5 font-medium text-sky-700'
                                    : 'text-sky-600 hover:underline'
                            }
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <article
                    className="prose prose-sm max-w-none text-slate-800 prose-headings:text-slate-900"
                    dangerouslySetInnerHTML={{ __html: html }}
                />

                {doc === 'cookies' && (
                    <div className="mt-4 rounded-xl border border-ts-border bg-ts-surface p-4">
                        <h2 className="text-base font-semibold">Οι επιλογές σας</h2>
                        <p className="mt-1 text-sm text-ts-muted">
                            {consent
                                ? `Analytics: ${consent.analytics ? 'Ναι' : 'Όχι'} · Marketing: ${
                                      consent.marketing ? 'Ναι' : 'Όχι'
                                  }`
                                : 'Δεν έχετε ακόμη αποφασίσει.'}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                                onClick={() => saveConsent(true, true)}
                            >
                                Αποδοχή όλων
                            </button>
                            <button
                                type="button"
                                className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                                onClick={() => saveConsent(false, false)}
                            >
                                Απόρριψη προαιρετικών
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
