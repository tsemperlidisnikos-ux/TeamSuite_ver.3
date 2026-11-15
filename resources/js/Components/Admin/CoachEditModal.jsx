import Modal from '@/Components/Modal';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const LICENSE_LEVELS = [
    { value: 'A', label: 'Επίπεδο Α' },
    { value: 'B', label: 'Επίπεδο Β' },
    { value: 'C', label: 'Επίπεδο Γ' },
    { value: 'D', label: 'Επίπεδο Δ' },
];

const inputClass =
    'mt-1 w-full rounded-md border border-ts-border bg-white px-2 py-1.5 text-sm text-ts-fg';

function toDateInputValue(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const raw = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
        return raw.slice(0, 10);
    }
    return raw;
}

function emptyForm() {
    return {
        last_name: '',
        first_name: '',
        email: '',
        phone: '',
        sport: '',
        gga_code: '',
        is_active: true,
        hire_date: '',
        license_level: '',
        license_starts_at: '',
        license_expires_at: '',
        first_aid_starts_at: '',
        first_aid_expires_at: '',
        remove_photo: false,
    };
}

function formFromRow(row) {
    return {
        last_name: row.last_name ?? '',
        first_name: row.first_name ?? '',
        email: row.email ?? '',
        phone: row.phone ?? '',
        sport: row.sport ?? '',
        gga_code: row.gga_code ?? '',
        is_active: row.is_active === true || row.is_active === 1 || row.is_active === '1',
        hire_date: toDateInputValue(row.hire_date),
        license_level: row.license_level ?? '',
        license_starts_at: toDateInputValue(row.license_starts_at),
        license_expires_at: toDateInputValue(row.license_expires_at),
        first_aid_starts_at: toDateInputValue(row.first_aid_starts_at),
        first_aid_expires_at: toDateInputValue(row.first_aid_expires_at ?? row.first_aid),
        remove_photo: false,
    };
}

