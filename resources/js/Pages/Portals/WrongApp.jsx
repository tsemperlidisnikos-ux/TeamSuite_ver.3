import { Head, Link } from '@inertiajs/react';

export default function WrongApp({ audience, roleLabel }) {
    const title =
        audience === 'coach'
            ? 'Αυτή η εφαρμογή είναι για προπονητές'
            : 'Αυτή η εφαρμογή είναι για γονείς';

    return (
        <div className="flex min-h-screen flex-col items-center bg-gradient-to-b from-sky-50 to-white px-4 pt-24 text-center">
            <Head title={title} />
            <h1 className="max-w-lg text-2xl font-bold text-slate-900">{title}</h1>
            <p className="mt-3 max-w-md text-sm text-slate-600">
                Συνδεθήκατε ως {roleLabel || 'χρήστης'}. Για τη γραμματεία χρησιμοποιήστε την κανονική
                είσοδο του TeamSuite.
            </p>
            <Link
                href={route('logout')}
                method="post"
                as="button"
                className="mt-8 rounded-md bg-teal-700 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-teal-800"
            >
                Έξοδος
            </Link>
        </div>
    );
}
