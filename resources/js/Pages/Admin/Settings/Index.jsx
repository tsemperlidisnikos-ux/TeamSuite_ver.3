import AdminLayout from '@/Layouts/AdminLayout';
import ClubUsersTab from '@/Pages/Admin/Settings/ClubUsersTab';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const PRIMARY_TABS = [
    { id: 'club', label: 'Σύλλογος' },
    { id: 'users', label: 'Χρήστες' },
    { id: 'publicRegistration', label: 'Δημόσια εγγραφή' },
    { id: 'payments', label: 'Πληρωμές' },
];

const MORE_TABS = [
    { id: 'facilities', label: 'Γήπεδο' },
    { id: 'email', label: 'Email' },
    { id: 'sms', label: 'SMS' },
    { id: 'password', label: 'Κωδικός' },
    { id: 'associations', label: 'Σωματείο' },
    { id: 'sports', label: 'Άθλημα' },
    { id: 'seasons', label: 'Σεζόν' },
    { id: 'sizes', label: 'Μεγεθολόγιο' },
    { id: 'clothing', label: 'Πακέτο ρουχισμού' },
    { id: 'discounts', label: 'Λόγοι έκπτωσης' },
    { id: 'receipts', label: 'Αποδείξεις' },
    { id: 'terms', label: 'Όροι' },
    { id: 'amka', label: 'GDPR' },
    { id: 'backup', label: 'Αντίγραφο ασφαλείας' },
];

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const btnPrimary = 'rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover';
const btnDanger = 'text-rose-400 text-sm';
const card = 'rounded-lg border border-ts-border bg-ts-surface p-4 space-y-3';

function flattenSizes(chart) {
    if (!chart) return [];
    if (Array.isArray(chart)) return chart;
    return [...(chart.kids || []), ...(chart.men || []), ...(chart.women || [])];
}

