import { PublicClubMessage } from '@/Components/PublicClubShell';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const HEALTH_OPTIONS = [
    { value: 'allow', label: 'ΕΠΙΤΡΕΠΩ' },
    { value: 'deny', label: 'ΔΕΝ ΕΠΙΤΡΕΠΩ' },
];

const LIABILITY_OPTIONS = [
    { value: 'accept', label: 'ΔΕΧΟΜΑΙ' },
    { value: 'decline', label: 'ΔΕΝ ΔΕΧΟΜΑΙ' },
];

const MEDIA_OPTIONS = [
    { value: 'consent', label: 'ΣΥΝΑΙΝΩ' },
    { value: 'decline', label: 'ΔΕΝ ΣΥΝΑΙΝΩ' },
];

const GDPR_REQUIRED_MSG = 'Απαιτείται «ΕΛΑΒΑ ΓΝΩΣΗ» για την προστασία προσωπικών δεδομένων.';

function ChoiceCard({ title, children }) {
    return (
        <fieldset className="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
            <legend className="px-1 text-sm font-semibold text-slate-800">
                {title}{' '}
                <span className="text-rose-600" aria-hidden>
                    *
                </span>
            </legend>
            <div className="mt-2 space-y-2 text-sm text-slate-700">{children}</div>
        </fieldset>
    );
}

