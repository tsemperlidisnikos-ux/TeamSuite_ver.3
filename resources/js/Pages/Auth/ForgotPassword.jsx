import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Νέος κωδικός" />

            <div className="mb-4">
                <h1 className="text-xl font-semibold text-ts-fg">Νέος κωδικός</h1>
                <p className="mt-1 text-sm text-ts-muted">
                    Εισάγετε το email σας και θα σας στείλουμε σύνδεσμο για νέο κωδικό πρόσβασης.
                </p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-emerald-700">{status}</div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        isFocused
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-6 flex items-center justify-between gap-3">
                    <Link href={route('login')} className="text-sm text-ts-accent hover:underline">
                        Επιστροφή στη σύνδεση
                    </Link>
                    <PrimaryButton disabled={processing}>Αποστολή συνδέσμου</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