export default function CoachEditModal({
    show,
    row,
    updateRoute,
    sports = [],
    onClose,
}) {
    const [form, setForm] = useState(emptyForm);
    const [photoPreview, setPhotoPreview] = useState(null);
    const [photoFile, setPhotoFile] = useState(null);
    const [licenseFile, setLicenseFile] = useState(null);
    const [firstAidFile, setFirstAidFile] = useState(null);
    const [processing, setProcessing] = useState(false);
    const photoInputRef = useRef(null);
    const licenseInputRef = useRef(null);
    const firstAidInputRef = useRef(null);

    useEffect(() => {
        if (!show || !row) {
            return;
        }
        setForm(formFromRow(row));
        setPhotoPreview(row.photo_url || null);
        setPhotoFile(null);
        setLicenseFile(null);
        setFirstAidFile(null);
    }, [show, row]);

    const setField = (name, value) => {
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const onPickPhoto = (event) => {
        const file = event.target.files?.[0] || null;
        setPhotoFile(file);
        setForm((prev) => ({ ...prev, remove_photo: false }));
        if (file) {
            setPhotoPreview(URL.createObjectURL(file));
        }
    };

    const clearPhoto = () => {
        setPhotoFile(null);
        setPhotoPreview(null);
        setForm((prev) => ({ ...prev, remove_photo: true }));
        if (photoInputRef.current) {
            photoInputRef.current.value = '';
        }
    };

    const submit = (event) => {
        event.preventDefault();
        if (!row?.id || !updateRoute) {
            return;
        }

        const payload = {
            _method: 'patch',
            ...form,
            is_active: form.is_active ? 1 : 0,
            remove_photo: form.remove_photo ? 1 : 0,
        };
        if (photoFile) {
            payload.photo = photoFile;
        }
        if (licenseFile) {
            payload.license_file = licenseFile;
        }
        if (firstAidFile) {
            payload.first_aid_file = firstAidFile;
        }

        setProcessing(true);
        router.post(route(updateRoute, row.id), payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onClose?.(),
            onFinish: () => setProcessing(false),
        });
    };

    const sportOptions = Array.from(
        new Set([...(sports || []), form.sport].filter(Boolean)),
    );

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <form onSubmit={submit} className="max-h-[85vh] overflow-y-auto bg-sky-50 p-5">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <p className="text-xs font-semibold uppercase tracking-wider text-sky-800">
                        Επεξεργασία προπονητή
                    </p>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-white/70 hover:text-slate-800"
                        aria-label="Κλείσιμο"
                    >
                        ✕
                    </button>
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-3">
                    {photoPreview ? (
                        <img
                            src={photoPreview}
                            alt=""
                            className="h-20 w-20 rounded-md object-cover ring-1 ring-slate-200"
                        />
                    ) : (
                        <div className="flex h-20 w-20 items-center justify-center rounded-md bg-slate-100 text-center text-[11px] text-slate-500 ring-1 ring-slate-200">
                            Χωρίς φωτογραφία
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
                            onClick={() => photoInputRef.current?.click()}
                        >
                            Προσθήκη φωτογραφίας
                        </button>
                        {photoPreview ? (
                            <button
                                type="button"
                                className="rounded-md px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50"
                                onClick={clearPhoto}
                            >
                                Αφαίρεση
                            </button>
                        ) : null}
                        <input
                            ref={photoInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={onPickPhoto}
                        />
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label className="block text-sm">
                        <span className="text-slate-600">Επώνυμο</span>
                        <input
                            className={inputClass}
                            required
                            value={form.last_name}
                            onChange={(e) => setField('last_name', e.target.value)}
                        />
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Όνομα</span>
                        <input
                            className={inputClass}
                            required
                            value={form.first_name}
                            onChange={(e) => setField('first_name', e.target.value)}
                        />
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Email</span>
                        <input
                            type="email"
                            className={inputClass}
                            value={form.email}
                            onChange={(e) => setField('email', e.target.value)}
                        />
                        <span className="mt-1 block text-[11px] text-slate-500">
                            Με έγκυρο email δημιουργείται αυτόματα λογαριασμός για την εφαρμογή
                            προπονητή
                        </span>
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Τηλέφωνο</span>
                        <input
                            className={inputClass}
                            value={form.phone}
                            onChange={(e) => setField('phone', e.target.value)}
                        />
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Άθλημα</span>
                        <select
                            className={inputClass}
                            value={form.sport}
                            onChange={(e) => setField('sport', e.target.value)}
                        >
                            <option value="">—</option>
                            {sportOptions.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Κωδικός Γ.Γ.Α</span>
                        <input
                            className={inputClass}
                            value={form.gga_code}
                            onChange={(e) => setField('gga_code', e.target.value)}
                        />
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Κατάσταση</span>
                        <select
                            className={inputClass}
                            value={form.is_active ? '1' : '0'}
                            onChange={(e) => setField('is_active', e.target.value === '1')}
                        >
                            <option value="1">Ενεργός</option>
                            <option value="0">Ανενεργός</option>
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span className="text-slate-600">Ημερομηνία πρόσληψης</span>
                        <input
                            type="date"
                            className={inputClass}
                            value={form.hire_date}
                            onChange={(e) => setField('hire_date', e.target.value)}
                        />
                    </label>
                </div>

                <section className="mt-4 rounded-lg border border-slate-200 bg-slate-100/80 p-4">
                    <h3 className="mb-3 text-sm font-semibold text-slate-800">
                        Άδεια άσκησης επαγγέλματος
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label className="block text-sm">
                            <span className="text-slate-600">Επίπεδο</span>
                            <select
                                className={inputClass}
                                value={form.license_level}
                                onChange={(e) => setField('license_level', e.target.value)}
                            >
                                <option value="">Επιλέξτε επίπεδο</option>
                                {LICENSE_LEVELS.map((level) => (
                                    <option key={level.value} value={level.value}>
                                        {level.label}
                                    </option>
                                ))}
                                {form.license_level &&
                                !LICENSE_LEVELS.some((l) => l.value === form.license_level) ? (
                                    <option value={form.license_level}>{form.license_level}</option>
                                ) : null}
                            </select>
                        </label>
                        <label className="block text-sm">
                            <span className="text-slate-600">Έναρξη ισχύος</span>
                            <input
                                type="date"
                                className={inputClass}
                                value={form.license_starts_at}
                                onChange={(e) => setField('license_starts_at', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            <span className="text-slate-600">Λήξη ισχύος</span>
                            <input
                                type="date"
                                className={inputClass}
                                value={form.license_expires_at}
                                onChange={(e) => setField('license_expires_at', e.target.value)}
                            />
                        </label>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            className="rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
                            onClick={() => licenseInputRef.current?.click()}
                        >
                            Ανέβασμα αρχείου
                        </button>
                        <span className="text-xs text-slate-500">
                            {licenseFile?.name ||
                                (row?.license_file_url ? 'Υπάρχον αρχείο' : 'PDF ή εικόνα')}
                        </span>
                        {row?.license_file_url ? (
                            <a
                                href={row.license_file_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-xs text-ts-accent hover:underline"
                            >
                                Προβολή
                            </a>
                        ) : null}
                        <input
                            ref={licenseInputRef}
                            type="file"
                            accept="application/pdf,image/*"
                            className="hidden"
                            onChange={(e) => setLicenseFile(e.target.files?.[0] || null)}
                        />
                    </div>
                </section>

                <section className="mt-4 rounded-lg border border-slate-200 bg-slate-100/80 p-4">
                    <h3 className="mb-3 text-sm font-semibold text-slate-800">
                        Πιστοποιητικό πρώτων βοηθειών
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm">
                            <span className="text-slate-600">Έναρξη ισχύος</span>
                            <input
                                type="date"
                                className={inputClass}
                                value={form.first_aid_starts_at}
                                onChange={(e) => setField('first_aid_starts_at', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            <span className="text-slate-600">Λήξη ισχύος</span>
                            <input
                                type="date"
                                className={inputClass}
                                value={form.first_aid_expires_at}
                                onChange={(e) => setField('first_aid_expires_at', e.target.value)}
                            />
                        </label>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            className="rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
                            onClick={() => firstAidInputRef.current?.click()}
                        >
                            Ανέβασμα αρχείου
                        </button>
                        <span className="text-xs text-slate-500">
                            {firstAidFile?.name ||
                                (row?.first_aid_file_url ? 'Υπάρχον αρχείο' : 'PDF ή εικόνα')}
                        </span>
                        {row?.first_aid_file_url ? (
                            <a
                                href={row.first_aid_file_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-xs text-ts-accent hover:underline"
                            >
                                Προβολή
                            </a>
                        ) : null}
                        <input
                            ref={firstAidInputRef}
                            type="file"
                            accept="application/pdf,image/*"
                            className="hidden"
                            onChange={(e) => setFirstAidFile(e.target.files?.[0] || null)}
                        />
                    </div>
                </section>

                <div className="mt-5 flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-ts-border bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        disabled={processing}
                    >
                        Ακύρωση
                    </button>
                    <button
                        type="submit"
                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-60"
                        disabled={processing}
                    >
                        {processing ? 'Αποθήκευση…' : 'Αποθήκευση'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
