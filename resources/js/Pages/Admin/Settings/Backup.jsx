import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Backup({ lastDryRun, verify, currentCounts }) {
    const { flash, auth } = usePage().props;
    const clubName = auth?.user?.club_name || auth?.club?.name || 'σύλλογος';
    const { data, setData, post, processing, errors } = useForm({
        backup_file: null,
    });

    const confirmForm = useForm({
        backup_id: lastDryRun?.backupId ?? '',
        mode: 'merge',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.settings.backup.preview'), {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout title="Ρυθμίσεις">
            <Head title="Αντίγραφα ασφαλείας" />

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

            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="text-xl font-semibold text-ts-fg">Αντίγραφα ασφαλείας</h2>
                    <p className="mt-1 text-sm text-ts-muted">
                        Backup και επαναφορά μόνο των δεδομένων και χρηστών του ενεργού συλλόγου (
                        <strong>{clubName}</strong>). Δεν περιλαμβάνει άλλους συλλόγους, ούτε κωδικούς
                        SMTP/Viva.
                    </p>
                </div>
                <Link href={route('admin.settings.index')} className="text-sm text-ts-accent">
                    ← Ρυθμίσεις
                </Link>
            </div>

            <div className="mb-4 rounded-lg border border-ts-border bg-ts-surface p-4">
                <h3 className="text-base font-semibold text-ts-fg">Λήψη backup συλλόγου</h3>
                <p className="mt-1 text-sm text-ts-muted">
                    Κατεβάστε JSON με αθλητές, τμήματα, οικονομικά και λογαριασμούς χρηστών του ενεργού
                    συλλόγου.
                </p>
                <a
                    href={route('admin.settings.backup.download')}
                    className="mt-3 inline-flex rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover"
                >
                    Λήψη JSON
                </a>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-lg border border-ts-border bg-ts-surface p-6">
                    <h3 className="text-base font-semibold text-ts-fg">Επαναφορά από αρχείο</h3>
                    <p className="mt-1 text-sm text-ts-muted">
                        Ανεβάστε Club JSON από την παλιά TeamSuite (π.χ.{' '}
                        <code className="text-ts-fg-soft">
                            TeamSuite-Σύλλογος-YYYY-MM-DD (ενεργοί).json
                        </code>
                        ). Πρώτα προεπισκόπηση (dry-run), μετά επιβεβαίωση.
                    </p>

                    <form onSubmit={submit} className="mt-5 space-y-4">
                        <input
                            type="file"
                            accept="application/json,.json"
                            onChange={(e) =>
                                setData('backup_file', e.target.files?.[0] ?? null)
                            }
                            className="block w-full text-sm text-ts-fg-soft file:me-3 file:rounded-md file:border-0 file:bg-ts-accent file:px-3 file:py-1.5 file:text-ts-accent-fg"
                        />
                        {errors.backup_file && (
                            <p className="text-sm text-red-400">{errors.backup_file}</p>
                        )}
                        <button
                            type="submit"
                            disabled={processing || !data.backup_file}
                            className="rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
                        >
                            Προεπισκόπηση επαναφοράς
                        </button>
                    </form>

                    {lastDryRun && (
                        <div className="mt-6 border-t border-ts-border pt-4">
                            <h3 className="text-sm font-semibold text-ts-fg">
                                {lastDryRun.restored ? 'Αποτέλεσμα εισαγωγής' : 'Dry-run μετρητές'}
                            </h3>
                            {lastDryRun.clubName && (
                                <p className="mt-1 text-sm text-ts-muted">
                                    Σύλλογος: {lastDryRun.clubName}
                                </p>
                            )}
                            <ul className="mt-2 grid grid-cols-2 gap-2 text-sm text-ts-fg-soft">
                                {Object.entries(lastDryRun.counts || {}).map(([key, value]) => (
                                    <li
                                        key={key}
                                        className="flex justify-between rounded bg-ts-surface-2 px-2 py-1"
                                    >
                                        <span>{key}</span>
                                        <span className="text-sky-300">{value}</span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-3 text-xs text-ts-muted">
                                Σύλλογος υπάρχει ήδη:{' '}
                                {lastDryRun.clubExists
                                    ? 'Ναι'
                                    : 'Όχι (θα δημιουργηθεί με το ίδιο id)'}
                            </p>
                            {lastDryRun.backupId && !lastDryRun.restored && (
                                <form
                                    className="mt-4 flex flex-wrap items-end gap-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        confirmForm.setData('backup_id', lastDryRun.backupId);
                                        confirmForm.post(route('admin.settings.backup.restore'));
                                    }}
                                >
                                    <label className="text-sm text-ts-fg-soft">
                                        Τρόπος
                                        <select
                                            value={confirmForm.data.mode}
                                            onChange={(e) =>
                                                confirmForm.setData('mode', e.target.value)
                                            }
                                            className="ms-2 rounded-md border border-ts-border bg-ts-surface px-2 py-1 text-sm text-ts-fg"
                                        >
                                            <option value="merge">Συγχώνευση</option>
                                            <option value="replace">Αντικατάσταση</option>
                                        </select>
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={confirmForm.processing}
                                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                                    >
                                        {confirmForm.processing
                                            ? 'Επαναφορά σε εξέλιξη…'
                                            : 'Επιβεβαίωση επαναφοράς'}
                                    </button>
                                    {confirmForm.processing && (
                                        <div className="basis-full space-y-2">
                                            <div
                                                className="h-2.5 w-full overflow-hidden rounded-full bg-slate-200"
                                                role="progressbar"
                                                aria-busy="true"
                                                aria-label="Πρόοδος επαναφοράς"
                                            >
                                                <div className="backup-restore-progress h-full w-1/3 rounded-full bg-emerald-600" />
                                            </div>
                                            <p className="text-xs font-medium text-slate-900">
                                                Μεγάλα JSON μπορεί να χρειαστούν αρκετά λεπτά — μην
                                                κλείσετε τη σελίδα.
                                            </p>
                                        </div>
                                    )}
                                </form>
                            )}
                        </div>
                    )}
                </div>

                <div className="space-y-4">
                    {verify && (
                        <div className="rounded-lg border border-emerald-800/50 bg-emerald-950/20 p-4">
                            <h3 className="text-sm font-semibold text-emerald-200">
                                Έλεγχος μετά την επαναφορά
                            </h3>
                            <ul className="mt-2 grid grid-cols-2 gap-2 text-sm text-ts-fg-soft">
                                {Object.entries(verify).map(([key, value]) => (
                                    <li
                                        key={key}
                                        className="flex justify-between rounded bg-ts-surface-2 px-2 py-1"
                                    >
                                        <span>{key}</span>
                                        <span className="text-emerald-800">{value}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-3 flex flex-wrap gap-2 text-sm">
                                <Link href={route('admin.athletes.index')} className="text-ts-accent">
                                    Αθλητές →
                                </Link>
                                <Link href={route('admin.classes.index')} className="text-ts-accent">
                                    Τμήματα →
                                </Link>
                                <Link href={route('admin.fees.index')} className="text-ts-accent">
                                    Συνδρομές →
                                </Link>
                            </div>
                        </div>
                    )}

                    {currentCounts && (
                        <div className="rounded-lg border border-ts-border bg-ts-surface p-4">
                            <h3 className="text-sm font-semibold text-ts-fg">
                                Τρέχοντα δεδομένα συλλόγου
                            </h3>
                            <ul className="mt-2 grid grid-cols-2 gap-2 text-sm text-ts-fg-soft">
                                {Object.entries(currentCounts).map(([key, value]) => (
                                    <li
                                        key={key}
                                        className="flex justify-between rounded bg-ts-surface-2 px-2 py-1"
                                    >
                                        <span>{key}</span>
                                        <span className="text-sky-300">{value}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
