import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const btnPrimary =
    'rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-60';
const btnSecondary =
    'rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-surface-2';
const btnGhost = 'rounded-md px-3 py-1.5 text-sm text-ts-muted hover:text-ts-fg';
const card = 'rounded-lg border border-ts-border bg-ts-surface p-4 space-y-3';

function postWizard(payload) {
    router.post(route('admin.setup.wizard.update'), payload, { preserveScroll: true });
}

export default function Wizard({
    progress,
    club,
    publicJoin,
    payments,
    sports = [],
    seasons = [],
    activeSeasonId = null,
    sessionEmail = '',
}) {
    const flash = usePage().props.flash;
    const [step, setStep] = useState(progress?.nextStep || 'profile');

    useEffect(() => {
        if (progress?.nextStep) {
            setStep(progress.nextStep);
        }
    }, [progress?.nextStep]);

    const pct = Math.round(((progress?.doneCount || 0) / (progress?.total || 1)) * 100);
    const steps = progress?.steps || [];
    const done = progress?.done || {};

    const profileForm = useForm({
        action: 'profile',
        name: club?.name || '',
        address: club?.profile?.address || '',
        phone: club?.profile?.phone || '',
        email: club?.profile?.email || '',
    });

    const classForm = useForm({
        action: 'classes',
        class_name: '',
        sport: '',
        club_season_id: activeSeasonId || '',
        attendance_required: true,
    });

    const joinForm = useForm({
        action: 'publicJoin',
        join_enabled: Boolean(publicJoin?.enabled),
        accept_dpa: Boolean(publicJoin?.dpa_accepted),
    });

    const payForm = useForm({
        action: 'payments',
        provider: 'viva',
        enabled: true,
        clientId: '',
        clientSecret: '',
        merchantId: '',
        sourceCode: '',
        demo: true,
        publishableKey: '',
        secretKey: '',
        secret: '',
    });

    const secForm = useForm({
        action: 'secretariat',
        last_name: '',
        first_name: '',
        sec_email: '',
        password: '',
    });

    return (
        <AdminLayout title="Οδηγός πρώτης εβδομάδας">
            <Head title="Οδηγός πρώτης εβδομάδας" />

            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {flash.error}
                </div>
            )}

            <section className={card} aria-labelledby="club-setup-title">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 id="club-setup-title" className="text-xl font-semibold text-ts-fg">
                            Οδηγός πρώτης εβδομάδας
                        </h1>
                        <p className="mt-1 text-sm text-ts-muted">
                            Πέντε βήματα για να ξεκινήσει ο σύλλογος. Τα υπόλοιπα (email, SMS, GDPR, backup)
                            μένουν στις προχωρημένες ρυθμίσεις.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <strong className="text-sm text-ts-fg">
                            {progress?.doneCount || 0}/{progress?.total || 5}
                        </strong>
                        <button
                            type="button"
                            className={btnGhost}
                            onClick={() => postWizard({ action: 'dismiss' })}
                        >
                            Απόκρυψη
                        </button>
                    </div>
                </header>

                <div
                    className="h-2 overflow-hidden rounded-full bg-ts-border"
                    role="progressbar"
                    aria-valuenow={progress?.doneCount || 0}
                    aria-valuemin={0}
                    aria-valuemax={progress?.total || 5}
                >
                    <div className="h-full bg-ts-accent transition-all" style={{ width: `${pct}%` }} />
                </div>

                <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    {steps.map((item, index) => {
                        const isDone = Boolean(done[item.id]);
                        const isActive = step === item.id;
                        return (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    onClick={() => setStep(item.id)}
                                    className={`w-full rounded-md border px-3 py-2 text-left transition ${
                                        isActive
                                            ? 'border-ts-accent bg-sky-50'
                                            : isDone
                                              ? 'border-emerald-200 bg-emerald-50/60'
                                              : 'border-ts-border bg-ts-surface'
                                    }`}
                                >
                                    <strong className="block text-sm text-ts-fg">
                                        {isDone ? '✓ ' : ''}
                                        {index + 1}. {item.label}
                                    </strong>
                                    <em className="mt-0.5 block text-xs not-italic text-ts-muted">{item.hint}</em>
                                </button>
                            </li>
                        );
                    })}
                </ol>

                <div className="border-t border-ts-border pt-3 space-y-3">
                    {step === 'profile' && (
                        <>
                            <p className="text-sm text-ts-muted">
                                Συμπληρώστε τα βασικά στοιχεία που φαίνονται σε αποδείξεις και στη δημόσια φόρμα.
                            </p>
                            <form
                                className="grid gap-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    profileForm.post(route('admin.setup.wizard.update'), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <label className="block space-y-1 sm:col-span-2">
                                    <span className="text-xs text-ts-muted">Όνομα συλλόγου</span>
                                    <input
                                        className={field}
                                        value={profileForm.data.name}
                                        onChange={(e) => profileForm.setData('name', e.target.value)}
                                    />
                                    {profileForm.errors.name && (
                                        <p className="text-xs text-rose-600">{profileForm.errors.name}</p>
                                    )}
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Έδρα / πόλη</span>
                                    <input
                                        className={field}
                                        value={profileForm.data.address}
                                        onChange={(e) => profileForm.setData('address', e.target.value)}
                                    />
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Τηλέφωνο</span>
                                    <input
                                        className={field}
                                        value={profileForm.data.phone}
                                        onChange={(e) => profileForm.setData('phone', e.target.value)}
                                    />
                                </label>
                                <label className="block space-y-1 sm:col-span-2">
                                    <span className="text-xs text-ts-muted">Email</span>
                                    <input
                                        type="email"
                                        className={field}
                                        value={profileForm.data.email}
                                        onChange={(e) => profileForm.setData('email', e.target.value)}
                                    />
                                </label>
                                <div className="flex flex-wrap gap-2 sm:col-span-2">
                                    <button
                                        type="submit"
                                        className={btnPrimary}
                                        disabled={profileForm.processing}
                                    >
                                        {profileForm.processing ? 'Αποθήκευση…' : 'Αποθήκευση και συνέχεια'}
                                    </button>
                                    <Link
                                        href={route('admin.settings.index', { tab: 'club' })}
                                        className={btnSecondary}
                                    >
                                        Πλήρες προφίλ / λογότυπο
                                    </Link>
                                </div>
                            </form>
                        </>
                    )}

                    {step === 'classes' && (
                        <>
                            <p className="text-sm text-ts-muted">
                                {done.classes
                                    ? 'Υπάρχει ήδη τουλάχιστον ένα τμήμα. Μπορείτε να προσθέσετε κι άλλα από Τμήματα.'
                                    : 'Ένα τμήμα αρκεί για να δεχτείτε αθλητές και δημόσιες αιτήσεις.'}
                            </p>
                            <form
                                className="grid gap-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    classForm.post(route('admin.setup.wizard.update'), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <label className="block space-y-1 sm:col-span-2">
                                    <span className="text-xs text-ts-muted">Όνομα τμήματος</span>
                                    <input
                                        className={field}
                                        placeholder="π.χ. Ακαδημία U10"
                                        value={classForm.data.class_name}
                                        onChange={(e) => classForm.setData('class_name', e.target.value)}
                                    />
                                    {classForm.errors.class_name && (
                                        <p className="text-xs text-rose-600">{classForm.errors.class_name}</p>
                                    )}
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Άθλημα</span>
                                    <select
                                        className={field}
                                        value={classForm.data.sport}
                                        onChange={(e) => classForm.setData('sport', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        {sports.map((s) => (
                                            <option key={s.id} value={s.name}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Παρουσιολόγιο</span>
                                    <select
                                        className={field}
                                        value={classForm.data.attendance_required ? 'yes' : 'no'}
                                        onChange={(e) =>
                                            classForm.setData(
                                                'attendance_required',
                                                e.target.value !== 'no',
                                            )
                                        }
                                    >
                                        <option value="yes">Απαιτείται</option>
                                        <option value="no">Δεν απαιτείται (π.χ. ανδρική ομάδα)</option>
                                    </select>
                                </label>
                                <label className="block space-y-1 sm:col-span-2">
                                    <span className="text-xs text-ts-muted">Σεζόν</span>
                                    <select
                                        className={field}
                                        value={classForm.data.club_season_id || ''}
                                        onChange={(e) =>
                                            classForm.setData('club_season_id', e.target.value)
                                        }
                                    >
                                        <option value="">—</option>
                                        {seasons.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="flex flex-wrap gap-2 sm:col-span-2">
                                    <button
                                        type="submit"
                                        className={btnPrimary}
                                        disabled={classForm.processing}
                                    >
                                        {classForm.processing ? 'Αποθήκευση…' : 'Δημιουργία τμήματος'}
                                    </button>
                                    <button
                                        type="button"
                                        className={btnGhost}
                                        onClick={() => postWizard({ action: 'skip', step: 'classes' })}
                                    >
                                        Παράλειψη
                                    </button>
                                </div>
                            </form>
                        </>
                    )}

                    {step === 'publicJoin' && (
                        <>
                            <p className="text-sm text-ts-muted">
                                Οι γονείς συμπληρώνουν φόρμα στο <code className="text-ts-fg">/join/…</code>.
                                Η αίτηση έρχεται για έγκριση, όχι απευθείας στο μητρώο.
                            </p>
                            {publicJoin?.join_url ? (
                                <p className="break-all text-xs text-ts-muted">{publicJoin.join_url}</p>
                            ) : null}
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    joinForm.post(route('admin.setup.wizard.update'), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <label className="flex items-center gap-2 text-sm text-ts-fg">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(joinForm.data.join_enabled)}
                                        onChange={(e) =>
                                            joinForm.setData('join_enabled', e.target.checked)
                                        }
                                    />
                                    Ενεργοποίηση δημόσιας εγγραφής
                                </label>
                                <label className="flex items-center gap-2 text-sm text-ts-fg">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(joinForm.data.accept_dpa)}
                                        onChange={(e) =>
                                            joinForm.setData('accept_dpa', e.target.checked)
                                        }
                                        disabled={Boolean(publicJoin?.dpa_accepted)}
                                    />
                                    Αποδέχομαι τη συμφωνία επεξεργασίας (DPA) για τη δημόσια φόρμα
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="submit"
                                        className={btnPrimary}
                                        disabled={joinForm.processing}
                                    >
                                        {joinForm.processing ? 'Αποθήκευση…' : 'Αποθήκευση φόρμας'}
                                    </button>
                                    <Link
                                        href={route('admin.settings.index', {
                                            tab: 'publicRegistration',
                                        })}
                                        className={btnSecondary}
                                    >
                                        Πλήρεις επιλογές / QR
                                    </Link>
                                    <button
                                        type="button"
                                        className={btnGhost}
                                        onClick={() =>
                                            postWizard({ action: 'skip', step: 'publicJoin' })
                                        }
                                    >
                                        Αργότερα
                                    </button>
                                </div>
                            </form>
                        </>
                    )}

                    {step === 'payments' && (
                        <>
                            <p className="text-sm text-ts-muted">
                                Αν οι γονείς πληρώνουν online, συμπληρώστε έναν πάροχο. Αν εισπράττετε μόνο
                                μετρητά / κατάθεση, παραλείψτε το βήμα.
                            </p>
                            {payments?.configured ? (
                                <p className="text-sm text-emerald-700">
                                    Υπάρχει ήδη ρυθμισμένος online πάροχος.
                                </p>
                            ) : null}
                            <form
                                className="grid gap-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    payForm.post(route('admin.setup.wizard.update'), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Πάροχος</span>
                                    <select
                                        className={field}
                                        value={payForm.data.provider}
                                        onChange={(e) => payForm.setData('provider', e.target.value)}
                                    >
                                        <option value="viva">Viva</option>
                                        <option value="stripe">Stripe</option>
                                        <option value="eurobank">Eurobank</option>
                                    </select>
                                </label>
                                <label className="flex items-end gap-2 pb-1 text-sm text-ts-fg">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(payForm.data.enabled)}
                                        onChange={(e) => payForm.setData('enabled', e.target.checked)}
                                    />
                                    Ενεργό
                                </label>
                                {payForm.data.provider === 'viva' && (
                                    <>
                                        <label className="block space-y-1">
                                            <span className="text-xs text-ts-muted">Client ID</span>
                                            <input
                                                className={field}
                                                value={payForm.data.clientId}
                                                onChange={(e) =>
                                                    payForm.setData('clientId', e.target.value)
                                                }
                                            />
                                        </label>
                                        <label className="block space-y-1">
                                            <span className="text-xs text-ts-muted">Merchant ID</span>
                                            <input
                                                className={field}
                                                value={payForm.data.merchantId}
                                                onChange={(e) =>
                                                    payForm.setData('merchantId', e.target.value)
                                                }
                                            />
                                        </label>
                                    </>
                                )}
                                {payForm.data.provider === 'stripe' && (
                                    <label className="block space-y-1 sm:col-span-2">
                                        <span className="text-xs text-ts-muted">Publishable key</span>
                                        <input
                                            className={field}
                                            value={payForm.data.publishableKey}
                                            onChange={(e) =>
                                                payForm.setData('publishableKey', e.target.value)
                                            }
                                        />
                                    </label>
                                )}
                                {payForm.data.provider === 'eurobank' && (
                                    <label className="block space-y-1 sm:col-span-2">
                                        <span className="text-xs text-ts-muted">Merchant ID</span>
                                        <input
                                            className={field}
                                            value={payForm.data.merchantId}
                                            onChange={(e) =>
                                                payForm.setData('merchantId', e.target.value)
                                            }
                                        />
                                    </label>
                                )}
                                <div className="flex flex-wrap gap-2 sm:col-span-2">
                                    <button
                                        type="submit"
                                        className={btnPrimary}
                                        disabled={payForm.processing}
                                    >
                                        {payForm.processing ? 'Αποθήκευση…' : 'Αποθήκευση παρόχου'}
                                    </button>
                                    <Link
                                        href={route('admin.settings.index', { tab: 'payments' })}
                                        className={btnSecondary}
                                    >
                                        Καρτέλα πληρωμών
                                    </Link>
                                    <button
                                        type="button"
                                        className={btnGhost}
                                        onClick={() =>
                                            postWizard({ action: 'skip', step: 'payments' })
                                        }
                                    >
                                        Μόνο μετρητά — παράλειψη
                                    </button>
                                </div>
                            </form>
                        </>
                    )}

                    {step === 'secretariat' && (
                        <>
                            <p className="text-sm text-ts-muted">
                                Ο λογαριασμός σας ({sessionEmail || '—'}) είναι διαχειριστής. Προσθέστε
                                γραμματεία για καθημερινές εγγραφές χωρίς πλήρη δικαιώματα.
                            </p>
                            <form
                                className="grid gap-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    secForm.post(route('admin.setup.wizard.update'), {
                                        preserveScroll: true,
                                        onSuccess: () => secForm.reset('password'),
                                    });
                                }}
                            >
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Επώνυμο</span>
                                    <input
                                        className={field}
                                        value={secForm.data.last_name}
                                        onChange={(e) => secForm.setData('last_name', e.target.value)}
                                    />
                                    {secForm.errors.last_name && (
                                        <p className="text-xs text-rose-600">{secForm.errors.last_name}</p>
                                    )}
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Όνομα</span>
                                    <input
                                        className={field}
                                        value={secForm.data.first_name}
                                        onChange={(e) => secForm.setData('first_name', e.target.value)}
                                    />
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Email εισόδου</span>
                                    <input
                                        type="email"
                                        className={field}
                                        value={secForm.data.sec_email}
                                        onChange={(e) => secForm.setData('sec_email', e.target.value)}
                                    />
                                    {secForm.errors.sec_email && (
                                        <p className="text-xs text-rose-600">{secForm.errors.sec_email}</p>
                                    )}
                                </label>
                                <label className="block space-y-1">
                                    <span className="text-xs text-ts-muted">Κωδικός (τουλάχιστον 6)</span>
                                    <input
                                        type="password"
                                        autoComplete="new-password"
                                        className={field}
                                        value={secForm.data.password}
                                        onChange={(e) => secForm.setData('password', e.target.value)}
                                    />
                                    {secForm.errors.password && (
                                        <p className="text-xs text-rose-600">{secForm.errors.password}</p>
                                    )}
                                </label>
                                <div className="flex flex-wrap gap-2 sm:col-span-2">
                                    <button
                                        type="submit"
                                        className={btnPrimary}
                                        disabled={secForm.processing}
                                    >
                                        {secForm.processing ? 'Αποθήκευση…' : 'Δημιουργία γραμματείας'}
                                    </button>
                                    <Link
                                        href={route('admin.settings.index', { tab: 'users' })}
                                        className={btnSecondary}
                                    >
                                        Όλοι οι χρήστες
                                    </Link>
                                    <button
                                        type="button"
                                        className={btnGhost}
                                        onClick={() =>
                                            postWizard({ action: 'skip', step: 'secretariat' })
                                        }
                                    >
                                        Θα το κάνω αργότερα
                                    </button>
                                </div>
                            </form>
                        </>
                    )}
                </div>

                {progress?.complete ? (
                    <p className="text-sm text-emerald-700">
                        Η πρώτη εβδομάδα ολοκληρώθηκε. Μπορείτε να αποκρύψετε τον οδηγό.
                    </p>
                ) : null}
            </section>
        </AdminLayout>
    );
}