export default function Index({
    tab,
    club,
    settings,
    users,
    roles,
    permissionModules,
    roleDefaultPermissions,
    coaches,
    sports,
    facilities,
    seasons,
    associations,
    sizeChart,
    clothingPackages,
    discountReasons,
}) {
    const flash = usePage().props.flash;
    const [advancedOpen, setAdvancedOpen] = useState(
        MORE_TABS.some((t) => t.id === tab),
    );

    const setTab = (id) => {
        if (id === 'backup') {
            router.get(route('admin.settings.backup.index'));
            return;
        }
        if (id === 'receipts') {
            router.get(route('admin.receipts.index'));
            return;
        }
        router.get(route('admin.settings.index'), { tab: id }, { preserveState: true, replace: true });
        if (MORE_TABS.some((t) => t.id === id)) setAdvancedOpen(true);
    };

    // Keep the File outside useForm — Inertia cloneDeep() strips File objects when other fields change.
    const clubLogoFileRef = useRef(null);
    const [clubLogoFileName, setClubLogoFileName] = useState('');
    const [clubLogoPreviewUrl, setClubLogoPreviewUrl] = useState(null);

    useEffect(() => {
        return () => {
            if (clubLogoPreviewUrl) {
                URL.revokeObjectURL(clubLogoPreviewUrl);
            }
        };
    }, [clubLogoPreviewUrl]);

    const clubForm = useForm({
        name: club?.name || '',
        vat_number: club?.profile?.vat_number || '',
        tax_office: club?.profile?.tax_office || '',
        address: club?.profile?.address || '',
        founded_year: club?.profile?.founded_year || '',
        website: club?.profile?.website || '',
        phone: club?.profile?.phone || '',
        email: club?.profile?.email || '',
        custom_charge_label: club?.profile?.custom_charge_label || '',
        remove_logo: false,
    });

    const clearClubLogoSelection = () => {
        clubLogoFileRef.current = null;
        setClubLogoFileName('');
        setClubLogoPreviewUrl((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }
            return null;
        });
    };

    const onClubLogoSelected = (file) => {
        clubLogoFileRef.current = file || null;
        setClubLogoFileName(file?.name || '');
        setClubLogoPreviewUrl((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }
            return file ? URL.createObjectURL(file) : null;
        });
        clubForm.clearErrors('logo');
    };

    const emailForm = useForm({
        enabled: Boolean(settings?.smtp?.enabled),
        provider: settings?.smtp?.provider || 'gmail',
        host: settings?.smtp?.host || 'smtp.gmail.com',
        port: settings?.smtp?.port || '587',
        username: settings?.smtp?.username || '',
        password: '',
        fromName: settings?.smtp?.fromName || '',
        fromEmail: settings?.smtp?.fromEmail || '',
        security: settings?.smtp?.security || 'starttls',
        requireAuth: settings?.smtp?.requireAuth ?? true,
    });

    const [testEmailTo, setTestEmailTo] = useState('');
    const [testEmailProcessing, setTestEmailProcessing] = useState(false);
    const [testEmailError, setTestEmailError] = useState('');

    const smsForm = useForm({
        enabled: Boolean(settings?.sms?.enabled),
        provider: settings?.sms?.provider || 'sms.to',
        apiKey: '',
        sender: settings?.sms?.sender || '',
        httpUrl: settings?.sms?.httpUrl || '',
    });

    const vivaForm = useForm({
        provider: 'viva',
        enabled: Boolean(settings?.viva?.enabled),
        clientId: settings?.viva?.clientId || '',
        clientSecret: '',
        merchantId: settings?.viva?.merchantId || '',
        sourceCode: settings?.viva?.sourceCode || '',
        demo: settings?.viva?.demo ?? true,
    });

    const stripeForm = useForm({
        provider: 'stripe',
        enabled: Boolean(settings?.stripe?.enabled),
        publishableKey: settings?.stripe?.publishableKey || '',
        secretKey: '',
    });

    const eurobankForm = useForm({
        provider: 'eurobank',
        enabled: Boolean(settings?.eurobank?.enabled),
        merchantId: settings?.eurobank?.merchantId || '',
        secret: '',
        demo: settings?.eurobank?.demo ?? true,
    });

    const publicForm = useForm({
        enabled: Boolean(settings?.public_registration?.enabled ?? settings?.public_join_enabled),
        slug: settings?.public_registration?.slug || club?.slug || '',
        notifyEmail: settings?.public_registration?.notifyEmail || '',
        allowTrial: Boolean(settings?.public_registration?.allowTrial),
        allowWaitlist: Boolean(settings?.public_registration?.allowWaitlist),
        public_rent_enabled: Boolean(settings?.public_rent_enabled),
    });

    const termsForm = useForm({
        terms_of_use_html: settings?.terms_of_use_html || '',
    });

    const gdprForm = useForm({
        dpa_html: settings?.dpa_html || '',
        retention_policy_html: settings?.retention_policy_html || '',
        data_retention_months: settings?.data_retention_months || 24,
        accept_dpa: false,
    });

    const sportForm = useForm({ name: '' });
    const facilityForm = useForm({ name: '', address: '', is_rentable: false });
    const seasonForm = useForm({ name: '', starts_on: '', ends_on: '', is_active: true });
    const associationForm = useForm({
        name: '',
        city: '',
        phone: '',
        email: '',
        address: '',
        active: true,
    });
    const clothingForm = useForm({ name: '', price: '' });
    const discountForm = useForm({ name: '', sport: '', default_amount: '' });

    const [sizes, setSizes] = useState(() => flattenSizes(sizeChart));
    const [newSize, setNewSize] = useState('');

    useEffect(() => {
        setSizes(flattenSizes(sizeChart));
    }, [sizeChart]);

    const joinUrl = useMemo(() => {
        const slug = publicForm.data.slug || club?.slug || 'club';
        if (typeof window === 'undefined') return `/join/${slug}`;
        return `${window.location.origin}/join/${slug}`;
    }, [publicForm.data.slug, club?.slug]);

    return (
        <AdminLayout title="Ρυθμίσεις">
            <Head title="Ρυθμίσεις" />

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

            <h2 className="mb-1 text-xl font-semibold text-ts-fg">Ρυθμίσεις</h2>
            <p className="mb-3 text-sm text-ts-muted">
                Τα βασικά για να λειτουργήσει ο σύλλογος είναι εδώ. Email, SMS, GDPR και backup
                βρίσκονται στα προχωρημένα.
            </p>

            <nav className="mb-2 flex flex-wrap gap-4 border-b border-ts-border">
                {PRIMARY_TABS.map((t) => {
                    const active =
                        tab === t.id ||
                        (t.id === 'payments' && ['viva', 'stripe', 'eurobank'].includes(tab));
                    return (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTab(t.id)}
                            className={`-mb-px border-b-2 px-1 py-2 text-sm ${
                                active
                                    ? 'border-ts-accent font-medium text-ts-fg'
                                    : 'border-transparent text-ts-muted hover:text-ts-fg'
                            }`}
                        >
                            {t.label}
                        </button>
                    );
                })}
            </nav>

            <details
                className="mb-4 rounded-md border border-ts-border bg-ts-surface px-3 py-2"
                open={advancedOpen}
                onToggle={(e) => setAdvancedOpen(e.currentTarget.open)}
            >
                <summary className="cursor-pointer text-sm text-ts-fg-soft">Για προχωρημένους</summary>
                <div className="mt-2 flex flex-wrap gap-1">
                    {MORE_TABS.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTab(t.id)}
                            className={`rounded-md px-2.5 py-1 text-xs ${
                                tab === t.id
                                    ? 'bg-sky-900/40 text-sky-200'
                                    : 'text-ts-muted hover:text-ts-fg'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
            </details>

            {tab === 'club' && (
                <form
                    className="max-w-2xl space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        // Inertia useForm.transform() returns undefined — do not chain .post().
                        clubForm.transform((data) => {
                            const payload = {
                                ...data,
                                // FormData stringifies booleans as "true"/"false", which fail Laravel's boolean rule.
                                remove_logo: data.remove_logo ? 1 : 0,
                            };
                            if (clubLogoFileRef.current instanceof File) {
                                payload.logo = clubLogoFileRef.current;
                            }
                            return payload;
                        });
                        clubForm.post(route('admin.settings.club.update'), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => clearClubLogoSelection(),
                            onFinish: () => clubForm.transform((data) => data),
                        });
                    }}
                >
                    <div className={card}>
                        <h2 className="text-base font-semibold text-ts-fg">Συνδρομή &amp; άδειες αθλητών</h2>
                        <p className="text-sm text-ts-muted">
                            Όριο αδειών σύμφωνα με το πακέτο συνδρομής του συλλόγου.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-ts-border bg-ts-surface-2/40 p-3">
                                <div className="text-[11px] uppercase tracking-wide text-ts-muted">Πακέτο</div>
                                <div className="mt-1 font-semibold text-ts-fg">
                                    {club?.license?.name || 'Χωρίς πακέτο'}
                                </div>
                                <p className="mt-1 text-xs text-ts-muted">
                                    {club?.license
                                        ? `${club.license.athlete_cap} άδειες`
                                        : 'Το όριο ορίζεται από τον διαχειριστή πλατφόρμας.'}
                                </p>
                                <p className="text-xs text-ts-muted">Λογαριασμός χωρίς ημερομηνία λήξης</p>
                            </div>
                            <div className="rounded-lg border border-ts-border bg-ts-surface-2/40 p-3">
                                <div className="text-[11px] uppercase tracking-wide text-ts-muted">
                                    Χρήση αδειών
                                </div>
                                <div className="mt-1 font-semibold text-ts-fg">
                                    {club?.license_usage?.active ?? 0} /{' '}
                                    {club?.license_usage?.cap ?? '—'}
                                </div>
                                <p className="mt-1 text-xs text-ts-muted">
                                    {club?.license_usage?.percent != null
                                        ? `${club.license_usage.percent}% πληρότητα · ενεργοί αθλητές`
                                        : 'Δεν έχει οριστεί όριο αδειών'}
                                </p>
                            </div>
                        </div>
                        {club?.license_usage?.cap ? (
                            <div
                                className="mt-2 h-2 overflow-hidden rounded-full bg-ts-border"
                                role="progressbar"
                                aria-valuenow={club.license_usage.active}
                                aria-valuemin={0}
                                aria-valuemax={club.license_usage.cap}
                            >
                                <div
                                    className="h-full rounded-full bg-ts-accent"
                                    style={{ width: `${club.license_usage.percent ?? 0}%` }}
                                />
                            </div>
                        ) : null}
                    </div>

                    <div className={card}>
                        <h2 className="text-base font-semibold text-ts-fg">Λογότυπο Συλλόγου</h2>
                        <p className="text-sm text-ts-muted">
                            Ανεβάστε το λογότυπο του συλλόγου. Προτεινόμενη διάσταση: 512×512px.
                        </p>
                        {Object.keys(clubForm.errors).length > 0 && (
                            <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                {Object.values(clubForm.errors).flat().join(' ')}
                            </div>
                        )}
                        <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-ts-border px-4 py-8 text-center hover:bg-ts-hover/40">
                            {clubLogoPreviewUrl || club?.logo_url ? (
                                <img
                                    src={clubLogoPreviewUrl || club.logo_url}
                                    alt=""
                                    className="mb-3 h-24 w-24 rounded object-contain"
                                />
                            ) : (
                                <span className="mb-2 text-2xl text-ts-muted">+</span>
                            )}
                            {clubLogoFileName ? (
                                <span className="mb-2 text-sm font-medium text-ts-accent">
                                    {clubLogoFileName}
                                </span>
                            ) : null}
                            <span className="text-sm text-ts-fg-soft">
                                Κάντε κλικ για επιλογή αρχείου ή σύρετε το αρχείο εδώ
                            </span>
                            <span className="mt-1 text-xs text-ts-muted">PNG, JPG ή WEBP (μέγ. 2MB)</span>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                className="sr-only"
                                onChange={(e) => onClubLogoSelected(e.target.files?.[0] || null)}
                            />
                        </label>
                        {club?.logo_url && (
                            <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                <input
                                    type="checkbox"
                                    checked={clubForm.data.remove_logo}
                                    onChange={(e) => clubForm.setData('remove_logo', e.target.checked)}
                                />
                                Αφαίρεση λογοτύπου
                            </label>
                        )}
                    </div>

                    <div className={card}>
                        <h2 className="text-base font-semibold text-ts-fg">Στοιχεία Συλλόγου</h2>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {[
                                ['name', 'Όνομα Συλλόγου', true],
                                ['vat_number', 'Α.Φ.Μ.'],
                                ['tax_office', 'Δ.Ο.Υ.'],
                                ['address', 'Έδρα'],
                                ['founded_year', 'Έτος Ίδρυσης'],
                                ['website', 'Ιστοσελίδα'],
                                ['phone', 'Τηλέφωνο'],
                                ['email', 'Email'],
                                ['custom_charge_label', 'Τίτλος προσαρμοσμένης χρέωσης'],
                            ].map(([key, label, full]) => (
                                <label
                                    key={key}
                                    className={`block text-xs text-ts-muted ${full || key === 'custom_charge_label' || key === 'email' ? 'sm:col-span-2' : ''}`}
                                >
                                    {label}
                                    <input
                                        className={`mt-1 ${field}`}
                                        value={clubForm.data[key]}
                                        onChange={(e) => clubForm.setData(key, e.target.value)}
                                        required={Boolean(full)}
                                    />
                                </label>
                            ))}
                        </div>
                    </div>

                    <button type="submit" className={btnPrimary} disabled={clubForm.processing}>
                        Αποθήκευση
                    </button>
                </form>
            )}

            {tab === 'users' && (
                <ClubUsersTab
                    users={users}
                    roles={roles}
                    permissionModules={permissionModules}
                    roleDefaultPermissions={roleDefaultPermissions}
                    coaches={coaches}
                />
            )}

            {tab === 'email' && (
                <div className="max-w-xl space-y-4">
                    <form
                        className={card}
                        onSubmit={(e) => {
                            e.preventDefault();
                            emailForm.post(route('admin.settings.email.update'));
                        }}
                    >
                        <h2 className="text-base font-semibold text-ts-fg">Email / SMTP</h2>
                        <p className="text-sm text-ts-muted">
                            Ισχύει μόνο για αυτόν τον σύλλογο. Κάθε σύλλογος έχει δικό του SMTP.
                        </p>
                        <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                            <input
                                type="checkbox"
                                checked={emailForm.data.enabled}
                                onChange={(e) => emailForm.setData('enabled', e.target.checked)}
                            />
                            Ενεργό
                        </label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {[
                                ['provider', 'Πάροχος'],
                                ['host', 'Host'],
                                ['port', 'Port'],
                                ['username', 'Username'],
                                ['password', 'Password', 'password'],
                                ['fromName', 'From name'],
                                ['fromEmail', 'From email'],
                                ['security', 'Security'],
                            ].map(([key, label, type]) => (
                                <label key={key} className="block text-xs text-ts-muted">
                                    {label}
                                    {settings?.smtp?.[`${key}Set`] && key === 'password' ? (
                                        <span className="ms-1 text-emerald-500">(αποθηκευμένο)</span>
                                    ) : null}
                                <input
                                    type={type || 'text'}
                                    className={`mt-1 ${field}`}
                                    value={emailForm.data[key]}
                                    onChange={(e) => emailForm.setData(key, e.target.value)}
                                    placeholder={key === 'password' ? 'Ξαναβάλε το password' : ''}
                                    autoComplete={key === 'password' ? 'new-password' : 'off'}
                                />
                                </label>
                            ))}
                        </div>
                        <button type="submit" className={btnPrimary} disabled={emailForm.processing}>
                            Αποθήκευση
                        </button>
                    </form>

                    <form
                        className={card}
                        onSubmit={(e) => {
                            e.preventDefault();
                            setTestEmailError('');
                            setTestEmailProcessing(true);
                            router.post(
                                route('admin.settings.email.test'),
                                {
                                    to: testEmailTo,
                                    enabled: emailForm.data.enabled,
                                    provider: emailForm.data.provider,
                                    host: emailForm.data.host,
                                    port: emailForm.data.port,
                                    username: emailForm.data.username,
                                    password: emailForm.data.password,
                                    fromName: emailForm.data.fromName,
                                    fromEmail: emailForm.data.fromEmail,
                                    security: emailForm.data.security,
                                    requireAuth: emailForm.data.requireAuth,
                                },
                                {
                                    preserveScroll: true,
                                    onFinish: () => setTestEmailProcessing(false),
                                    onError: (errors) => {
                                        setTestEmailError(
                                            errors.to ||
                                                errors.fromEmail ||
                                                errors.password ||
                                                errors.host ||
                                                'Έλεγχος στοιχείων SMTP.',
                                        );
                                    },
                                },
                            );
                        }}
                    >
                        <h3 className="text-sm font-semibold text-ts-fg">Δοκιμαστικό email</h3>
                        <p className="text-sm text-ts-muted">
                            Στέλνει με τις τιμές της φόρμας πάνω και τις αποθηκεύει αν είναι έγκυρες.
                            Αν το Password δείχνει τελείες από τον browser, ξαναγράψ’ το χειροκίνητα.
                        </p>
                        <label className="block text-xs text-ts-muted">
                            Διεύθυνση παραλήπτη
                            <input
                                type="email"
                                className={`mt-1 ${field}`}
                                value={testEmailTo}
                                onChange={(e) => setTestEmailTo(e.target.value)}
                                autoComplete="off"
                                required
                            />
                        </label>
                        {testEmailError && (
                            <p className="text-sm text-rose-500">{testEmailError}</p>
                        )}
                        <button
                            type="submit"
                            className={btnPrimary}
                            disabled={testEmailProcessing || !testEmailTo}
                        >
                            {testEmailProcessing
                                ? 'Αποστολή…'
                                : 'Αποστολή δοκιμαστικού'}
                        </button>
                    </form>
                </div>
            )}

            {tab === 'sms' && (
                <form
                    className={`max-w-xl ${card}`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        smsForm.post(route('admin.settings.sms.update'));
                    }}
                >
                    <h2 className="text-base font-semibold text-ts-fg">SMS / Viber</h2>
                    <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                        <input
                            type="checkbox"
                            checked={smsForm.data.enabled}
                            onChange={(e) => smsForm.setData('enabled', e.target.checked)}
                        />
                        Ενεργό
                    </label>
                    <div className="grid gap-2">
                        <input
                            className={field}
                            placeholder="Πάροχος (sms.to / http)"
                            value={smsForm.data.provider}
                            onChange={(e) => smsForm.setData('provider', e.target.value)}
                        />
                        <input
                            className={field}
                            type="password"
                            placeholder={settings?.sms?.apiKeySet ? 'API key (αποθηκευμένο)' : 'API key'}
                            value={smsForm.data.apiKey}
                            onChange={(e) => smsForm.setData('apiKey', e.target.value)}
                        />
                        <input
                            className={field}
                            placeholder="Sender (έως 11 χαρακτήρες)"
                            maxLength={11}
                            value={smsForm.data.sender}
                            onChange={(e) => smsForm.setData('sender', e.target.value)}
                        />
                        <input
                            className={field}
                            placeholder="HTTP URL (αν generic)"
                            value={smsForm.data.httpUrl}
                            onChange={(e) => smsForm.setData('httpUrl', e.target.value)}
                        />
                    </div>
                    <button type="submit" className={btnPrimary}>
                        Αποθήκευση
                    </button>
                </form>
            )}

            {tab === 'payments' && (
                <div className="max-w-2xl space-y-4">
                    <div>
                        <h2 className="text-base font-semibold text-ts-fg">Online πληρωμές</h2>
                        <p className="mt-1 text-sm text-ts-muted">
                            Οι γονείς βλέπουν μόνο τους παρόχους που έχει επιτρέψει ο διαχειριστής
                            πλατφόρμας. Email και SMS ρυθμίζονται στα προχωρημένα.
                        </p>
                    </div>
                    {[
                        [
                            vivaForm,
                            'Viva Wallet',
                            `Συνδέστε Client ID, Client Secret και Source Code από το Viva banking app για online πληρωμές (Smart Checkout) του συλλόγου «${club?.name || 'σύλλογος'}».`,
                            [
                                ['clientId', 'Client ID', 'Client ID'],
                                ['clientSecret', 'Client Secret', 'Client Secret'],
                                ['merchantId', 'Merchant ID (προαιρετικό)', 'Merchant ID'],
                                ['sourceCode', 'Source Code (4 ψηφία)', 'π.χ. 1234'],
                            ],
                            'Ενεργές',
                        ],
                        [
                            stripeForm,
                            'Stripe',
                            'Ρυθμίσεις Stripe για online πληρωμές.',
                            [
                                ['publishableKey', 'Publishable key', 'Publishable key'],
                                ['secretKey', 'Secret key', 'Secret key'],
                            ],
                            'Ενεργό',
                        ],
                        [
                            eurobankForm,
                            'Eurobank',
                            'Ρυθμίσεις Eurobank για online πληρωμές.',
                            [
                                ['merchantId', 'Merchant ID', 'Merchant ID'],
                                ['secret', 'Secret', 'Secret'],
                            ],
                            'Ενεργό',
                        ],
                    ].map(([form, title, description, fields, enabledLabel]) => (
                        <form
                            key={title}
                            className={card}
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(route('admin.settings.payments.update'));
                            }}
                        >
                            <h3 className="text-base font-semibold text-ts-fg">{title}</h3>
                            <p className="text-sm text-ts-muted">{description}</p>
                            <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                <span className="text-xs text-ts-muted">Ενεργές online πληρωμές</span>
                                <input
                                    type="checkbox"
                                    checked={form.data.enabled}
                                    onChange={(e) => form.setData('enabled', e.target.checked)}
                                />
                                <span>{enabledLabel}</span>
                            </label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {fields.map(([key, label, placeholder]) => (
                                    <label key={key} className="block text-xs text-ts-muted">
                                        {label}
                                        <input
                                            className={`mt-1 ${field}`}
                                            type={key.toLowerCase().includes('secret') ? 'password' : 'text'}
                                            placeholder={placeholder}
                                            value={form.data[key]}
                                            onChange={(e) => form.setData(key, e.target.value)}
                                        />
                                    </label>
                                ))}
                            </div>
                            {'demo' in form.data && (
                                <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                    <input
                                        type="checkbox"
                                        checked={form.data.demo}
                                        onChange={(e) => form.setData('demo', e.target.checked)}
                                    />
                                    Demo / sandbox
                                </label>
                            )}
                            <button type="submit" className={btnPrimary}>
                                Αποθήκευση
                            </button>
                        </form>
                    ))}
                </div>
            )}

            {tab === 'publicRegistration' && (
                <form
                    className="max-w-2xl space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        publicForm.post(route('admin.settings.public.update'));
                    }}
                >
                    <div className="flex items-center gap-2 text-ts-fg">
                        <span className="text-lg" aria-hidden>
                            👤+
                        </span>
                        <h2 className="text-base font-semibold">Δημόσια εγγραφή</h2>
                    </div>

                    <div className={`${card} space-y-4`}>
                        <h3 className="text-base font-semibold text-ts-fg">Δημόσια εγγραφή αθλητή</h3>

                        <PublicRegBanner>
                            Ενεργοποίησε τη φόρμα και μοιράσου το link σε γονείς/αθλητές. Οι αιτήσεις
                            εγκρίνονται από το προσωπικό.
                        </PublicRegBanner>

                        <SettingsFormRow label="Ενεργή δημόσια εγγραφή">
                            <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                <input
                                    type="checkbox"
                                    checked={publicForm.data.enabled}
                                    disabled={!settings?.dpa_accepted_at && !publicForm.data.enabled}
                                    onChange={(e) => publicForm.setData('enabled', e.target.checked)}
                                />
                                <span>Ενεργή</span>
                            </label>
                            {!settings?.dpa_accepted_at ? (
                                <PublicRegInfo>
                                    Πρώτα καταγράψτε αποδοχή DPA στο GDPR.{' '}
                                    <button
                                        type="button"
                                        className="text-ts-accent underline"
                                        onClick={() => setTab('amka')}
                                    >
                                        Μετάβαση στο GDPR
                                    </button>
                                </PublicRegInfo>
                            ) : null}
                        </SettingsFormRow>

                        <SettingsFormRow label="Άμεση εμφάνιση στη λίστα αθλητών">
                            <label className="flex items-center gap-2 text-sm text-ts-muted">
                                <input type="checkbox" checked={false} disabled />
                                <span>Ανενεργή</span>
                            </label>
                            <PublicRegInfo>
                                Κάθε δημόσια εγγραφή μένει σε αναμονή. Ο σύλλογος ενεργοποιεί τον αθλητή
                                από Αθλητές → εκκρεμείς αιτήσεις (Έγκριση).
                            </PublicRegInfo>
                        </SettingsFormRow>

                        <SettingsFormRow label="Δοκιμαστική προπόνηση">
                            <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                <input
                                    type="checkbox"
                                    checked={publicForm.data.allowTrial}
                                    onChange={(e) => publicForm.setData('allowTrial', e.target.checked)}
                                />
                                <span>Στη δημόσια εγγραφή</span>
                            </label>
                        </SettingsFormRow>

                        <SettingsFormRow label="Λίστα αναμονής">
                            <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                <input
                                    type="checkbox"
                                    checked={publicForm.data.allowWaitlist}
                                    onChange={(e) => publicForm.setData('allowWaitlist', e.target.checked)}
                                />
                                <span>Στη δημόσια εγγραφή</span>
                            </label>
                            <PublicRegInfo>
                                Όταν γεμίσει ένα τμήμα ή επιλεγεί λίστα αναμονής, οι αιτήσεις μπαίνουν σε
                                σειρά.
                            </PublicRegInfo>
                        </SettingsFormRow>

                        <SettingsFormRow label="Σύνδεσμος (slug)">
                            <input
                                className={field}
                                value={publicForm.data.slug}
                                onChange={(e) => publicForm.setData('slug', e.target.value)}
                                placeholder="π.χ. demo-club"
                            />
                            <p className="mt-1 break-all text-xs text-ts-muted">{joinUrl}</p>
                        </SettingsFormRow>

                        <SettingsFormRow label="Email ειδοποίησης">
                            <input
                                type="email"
                                className={field}
                                value={publicForm.data.notifyEmail}
                                onChange={(e) => publicForm.setData('notifyEmail', e.target.value)}
                            />
                        </SettingsFormRow>

                        <SettingsFormRow label="Δημόσια κράτηση γηπέδου">
                            <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                                <input
                                    type="checkbox"
                                    checked={publicForm.data.public_rent_enabled}
                                    onChange={(e) =>
                                        publicForm.setData('public_rent_enabled', e.target.checked)
                                    }
                                />
                                <span>Ενεργή</span>
                            </label>
                        </SettingsFormRow>

                        <div className="flex justify-end pt-2">
                            <button type="submit" className={btnPrimary} disabled={publicForm.processing}>
                                Αποθήκευση
                            </button>
                        </div>
                    </div>
                </form>
            )}

            {tab === 'password' && (
                <div className={card}>
                    <h2 className="text-base font-semibold text-ts-fg">Αλλαγή κωδικού</h2>
                    <p className="text-sm text-ts-muted">
                        Ο κωδικός λογαριασμού αλλάζει από το προφίλ χρήστη.
                    </p>
                    <Link href={route('profile.edit')} className={btnPrimary + ' inline-block'}>
                        Άνοιγμα προφίλ →
                    </Link>
                </div>
            )}

            {tab === 'sports' && (
                <SimpleCrud
                    title="Άθλημα"
                    form={sportForm}
                    storeRoute="admin.settings.sports.store"
                    destroyRoute="admin.settings.sports.destroy"
                    items={sports}
                    fields={[['name', 'Όνομα', true]]}
                    renderItem={(s) => s.name}
                />
            )}

            {tab === 'facilities' && (
                <div className="max-w-xl space-y-4">
                    <form
                        className={`${card} grid gap-2 sm:grid-cols-2`}
                        onSubmit={(e) => {
                            e.preventDefault();
                            facilityForm.post(route('admin.settings.facilities.store'), {
                                onSuccess: () => facilityForm.reset(),
                            });
                        }}
                    >
                        <input
                            required
                            placeholder="Όνομα"
                            className={field}
                            value={facilityForm.data.name}
                            onChange={(e) => facilityForm.setData('name', e.target.value)}
                        />
                        <input
                            placeholder="Διεύθυνση"
                            className={field}
                            value={facilityForm.data.address}
                            onChange={(e) => facilityForm.setData('address', e.target.value)}
                        />
                        <label className="flex items-center gap-2 text-sm text-ts-fg-soft sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={facilityForm.data.is_rentable}
                                onChange={(e) => facilityForm.setData('is_rentable', e.target.checked)}
                            />
                            Διαθέσιμο για ενοικίαση
                        </label>
                        <button type="submit" className={btnPrimary}>
                            Προσθήκη
                        </button>
                    </form>
                    <ItemList
                        items={facilities}
                        empty="Καμία εγκατάσταση."
                        destroyRoute="admin.settings.facilities.destroy"
                        render={(f) => (
                            <>
                                <div className="text-ts-fg">{f.name}</div>
                                <div className="text-xs text-ts-muted">
                                    {f.address || '—'}
                                    {f.is_rentable ? ' · ενοικιάσιμο' : ''}
                                </div>
                            </>
                        )}
                    />
                </div>
            )}

            {tab === 'seasons' && (
                <div className="max-w-xl space-y-4">
                    <form
                        className={`${card} grid gap-2 sm:grid-cols-2`}
                        onSubmit={(e) => {
                            e.preventDefault();
                            seasonForm.post(route('admin.settings.seasons.store'), {
                                onSuccess: () => seasonForm.reset('name', 'starts_on', 'ends_on'),
                            });
                        }}
                    >
                        <input
                            required
                            placeholder="Όνομα σεζόν"
                            className={`sm:col-span-2 ${field}`}
                            value={seasonForm.data.name}
                            onChange={(e) => seasonForm.setData('name', e.target.value)}
                        />
                        <input
                            type="date"
                            className={field}
                            value={seasonForm.data.starts_on}
                            onChange={(e) => seasonForm.setData('starts_on', e.target.value)}
                        />
                        <input
                            type="date"
                            className={field}
                            value={seasonForm.data.ends_on}
                            onChange={(e) => seasonForm.setData('ends_on', e.target.value)}
                        />
                        <label className="flex items-center gap-2 text-sm text-ts-fg-soft">
                            <input
                                type="checkbox"
                                checked={seasonForm.data.is_active}
                                onChange={(e) => seasonForm.setData('is_active', e.target.checked)}
                            />
                            Ενεργή σεζόν
                        </label>
                        <button type="submit" className={btnPrimary}>
                            Προσθήκη
                        </button>
                    </form>
                    <ul className="space-y-1">
                        {seasons.map((s) => (
                            <li
                                key={s.id}
                                className="flex items-center justify-between rounded border border-ts-border px-3 py-2 text-sm"
                            >
                                <div>
                                    <div className="text-ts-fg">
                                        {s.name}
                                        {s.is_active && (
                                            <span className="ms-2 rounded bg-emerald-900/50 px-1.5 py-0.5 text-[10px] text-emerald-800">
                                                ενεργή
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-xs text-ts-muted">
                                        {s.starts_on || '—'} → {s.ends_on || '—'}
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    {!s.is_active && (
                                        <button
                                            type="button"
                                            className="text-ts-accent text-sm"
                                            onClick={() =>
                                                router.patch(route('admin.settings.seasons.update', s.id), {
                                                    name: s.name,
                                                    starts_on: s.starts_on,
                                                    ends_on: s.ends_on,
                                                    is_active: true,
                                                })
                                            }
                                        >
                                            Ενεργοποίηση
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        className={btnDanger}
                                        onClick={() => {
                                            if (confirm('Διαγραφή;')) {
                                                router.delete(route('admin.settings.seasons.destroy', s.id));
                                            }
                                        }}
                                    >
                                        Διαγραφή
                                    </button>
                                </div>
                            </li>
                        ))}
                        {!seasons.length && <li className="text-ts-muted">Καμία σεζόν.</li>}
                    </ul>
                </div>
            )}

            {tab === 'associations' && (
                <div className="max-w-xl space-y-4">
                    <form
                        className={`${card} grid gap-2 sm:grid-cols-2`}
                        onSubmit={(e) => {
                            e.preventDefault();
                            associationForm.post(route('admin.settings.associations.store'), {
                                onSuccess: () => associationForm.reset(),
                            });
                        }}
                    >
                        <h2 className="sm:col-span-2 text-base font-semibold text-ts-fg">Νέο σωματείο</h2>
                        <input
                            required
                            placeholder="Όνομα"
                            className={`sm:col-span-2 ${field}`}
                            value={associationForm.data.name}
                            onChange={(e) => associationForm.setData('name', e.target.value)}
                        />
                        <input
                            placeholder="Πόλη"
                            className={field}
                            value={associationForm.data.city}
                            onChange={(e) => associationForm.setData('city', e.target.value)}
                        />
                        <input
                            placeholder="Τηλέφωνο"
                            className={field}
                            value={associationForm.data.phone}
                            onChange={(e) => associationForm.setData('phone', e.target.value)}
                        />
                        <input
                            placeholder="Email"
                            className={field}
                            value={associationForm.data.email}
                            onChange={(e) => associationForm.setData('email', e.target.value)}
                        />
                        <input
                            placeholder="Διεύθυνση"
                            className={field}
                            value={associationForm.data.address}
                            onChange={(e) => associationForm.setData('address', e.target.value)}
                        />
                        <label className="flex items-center gap-2 text-sm text-ts-muted sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={!!associationForm.data.active}
                                onChange={(e) =>
                                    associationForm.setData('active', e.target.checked)
                                }
                            />
                            Ενεργό
                        </label>
                        <button type="submit" className={btnPrimary}>
                            Προσθήκη
                        </button>
                    </form>
                    <ItemList
                        items={associations}
                        empty="Κανένα σωματείο."
                        destroyRoute="admin.settings.associations.destroy"
                        render={(a) => (
                            <>
                                <div className="text-ts-fg">{a.name}</div>
                                <div className="text-xs text-ts-muted">
                                    {[a.city, a.phone, a.email, a.address]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                    {a.active === false ? ' · Ανενεργό' : ''}
                                </div>
                            </>
                        )}
                    />
                </div>
            )}

            {tab === 'sizes' && (
                <div className={`max-w-2xl ${card}`}>
                    <div>
                        <h2 className="text-base font-semibold text-ts-fg">Μεγεθολόγιο</h2>
                        <p className="text-sm text-ts-muted">Ρουχισμός / μεγέθη συλλόγου</p>
                    </div>

                    <ul className="flex flex-wrap gap-2">
                        {sizes.length === 0 ? (
                            <li className="text-sm text-ts-muted">Δεν υπάρχουν μεγέθη</li>
                        ) : (
                            sizes.map((size) => (
                                <li
                                    key={size}
                                    className="inline-flex items-center gap-2 rounded-full border border-ts-border bg-ts-bg px-3 py-1.5 text-sm font-semibold text-ts-fg"
                                >
                                    <span className="tracking-wide">{size}</span>
                                    <button
                                        type="button"
                                        className="rounded-full bg-rose-500/15 px-2 py-0.5 text-xs font-semibold text-rose-600 hover:bg-rose-500/25"
                                        onClick={() =>
                                            setSizes((prev) => prev.filter((item) => item !== size))
                                        }
                                    >
                                        Διαγραφή
                                    </button>
                                </li>
                            ))
                        )}
                    </ul>

                    <form
                        className="flex flex-wrap items-end gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const value = newSize.trim();
                            if (!value) {
                                return;
                            }
                            const exists = sizes.some(
                                (item) => item.toLocaleUpperCase('el') === value.toLocaleUpperCase('el'),
                            );
                            if (!exists) {
                                setSizes((prev) => [...prev, value]);
                            }
                            setNewSize('');
                        }}
                    >
                        <label className="min-w-[12rem] flex-1 text-xs text-ts-muted">
                            Νέο μέγεθος
                            <input
                                className={`${field} mt-1`}
                                value={newSize}
                                onChange={(e) => setNewSize(e.target.value)}
                                placeholder="π.χ. XL"
                            />
                        </label>
                        <button
                            type="submit"
                            className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-bg"
                        >
                            Προσθήκη
                        </button>
                    </form>

                    <div>
                        <button
                            type="button"
                            className="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
                            onClick={() =>
                                router.post(route('admin.settings.sizes.update'), { sizes })
                            }
                        >
                            Αποθήκευση
                        </button>
                    </div>
                </div>
            )}

            {tab === 'clothing' && (
                <SimpleCrud
                    title="Πακέτα ρουχισμού"
                    form={clothingForm}
                    storeRoute="admin.settings.clothing.store"
                    destroyRoute="admin.settings.clothing.destroy"
                    items={clothingPackages}
                    fields={[
                        ['name', 'Όνομα', true],
                        ['price', 'Τιμή'],
                    ]}
                    renderItem={(p) => `${p.name}${p.price != null ? ` · €${p.price}` : ''}`}
                />
            )}

            {tab === 'discounts' && (
                <SimpleCrud
                    title="Λόγοι έκπτωσης"
                    form={discountForm}
                    storeRoute="admin.settings.discounts.store"
                    destroyRoute="admin.settings.discounts.destroy"
                    items={discountReasons}
                    fields={[
                        ['name', 'Λόγος', true],
                        ['sport', 'Άθλημα (κενό = όλα)'],
                    ]}
                    renderItem={(d) => `${d.name}${d.sport ? ` · ${d.sport}` : ' · όλα'}`}
                />
            )}

            {tab === 'receipts' && (
                <div className={card}>
                    <h2 className="text-base font-semibold text-ts-fg">Αποδείξεις είσπραξης</h2>
                    <p className="text-sm text-ts-muted">
                        Το μητρώο και η αρίθμηση μεταφέρθηκαν σε ξεχωριστή σελίδα.
                    </p>
                    <Link
                        href={route('admin.receipts.index')}
                        className="inline-flex rounded-md bg-teal-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-700"
                    >
                        Άνοιγμα μητρώου αποδείξεων
                    </Link>
                </div>
            )}

            {tab === 'terms' && (
                <form
                    className={`max-w-2xl ${card}`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        termsForm.post(route('admin.settings.terms.update'));
                    }}
                >
                    <h2 className="text-base font-semibold text-ts-fg">Όροι χρήσης</h2>
                    <textarea
                        className={`${field} min-h-64 font-mono text-xs`}
                        value={termsForm.data.terms_of_use_html}
                        onChange={(e) => termsForm.setData('terms_of_use_html', e.target.value)}
                    />
                    <button type="submit" className={btnPrimary}>
                        Αποθήκευση
                    </button>
                </form>
            )}

            {tab === 'amka' && (
                <form
                    className={`max-w-2xl space-y-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        gdprForm.post(route('admin.settings.gdpr.update'));
                    }}
                >
                    <div className={card}>
                        <h2 className="text-base font-semibold text-ts-fg">GDPR / DPA</h2>
                        {settings?.dpa_accepted_at ? (
                            <p className="text-sm text-emerald-800">
                                DPA αποδεκτή: {new Date(settings.dpa_accepted_at).toLocaleString('el-GR')}
                            </p>
                        ) : (
                            <label className="flex items-center gap-2 text-sm text-amber-200">
                                <input
                                    type="checkbox"
                                    checked={gdprForm.data.accept_dpa}
                                    onChange={(e) => gdprForm.setData('accept_dpa', e.target.checked)}
                                />
                                Αποδέχομαι τη συμφωνία επεξεργασίας δεδομένων (DPA)
                            </label>
                        )}
                        <label className="block text-xs text-ts-muted">
                            Μήνες διατήρησης
                            <input
                                type="number"
                                min={1}
                                max={120}
                                className={`mt-1 ${field}`}
                                value={gdprForm.data.data_retention_months}
                                onChange={(e) =>
                                    gdprForm.setData('data_retention_months', Number(e.target.value))
                                }
                            />
                        </label>
                    </div>
                    <div className={card}>
                        <h3 className="text-sm font-medium text-ts-fg">Κείμενο DPA (HTML)</h3>
                        <textarea
                            className={`${field} min-h-40 font-mono text-xs`}
                            value={gdprForm.data.dpa_html}
                            onChange={(e) => gdprForm.setData('dpa_html', e.target.value)}
                        />
                    </div>
                    <div className={card}>
                        <h3 className="text-sm font-medium text-ts-fg">Πολιτική διατήρησης (HTML)</h3>
                        <textarea
                            className={`${field} min-h-40 font-mono text-xs`}
                            value={gdprForm.data.retention_policy_html}
                            onChange={(e) => gdprForm.setData('retention_policy_html', e.target.value)}
                        />
                    </div>
                    <button type="submit" className={btnPrimary}>
                        Αποθήκευση GDPR
                    </button>
                </form>
            )}
        </AdminLayout>
    );
}

