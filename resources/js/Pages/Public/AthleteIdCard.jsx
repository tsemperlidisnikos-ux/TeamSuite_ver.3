import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function publicLogoUrl(value) {
    const url = String(value ?? '').trim();
    if (!url) return null;
    if (url.startsWith('data:')) return null;
    if (!/^https?:\/\//i.test(url)) return null;
    if (url.length > 400) return null;
    return url;
}

function parseAthleteIdCardHash(hash) {
    try {
        const raw = String(hash || '')
            .replace(/^#/, '')
            .trim();
        if (!raw) return null;
        const json = decodeURIComponent(escape(atob(raw)));
        const data = JSON.parse(json);
        if (data.v !== 1) return null;
        if (!data.lastName || !data.firstName) return null;
        return {
            v: 1,
            clubName: String(data.clubName ?? '').trim(),
            season: String(data.season ?? '').trim(),
            lastName: String(data.lastName).trim(),
            firstName: String(data.firstName).trim(),
            birthDate: String(data.birthDate ?? '').trim(),
            athleteCode: String(data.athleteCode ?? '').trim(),
            logoUrl: publicLogoUrl(data.logoUrl),
        };
    } catch {
        return null;
    }
}

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(`${String(value).slice(0, 10)}T12:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('el-GR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function buildAthleteIdCardUrl(origin, payload) {
    const json = JSON.stringify({
        v: 1,
        clubName: String(payload.clubName || '').trim(),
        season: String(payload.season || '').trim(),
        lastName: String(payload.lastName || '').trim(),
        firstName: String(payload.firstName || '').trim(),
        birthDate: String(payload.birthDate || '').trim(),
        athleteCode: String(payload.athleteCode || '').trim(),
        logoUrl: publicLogoUrl(payload.logoUrl),
    });
    const encoded = btoa(unescape(encodeURIComponent(json)));
    return `${String(origin || '').replace(/\/$/, '')}/id/card#${encoded}`;
}

export default function AthleteIdCard({ appName = 'TeamSuite', appLogoUrl = null }) {
    const [payload, setPayload] = useState(null);

    useEffect(() => {
        const read = () => setPayload(parseAthleteIdCardHash(window.location.hash));
        read();
        window.addEventListener('hashchange', read);
        return () => window.removeEventListener('hashchange', read);
    }, []);

    const logoUrl = useMemo(
        () => payload?.logoUrl || publicLogoUrl(appLogoUrl),
        [payload?.logoUrl, appLogoUrl],
    );

    const initials = (payload?.clubName || appName || 'SS')
        .trim()
        .slice(0, 2)
        .toUpperCase();

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 px-4 py-10 text-slate-100">
            <Head title="Επαλήθευση ταυτότητας αθλητή" />
            <div className="w-full max-w-md">
                <p className="mb-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-teal-300/90">
                    {appName || 'TeamSuite'}
                </p>
                <h1 className="mb-6 text-center text-2xl font-semibold tracking-tight">
                    Επαλήθευση ταυτότητας αθλητή
                </h1>

                {!payload ? (
                    <div className="rounded-2xl border border-slate-700 bg-slate-900/80 px-6 py-8 text-center shadow-xl">
                        <p className="text-sm text-slate-300">
                            Μη έγκυρο ή κενό QR. Σκανάρετε ξανά την κάρτα αθλητή.
                        </p>
                        <Link href={route('login')} className="mt-4 inline-block text-sm text-teal-300 hover:underline">
                            Σύνδεση
                        </Link>
                    </div>
                ) : (
                    <article className="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900/90 shadow-xl">
                        <div className="flex items-center gap-4 border-b border-slate-700 bg-gradient-to-r from-teal-900/60 to-slate-900 px-5 py-4">
                            {logoUrl ? (
                                <img
                                    className="h-14 w-14 rounded-full object-cover ring-2 ring-teal-500/40"
                                    src={logoUrl}
                                    alt={payload.clubName || 'Logo συλλόγου'}
                                />
                            ) : (
                                <div
                                    className="flex h-14 w-14 items-center justify-center rounded-full bg-teal-800 text-lg font-bold text-teal-50"
                                    aria-hidden
                                >
                                    {initials}
                                </div>
                            )}
                            <div>
                                <strong className="block text-sm tracking-wide text-teal-100">
                                    ΤΑΥΤΟΤΗΤΑ ΑΘΛΗΤΗ
                                </strong>
                                <span className="text-sm text-slate-300">
                                    {payload.clubName || 'Σύλλογος'}
                                    {payload.season ? ` · Σεζόν ${payload.season}` : ''}
                                </span>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4 px-5 py-5 text-sm">
                            <div>
                                <span className="text-xs uppercase tracking-wide text-slate-500">Επώνυμο</span>
                                <strong className="mt-1 block text-slate-100">{payload.lastName || '—'}</strong>
                            </div>
                            <div>
                                <span className="text-xs uppercase tracking-wide text-slate-500">Όνομα</span>
                                <strong className="mt-1 block text-slate-100">{payload.firstName || '—'}</strong>
                            </div>
                            <div>
                                <span className="text-xs uppercase tracking-wide text-slate-500">
                                    Ημ. γέννησης
                                </span>
                                <strong className="mt-1 block text-slate-100">
                                    {payload.birthDate ? formatDate(payload.birthDate) : '—'}
                                </strong>
                            </div>
                            <div>
                                <span className="text-xs uppercase tracking-wide text-slate-500">
                                    Κωδ. αθλητή
                                </span>
                                <strong className="mt-1 block font-mono text-slate-100">
                                    {payload.athleteCode || '—'}
                                </strong>
                            </div>
                        </div>
                        <p className="border-t border-slate-800 px-5 py-3 text-xs leading-relaxed text-slate-400">
                            Εμφανίζονται μόνο δημόσια στοιχεία επαλήθευσης (χωρίς ΑΜΚΑ, ιατρικά ή
                            στοιχεία επικοινωνίας).
                        </p>
                    </article>
                )}
            </div>
        </div>
    );
}
