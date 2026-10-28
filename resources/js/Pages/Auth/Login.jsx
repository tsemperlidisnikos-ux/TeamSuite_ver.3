import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Login({ status, canResetPassword, portalApp = null }) {
    const page = usePage();
    const query = page.url.includes('?')
        ? new URLSearchParams(page.url.split('?')[1])
        : new URLSearchParams();
    const app = portalApp || query.get('app');
    const isParentApp = app === 'parent';
    const isCoachApp = app === 'coach';
    const isPortalApp = isParentApp || isCoachApp;
    const replaced = query.get('replaced') === '1';

    const brand = useMemo(() => {
        if (isParentApp) {
            return {
                title: 'TeamSuite',
                accent: 'Γονείς',
                tagline: 'Πρόγραμμα, πληρωμές και ανακοινώσεις των παιδιών σας — στο κινητό.',
            };
        }
        if (isCoachApp) {
            return {
                title: 'TeamSuite',
                accent: 'Προπονητές',
                tagline: 'Πρόγραμμα, παρουσίες και ανακοινώσεις των τμημάτων σας — στο κινητό.',
            };
        }
        return {
            title: 'TeamSuite',
            accent: '',
            tagline: 'Η ολοκληρωμένη πλατφόρμα διαχείρισης για αθλητικούς οργανισμούς και ομάδες.',
        };
    }, [isParentApp, isCoachApp]);

    const title = useMemo(() => {
        if (isParentApp) return 'Σύνδεση γονέα';
        if (isCoachApp) return 'Σύνδεση προπονητή';
        return 'Σύνδεση';
    }, [isParentApp, isCoachApp]);

    const subtitle = useMemo(() => {
        if (isParentApp) {
            return 'Συνδεθείτε με τον λογαριασμό γονέα που σας έδωσε ο σύλλογος.';
        }
        if (isCoachApp) {
            return 'Συνδεθείτε με τον λογαριασμό προπονητή που σας έδωσε ο σύλλογος.';
        }
        return 'Εισαγάγετε τα διαπιστευτήριά σας για να συνεχίσετε.';
    }, [isParentApp, isCoachApp]);

    const [showPassword, setShowPassword] = useState(false);
    const [info, setInfo] = useState(() => {
        if (replaced) return 'Συνδεθήκατε από άλλη συσκευή. Κάντε είσοδο ξανά.';
        return '';
    });
    const [demoLoading, setDemoLoading] = useState(false);

    const demoEmail = isParentApp
        ? 'parent@teamsuite.app'
        : isCoachApp
          ? 'coach@teamsuite.app'
          : 'demo@teamsuite.app';

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
        portal_app: isPortalApp ? app : '',
    });

    const submit = (e) => {
        e.preventDefault();
        setInfo('');
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const enterDemo = () => {
        setDemoLoading(true);
        setInfo('');
        router.post(
            route('login'),
            {
                email: demoEmail,
                password: 'demo1234',
                remember: false,
                portal_app: isPortalApp ? app : '',
            },
            {
                onFinish: () => setDemoLoading(false),
                onError: () => {
                    setDemoLoading(false);
                    setInfo('Αποτυχία εισόδου DEMO. Βεβαιωθείτε ότι υπάρχει ο λογαριασμός demo.');
                },
            },
        );
    };

    return (
        <GuestLayout brandTitle={brand.title} brandAccent={brand.accent} tagline={brand.tagline}>
            <Head title={title} />

            <header className="mb-6">
                <h1 className="text-2xl font-semibold text-ts-fg">{title}</h1>
                <p className="mt-1 text-sm text-ts-muted">{subtitle}</p>
            </header>

            {(status || info) && (
                <div className="mb-4 text-sm font-medium text-emerald-700">{status || info}</div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <div className="relative mt-1">
                        <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-ts-muted">
                            ✉
                        </span>
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="block w-full ps-9"
                            autoComplete="username"
                            isFocused
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </div>
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Κωδικός πρόσβασης" />
                    <div className="relative mt-1">
                        <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-ts-muted">
                            🔒
                        </span>
                        <TextInput
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            className="block w-full ps-9 pe-12"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <button
                            type="button"
                            className="absolute end-3 top-1/2 -translate-y-1/2 text-xs text-ts-muted hover:text-ts-fg"
                            onClick={() => setShowPassword((v) => !v)}
                            aria-label={showPassword ? 'Απόκρυψη' : 'Εμφάνιση'}
                        >
                            {showPassword ? '🙈' : '👁'}
                        </button>
                    </div>
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between gap-3">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span className="ms-2 text-sm text-ts-muted">Να με θυμάσαι</span>
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm text-ts-accent hover:underline"
                        >
                            Ξέχασα τον κωδικό μου
                        </Link>
                    )}
                </div>

                <PrimaryButton
                    className="w-full justify-center !normal-case tracking-normal"
                    disabled={processing || demoLoading}
                >
                    Σύνδεση
                </PrimaryButton>

                <div className="flex items-center gap-3 text-xs text-ts-muted">
                    <span className="h-px flex-1 bg-ts-border" />
                    ή
                    <span className="h-px flex-1 bg-ts-border" />
                </div>

                <button
                    type="button"
                    className="w-full rounded-md border border-ts-border bg-ts-surface px-4 py-2.5 text-sm font-medium text-ts-fg hover:bg-ts-hover disabled:opacity-60"
                    disabled={processing || demoLoading}
                    onClick={enterDemo}
                >
                    {demoLoading
                        ? 'Φόρτωση DEMO…'
                        : isParentApp
                          ? 'Δοκιμή ως γονέας (DEMO)'
                          : isCoachApp
                            ? 'Δοκιμή ως προπονητής (DEMO)'
                            : 'Είσοδος DEMO παρουσίασης'}
                </button>
                <p className="text-center text-xs text-ts-muted">
                    {isParentApp
                        ? `DEMO γονέα: ${demoEmail} / demo1234`
                        : isCoachApp
                          ? `DEMO προπονητή: ${demoEmail} / demo1234`
                          : `DEMO: ${demoEmail} / demo1234`}
                </p>
            </form>

            <p className="mt-6 text-center text-sm text-ts-muted">
                {isPortalApp ? (
                    <>
                        Είστε γραμματεία;{' '}
                        <Link href={route('login')} className="text-ts-accent hover:underline">
                            Κανονική είσοδος TeamSuite
                        </Link>
                    </>
                ) : (
                    <>
                        Δεν έχετε λογαριασμό;{' '}
                        <Link href={route('register')} className="text-ts-accent hover:underline">
                            Εγγραφή συλλόγου
                        </Link>
                    </>
                )}
            </p>
        </GuestLayout>
    );
}
