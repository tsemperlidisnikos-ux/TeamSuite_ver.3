import InputError from '@/Components/InputError';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const FEATURES = [
    { title: 'ΟΡΓΑΝΩΣΗ', subtitle: 'Αθλητές & Ομάδες', icon: '👤' },
    { title: 'ΠΡΟΠΟΝΗΣΕΙΣ', subtitle: 'Πρόγραμμα & Παρουσίες', icon: '📅' },
    { title: 'ΑΓΩΝΕΣ', subtitle: 'Αποτελέσματα & Βαθμολογίες', icon: '🏆' },
    { title: 'ΠΛΗΡΩΜΕΣ', subtitle: 'Συνδρομές & Ειδοποιήσεις', icon: '💳' },
    { title: 'ΕΠΙΚΟΙΝΩΝΙΑ', subtitle: 'Ανακοινώσεις & Μηνύματα', icon: '💬' },
];

export default function Register({
    sports = ['Ποδόσφαιρο', 'Μπάσκετ', 'Βόλεϊ', 'Χάντμπολ', 'Κολύμβηση', 'Τένις', 'Άλλο'],
    levels = [
        { id: 'academies', label: 'Ακαδημίες' },
        { id: 'pre', label: 'Προαγωνιστικό' },
        { id: 'comp', label: 'Αγωνιστικό' },
    ],
}) {
    const flash = usePage().props.flash;
    const { data, setData, post, processing, errors } = useForm({
        club_name: '',
        contact_name: '',
        email: '',
        phone: '',
        sport: '',
        levels: ['academies'],
        accept_terms: false,
    });

    const toggleLevel = (id) => {
        setData(
            'levels',
            data.levels.includes(id)
                ? data.levels.filter((x) => x !== id)
                : [...data.levels, id],
        );
    };

    const field =
        'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900';

    if (flash?.success) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4">
                <Head title="Είσαι στη λίστα!" />
                <div className="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl">
                    <h1 className="text-2xl font-semibold text-slate-900">Είσαι στη λίστα!</h1>
                    <p className="mt-3 text-sm text-slate-600">
                        Ευχαριστούμε. Θα επικοινωνήσουμε όταν ανοίξει η δωρεάν δοκιμή.
                    </p>
                    <Link
                        href={route('login')}
                        className="mt-6 inline-flex rounded-md bg-teal-600 px-4 py-2 text-sm text-white"
                    >
                        Μετάβαση στη σύνδεση
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            <Head title="Εγγραφή ακαδημίας" />
            <aside className="relative hidden overflow-hidden bg-slate-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div
                    className="pointer-events-none absolute -left-10 top-10 h-64 w-64 rounded-full bg-teal-500/20 blur-3xl"
                    aria-hidden
                />
                <div>
                    <div className="text-2xl font-semibold">TeamSuite</div>
                    <h1 className="mt-10 text-3xl font-bold leading-tight tracking-tight uppercase">
                        <span className="block text-slate-400">Η ΠΛΗΡΗΣ ΣΟΥΙΤΑ</span>
                        <span className="mt-1 block text-slate-400">ΔΙΑΧΕΙΡΙΣΗΣ</span>
                        <span className="mt-1 block text-teal-400">ΑΘΛΗΤΙΚΩΝ ΑΚΑΔΗΜΙΩΝ</span>
                    </h1>
                    <p className="mt-4 max-w-md text-sm leading-relaxed text-slate-300">
                        Οργάνωσε την ακαδημία σου: αθλητές, προπονήσεις, αγώνες, επικοινωνία και
                        πληρωμές σε ένα σύγχρονο περιβάλλον.
                    </p>
                    <ul className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        {FEATURES.map((f) => (
                            <li key={f.title} className="text-center">
                                <div className="mx-auto mb-2 flex h-9 w-9 items-center justify-center rounded-full bg-teal-500/15 text-base text-teal-300">
                                    <span aria-hidden>{f.icon}</span>
                                </div>
                                <div className="text-[10px] font-bold tracking-wide text-teal-300">
                                    {f.title}
                                </div>
                                <div className="mt-0.5 text-[10px] leading-snug text-slate-400">
                                    {f.subtitle}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
                <div className="mt-10 flex gap-3 rounded-xl border border-teal-500/40 bg-slate-900/60 p-4">
                    <div className="text-2xl text-teal-400" aria-hidden>
                        📅
                    </div>
                    <div>
                        <div className="text-sm font-semibold uppercase tracking-wide text-teal-300">
                            ΞΕΚΙΝΑΜΕ ΣΥΝΤΟΜΑ
                        </div>
                        <p className="mt-1 text-xs text-slate-400">
                            Άφησε τα στοιχεία σου για να είσαι από τους πρώτους που θα το δοκιμάσουν.
                        </p>
                    </div>
                </div>
            </aside>

            <div className="flex items-center justify-center bg-slate-100 px-4 py-10">
                <form
                    className="w-full max-w-md rounded-2xl bg-white p-6 shadow-lg sm:p-8"
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('register'));
                    }}
                >
                    <div className="mb-5 text-center">
                        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-800 text-xl text-teal-400">
                            +
                        </div>
                        <h2 className="text-xl font-semibold text-slate-900">Εγγραφή ακαδημίας</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Συμπλήρωσε τη φόρμα για να μπεις στη λίστα αναμονής για τη δωρεάν δοκιμή.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <label className="block text-sm font-medium text-slate-700">
                            Όνομα ακαδημίας <span className="text-rose-500">*</span>
                            <input
                                className={field}
                                value={data.club_name}
                                onChange={(e) => setData('club_name', e.target.value)}
                                placeholder="Π.χ. Α.Ο. Νίκη"
                                required
                            />
                            <InputError message={errors.club_name} className="mt-1" />
                        </label>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm font-medium text-slate-700">
                                Ονοματεπώνυμο υπευθύνου <span className="text-rose-500">*</span>
                                <input
                                    className={field}
                                    value={data.contact_name}
                                    onChange={(e) => setData('contact_name', e.target.value)}
                                    placeholder="Π.χ. Γιάννης Παπαδόπουλος"
                                    required
                                />
                            </label>
                            <label className="block text-sm font-medium text-slate-700">
                                Email επικοινωνίας <span className="text-rose-500">*</span>
                                <input
                                    type="email"
                                    className={field}
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="you@academy.gr"
                                    required
                                />
                            </label>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm font-medium text-slate-700">
                                Τηλέφωνο επικοινωνίας <span className="text-rose-500">*</span>
                                <input
                                    className={field}
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="69XXXXXXXX"
                                    required
                                />
                            </label>
                            <label className="block text-sm font-medium text-slate-700">
                                Άθλημα <span className="text-rose-500">*</span>
                                <select
                                    className={field}
                                    value={data.sport}
                                    onChange={(e) => setData('sport', e.target.value)}
                                    required
                                >
                                    <option value="">Επιλέξτε άθλημα</option>
                                    {sports.map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        <fieldset>
                            <legend className="text-sm font-medium text-slate-700">
                                Επίπεδο τμημάτων <span className="text-rose-500">*</span>
                            </legend>
                            <div className="mt-2 flex flex-wrap gap-3">
                                {levels.map((opt) => (
                                    <label
                                        key={opt.id}
                                        className="flex items-center gap-2 text-sm text-slate-600"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.levels.includes(opt.id)}
                                            onChange={() => toggleLevel(opt.id)}
                                        />
                                        {opt.label}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.levels} className="mt-1" />
                        </fieldset>

                        <label className="flex items-start gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={data.accept_terms}
                                onChange={(e) => setData('accept_terms', e.target.checked)}
                                required
                            />
                            <span>
                                Διάβασα και αποδέχομαι τους{' '}
                                <Link
                                    href={route('public.legal.show', 'terms')}
                                    className="text-teal-600 underline"
                                >
                                    Όρους Χρήσης
                                </Link>
                                , την{' '}
                                <Link
                                    href={route('public.legal.show', 'privacy')}
                                    className="text-teal-600 underline"
                                >
                                    Πολιτική Απορρήτου
                                </Link>{' '}
                                και τη{' '}
                                <Link
                                    href={route('public.legal.show', 'privacy')}
                                    className="text-teal-600 underline"
                                >
                                    Συμφωνία Επεξεργασίας Δεδομένων (DPA)
                                </Link>{' '}
                                όπου ο σύλλογος είναι υπεύθυνος επεξεργασίας και η πλατφόρμα εκτελών
                                την επεξεργασία.
                            </span>
                        </label>
                        <InputError message={errors.accept_terms} />

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-md bg-teal-600 py-3 text-sm font-semibold tracking-wide text-white hover:bg-teal-700 disabled:opacity-60"
                        >
                            ΕΓΓΡΑΦΗ ΣΤΗ ΛΙΣΤΑ ΑΝΑΜΟΝΗΣ
                        </button>
                        <p className="text-center text-xs text-slate-500">
                            🔒 Τα στοιχεία σας είναι ασφαλή και δεν θα κοινοποιηθούν
                        </p>
                    </div>
                </form>
            </div>
        </div>
    );
}