function SettingsFormRow({ label, children }) {
    return (
        <div className="grid gap-2 border-b border-ts-border/60 pb-4 sm:grid-cols-[minmax(0,220px)_1fr] sm:gap-6">
            <div className="text-sm font-medium text-ts-fg">{label}</div>
            <div className="space-y-2">{children}</div>
        </div>
    );
}

function PublicRegBanner({ children }) {
    return (
        <div className="flex gap-2 rounded-lg border border-emerald-200/80 bg-emerald-50/90 px-3 py-2 text-sm text-emerald-900">
            <span className="font-semibold" aria-hidden>
                i
            </span>
            <p>{children}</p>
        </div>
    );
}

function PublicRegInfo({ children }) {
    return (
        <div className="flex gap-2 rounded-md border border-ts-border bg-ts-surface-2/50 px-3 py-2 text-xs text-ts-muted">
            <span className="font-semibold text-ts-fg-soft" aria-hidden>
                i
            </span>
            <p>{children}</p>
        </div>
    );
}

function ItemList({ items, empty, destroyRoute, render }) {
    return (
        <ul className="space-y-1">
            {items.map((item) => (
                <li
                    key={item.id}
                    className="flex items-center justify-between rounded border border-ts-border px-3 py-2 text-sm"
                >
                    <div>{render(item)}</div>
                    <button
                        type="button"
                        className={btnDanger}
                        onClick={() => {
                            if (confirm('Διαγραφή;')) {
                                router.delete(route(destroyRoute, item.id));
                            }
                        }}
                    >
                        Διαγραφή
                    </button>
                </li>
            ))}
            {!items.length && <li className="text-ts-muted">{empty}</li>}
        </ul>
    );
}

function SimpleCrud({ title, form, storeRoute, destroyRoute, items, fields, renderItem }) {
    return (
        <div className="max-w-xl space-y-4">
            <form
                className={`${card} grid gap-2 sm:grid-cols-2`}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route(storeRoute), {
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <h2 className="sm:col-span-2 text-base font-semibold text-ts-fg">{title}</h2>
                {fields.map(([key, placeholder, required]) => (
                    <input
                        key={key}
                        required={Boolean(required)}
                        placeholder={placeholder}
                        className={field}
                        value={form.data[key]}
                        onChange={(e) => form.setData(key, e.target.value)}
                    />
                ))}
                <button type="submit" className={btnPrimary}>
                    Προσθήκη
                </button>
            </form>
            <ItemList
                items={items}
                empty="Κενό."
                destroyRoute={destroyRoute}
                render={(item) => <div className="text-ts-fg">{renderItem(item)}</div>}
            />
        </div>
    );
}