export default function Join({ club, registration }) {
    const { flash } = usePage().props;
    const enabled = Boolean(registration?.enabled);
    const [clientError, setClientError] = useState('');

    const form = useForm({
        type: 'full',
        amka: '',
        first_name: '',
        last_name: '',
        birth_date: '',
        gender: '',
        email: '',
        phone: '',
        father_name: '',
        mother_name: '',
        father_email: '',
        mother_email: '',
        father_phone: '',
        mother_phone: '',
        address: '',
        postal_code: '',
        city: '',
        county: '',
        sport: '',
        uniform_size: '',
        clothing_package: '',
        istos: false,
        preferred_payment: '',
        comments: '',
        health_declaration: '',
        medical_responsibility: '',
        photo_consent: '',
        gdpr_accepted: false,
        parent_signature: '',
    });

    if (!enabled) {
        return (
            <PublicClubMessage
                title={club.name}
                message="Η δημόσια εγγραφή δεν είναι ενεργή αυτή τη στιγμή."
            />
        );
    }

    const field = (label, children, span2 = false) => (
        <label className={`block text-sm ${span2 ? 'sm:col-span-2' : ''}`}>
            <span className="text-slate-600">{label}</span>
            <div className="mt-1">{children}</div>
        </label>
    );

    const inputClass =
        'w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900';

    const hero = registration?.heroImageUrl || club.logo_url;
    const typeOptions = [
        { value: 'full', label: 'Πλήρης εγγραφή' },
        ...(registration?.allowTrial ? [{ value: 'trial', label: 'Δοκιμαστική προπόνηση' }] : []),
        ...(registration?.allowWaitlist ? [{ value: 'waitlist', label: 'Λίστα αναμονής' }] : []),
    ];

    return (
        <div className="min-h-screen bg-slate-100 px-4 py-8 text-slate-900">
            <Head title={`Εγγραφή — ${club.name}`} />
            <div className="mx-auto w-full max-w-2xl space-y-4">
                <header className="overflow-hidden rounded-2xl bg-slate-500 text-white shadow-md">
                    {hero ? (
                        <img
                            src={hero}
                            alt=""
                            className="max-h-72 w-full object-contain object-center bg-slate-600"
                        />
                    ) : (
                        <div className="flex h-40 items-center justify-center bg-slate-600 text-5xl font-semibold">
                            {club.name.slice(0, 1)}
                        </div>
                    )}
                    <div className="px-6 py-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-200">
                            Δημόσια εγγραφή
                        </p>
                        <h1 className="text-2xl font-semibold">{club.name}</h1>
                        {club.city ? <p className="text-sm text-slate-200">{club.city}</p> : null}
                    </div>
                </header>

                <div className="rounded-2xl border border-slate-200/80 bg-white px-6 py-6 shadow-sm">
                    <h2 className="text-lg font-semibold">Φόρμα εγγραφής αθλητή</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Συμπληρώστε τα στοιχεία. Η αίτηση εγκρίνεται από το προσωπικό του συλλόγου.
                    </p>

                    {flash?.success && (
                        <div className="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        className="mt-6 space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setClientError('');
                            if (!form.data.gdpr_accepted) {
                                setClientError(GDPR_REQUIRED_MSG);
                                return;
                            }
                            form.transform((data) => ({
                                ...data,
                                health_declaration: data.health_declaration === 'allow',
                                medical_responsibility: data.medical_responsibility === 'accept',
                                photo_consent: data.photo_consent === 'consent',
                                gdpr_accepted: true,
                            }));
                            form.post(route('public.join.store', club.slug));
                        }}
                    >
                        {typeOptions.length > 1 ? (
                            <select
                                className={inputClass}
                                value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}
                            >
                                {typeOptions.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </select>
                        ) : null}

                        <div className="grid gap-3 sm:grid-cols-2">
                            {field(
                                'ΑΜΚΑ',
                                <input
                                    className={inputClass}
                                    value={form.data.amka}
                                    onChange={(e) => form.setData('amka', e.target.value)}
                                />,
                            )}
                            {field(
                                'Ημερομηνία γέννησης',
                                <input
                                    type="date"
                                    className={inputClass}
                                    value={form.data.birth_date}
                                    onChange={(e) => form.setData('birth_date', e.target.value)}
                                />,
                            )}
                            {field(
                                'Όνομα',
                                <input
                                    className={inputClass}
                                    required
                                    value={form.data.first_name}
                                    onChange={(e) => form.setData('first_name', e.target.value)}
                                />,
                            )}
                            {field(
                                'Επώνυμο',
                                <input
                                    className={inputClass}
                                    required
                                    value={form.data.last_name}
                                    onChange={(e) => form.setData('last_name', e.target.value)}
                                />,
                            )}
                            {field(
                                'Φύλο',
                                <select
                                    className={inputClass}
                                    value={form.data.gender}
                                    onChange={(e) => form.setData('gender', e.target.value)}
                                >
                                    <option value="">—</option>
                                    <option value="male">Αγόρι</option>
                                    <option value="female">Κορίτσι</option>
                                </select>,
                            )}
                            {field(
                                'Email αθλητή',
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />,
                            )}
                            {field(
                                'Τηλέφωνο αθλητή',
                                <input
                                    className={inputClass}
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                />,
                            )}
                            {field(
                                'Πατρώνυμο',
                                <input
                                    className={inputClass}
                                    value={form.data.father_name}
                                    onChange={(e) => form.setData('father_name', e.target.value)}
                                />,
                            )}
                            {field(
                                'Μητρώνυμο',
                                <input
                                    className={inputClass}
                                    value={form.data.mother_name}
                                    onChange={(e) => form.setData('mother_name', e.target.value)}
                                />,
                            )}
                            {field(
                                'Email πατέρα',
                                <input
                                    className={inputClass}
                                    value={form.data.father_email}
                                    onChange={(e) => form.setData('father_email', e.target.value)}
                                />,
                            )}
                            {field(
                                'Email μητέρας',
                                <input
                                    className={inputClass}
                                    value={form.data.mother_email}
                                    onChange={(e) => form.setData('mother_email', e.target.value)}
                                />,
                            )}
                            {field(
                                'Τηλέφωνο πατέρα',
                                <input
                                    className={inputClass}
                                    value={form.data.father_phone}
                                    onChange={(e) => form.setData('father_phone', e.target.value)}
                                />,
                            )}
                            {field(
                                'Τηλέφωνο μητέρας',
                                <input
                                    className={inputClass}
                                    value={form.data.mother_phone}
                                    onChange={(e) => form.setData('mother_phone', e.target.value)}
                                />,
                            )}
                            {field(
                                'Διεύθυνση',
                                <input
                                    className={inputClass}
                                    value={form.data.address}
                                    onChange={(e) => form.setData('address', e.target.value)}
                                />,
                            )}
                            {field(
                                'Τ.Κ.',
                                <input
                                    className={inputClass}
                                    value={form.data.postal_code}
                                    onChange={(e) => form.setData('postal_code', e.target.value)}
                                />,
                            )}
                            {field(
                                'Πόλη',
                                <input
                                    className={inputClass}
                                    value={form.data.city}
                                    onChange={(e) => form.setData('city', e.target.value)}
                                />,
                            )}
                            {field(
                                'Νομός',
                                <input
                                    className={inputClass}
                                    value={form.data.county}
                                    onChange={(e) => form.setData('county', e.target.value)}
                                />,
                            )}
                            {field(
                                'Άθλημα',
                                <input
                                    className={inputClass}
                                    value={form.data.sport}
                                    onChange={(e) => form.setData('sport', e.target.value)}
                                />,
                            )}
                            {field(
                                'Μέγεθος στολής',
                                <input
                                    className={inputClass}
                                    value={form.data.uniform_size}
                                    onChange={(e) => form.setData('uniform_size', e.target.value)}
                                />,
                            )}
                            {field(
                                'Πακέτο ρουχισμού',
                                <input
                                    className={inputClass}
                                    value={form.data.clothing_package}
                                    onChange={(e) => form.setData('clothing_package', e.target.value)}
                                />,
                            )}
                            {field(
                                'Προτιμώμενη πληρωμή',
                                <input
                                    className={inputClass}
                                    value={form.data.preferred_payment}
                                    onChange={(e) => form.setData('preferred_payment', e.target.value)}
                                />,
                            )}
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.istos}
                                onChange={(e) => form.setData('istos', e.target.checked)}
                            />
                            Συμμετοχή ΙΣΤΟΣ
                        </label>

                        {field(
                            'Σχόλια',
                            <textarea
                                className={inputClass}
                                rows={3}
                                value={form.data.comments}
                                onChange={(e) => form.setData('comments', e.target.value)}
                            />,
                            true,
                        )}

                        <div className="space-y-4">
                            <ChoiceCard title="Υπεύθυνη δήλωση υγείας">
                                <p>
                                    Με την υποβολή της παρούσας αίτησης, ο υπογράφων γονέας/κηδεμόνας
                                    δηλώνω υπεύθυνα ότι:
                                </p>
                                <p>
                                    Ο συμμετέχων/ουσα είναι <strong>υγιής</strong> και του/της
                                    επιτρέπεται η πλήρης συμμετοχή σε αθλητικές δραστηριότητες.
                                </p>
                                {HEALTH_OPTIONS.map((option) => (
                                    <label key={option.value} className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="health_declaration"
                                            checked={form.data.health_declaration === option.value}
                                            onChange={() =>
                                                form.setData('health_declaration', option.value)
                                            }
                                            required
                                        />
                                        <span className="font-medium">{option.label}</span>
                                    </label>
                                ))}
                            </ChoiceCard>

                            <ChoiceCard title="Ευθύνη και ιατρική περίθαλψη">
                                <p>
                                    Με την υποβολή της παρούσας αίτησης, ο υπογράφων γονέας/κηδεμόνας
                                    δηλώνω υπεύθυνα ότι:
                                </p>
                                <p>
                                    Ο Σύλλογος και οι προπονητές <strong>δεν φέρουν ευθύνη</strong> για
                                    τυχόν τραυματισμό που μπορεί να προκύψει κατά τη διάρκεια της
                                    προπόνησης, ο οποίος οφείλεται σε τυχαίο γεγονός ή σε μη συμμόρφωση
                                    του αθλητή / της αθλήτριας με τις υποδείξεις των υπευθύνων.
                                </p>
                                <p>
                                    Σε περίπτωση έκτακτου ιατρικού περιστατικού, παρέχω την άδεια στους
                                    υπεύθυνους να προβούν στις απαραίτητες ενέργειες για την παροχή
                                    πρώτων βοηθειών ή τη μεταφορά σε νοσοκομείο, εάν κριθεί απαραίτητο.
                                </p>
                                {LIABILITY_OPTIONS.map((option) => (
                                    <label key={option.value} className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="medical_responsibility"
                                            checked={
                                                form.data.medical_responsibility === option.value
                                            }
                                            onChange={() =>
                                                form.setData('medical_responsibility', option.value)
                                            }
                                            required
                                        />
                                        <span className="font-medium">{option.label}</span>
                                    </label>
                                ))}
                            </ChoiceCard>

                            <ChoiceCard title="Φωτογράφιση / βιντεοσκόπηση">
                                <p>
                                    Συναινώ στη φωτογράφιση ή βιντεοσκόπηση του ΑΘΛΗΤΗ / ΑΘΛΗΤΡΙΑΣ κατά
                                    τη διάρκεια της προπονητικής περιόδου, με σκοπό την προβολή στα
                                    social media και την ιστοσελίδα του {club.name}.
                                </p>
                                {MEDIA_OPTIONS.map((option) => (
                                    <label key={option.value} className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="photo_consent"
                                            checked={form.data.photo_consent === option.value}
                                            onChange={() =>
                                                form.setData('photo_consent', option.value)
                                            }
                                            required
                                        />
                                        <span className="font-medium">{option.label}</span>
                                    </label>
                                ))}
                            </ChoiceCard>

                            <ChoiceCard title="Προστασία προσωπικών δεδομένων (GDPR)">
                                <p>
                                    Τα προσωπικά δεδομένα που συλλέγονται μέσω αυτής της φόρμας (ΑΜΚΑ,
                                    ονοματεπώνυμο, τηλέφωνα επικοινωνίας, ιατρικές πληροφορίες) θα
                                    χρησιμοποιηθούν αποκλειστικά για τις ανάγκες λειτουργίας και
                                    επικοινωνίας του {club.name} και την ασφάλεια των συμμετεχόντων. Ο
                                    Σύλλογος δεσμεύεται για την εμπιστευτικότητα των στοιχείων και τη μη
                                    κοινοποίησή τους σε τρίτους, σύμφωνα με τον Γενικό Κανονισμό
                                    Προστασίας Δεδομένων (ΕΕ 2016/679).
                                </p>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.gdpr_accepted}
                                        onChange={(e) => {
                                            form.setData('gdpr_accepted', e.target.checked);
                                            if (e.target.checked) {
                                                setClientError('');
                                            }
                                        }}
                                        required
                                    />
                                    <span className="font-semibold tracking-wide">ΕΛΑΒΑ ΓΝΩΣΗ</span>
                                </label>
                            </ChoiceCard>

                            {field(
                                'Υπογραφή γονέα / κηδεμόνα',
                                <input
                                    className={inputClass}
                                    placeholder="Ονοματεπώνυμο υπογράφοντος"
                                    value={form.data.parent_signature}
                                    onChange={(e) => form.setData('parent_signature', e.target.value)}
                                />,
                            )}
                        </div>

                        {(clientError || form.errors.gdpr_accepted) && (
                            <p className="text-sm text-rose-600">
                                {clientError || form.errors.gdpr_accepted}
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full rounded-md bg-sky-600 py-2.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-60"
                        >
                            {form.processing ? 'Υποβολή…' : 'Υποβολή αίτησης'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
