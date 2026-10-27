import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const ROLE_ID_KEYS = {
    parent: 'parent_ids',
    coach: 'coach_ids',
    athlete: 'athlete_ids',
    staff: 'staff_ids',
};

const emptyForm = () => ({
    title: '',
    body: '',
    roles: ['parent', 'athlete'],
    class_ids: [],
    parent_ids: [],
    coach_ids: [],
    athlete_ids: [],
    staff_ids: [],
    sport: '',
    publish: true,
    send_email: true,
    send_sms: false,
    send_push: false,
    remove_image: false,
});

function pillClass(active, open) {
    if (active || open) {
        return 'border-ts-accent bg-ts-accent text-ts-accent-fg';
    }
    return 'border-sky-200 bg-sky-50 text-ts-fg hover:bg-sky-100';
}

const ROLE_PILL_STYLE = {
    parent: { background: '#2f6fed', color: '#fff' },
    coach: { background: '#7a4fd4', color: '#fff' },
    athlete: { background: '#1f9d63', color: '#fff' },
    staff: { background: '#5b6b7c', color: '#fff' },
};

function formatPublishedAt(iso) {
    if (!iso) {
        return 'Πρόχειρο';
    }
    try {
        return new Intl.DateTimeFormat('el-GR', {
            timeZone: 'Europe/Athens',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function recipientCountForRole(audience, role) {
    const key = ROLE_ID_KEYS[role];
    if (!key) {
        return 0;
    }
    return (audience?.[key] || []).length;
}

export default function Index({
    items,
    classes = [],
    sports = [],
    filters,
    roleOptions = [],
    audienceOptions = {},
}) {
    const flash = usePage().props.flash;
    const pageErrors = usePage().props.errors || {};
    const [editingId, setEditingId] = useState(null);
    const [existingImageUrl, setExistingImageUrl] = useState(null);
    const [imagePreviewUrl, setImagePreviewUrl] = useState(null);
    const [pickerOpen, setPickerOpen] = useState(null);
    const [classesEnabled, setClassesEnabled] = useState(false);
    const [pickerQuery, setPickerQuery] = useState('');
    const [emailReport, setEmailReport] = useState(null);
    const [listQuery, setListQuery] = useState(filters.q || '');
    const imageFileRef = useRef(null);
    const bodyRef = useRef(null);

    const listMeta = items.meta || {};
    const listLinks = items.links || [];

    const form = useForm(emptyForm());

    useEffect(() => {
        return () => {
            if (imagePreviewUrl) {
                URL.revokeObjectURL(imagePreviewUrl);
            }
        };
    }, [imagePreviewUrl]);

    const apply = (patch) => {
        router.get(route('admin.announcements.index'), { ...filters, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const toggleIdIn = (key, id) => {
        const current = form.data[key] || [];
        const next = current.includes(id)
            ? current.filter((x) => x !== id)
            : [...current, id];
        form.setData(key, next);
    };

    const toggleAudience = (role) => {
        const active = (form.data.roles || []).includes(role);
        const idKey = ROLE_ID_KEYS[role];

        if (!active) {
            form.setData('roles', [...(form.data.roles || []), role]);
            setPickerOpen(role);
            setPickerQuery('');
            return;
        }

        if (pickerOpen === role) {
            form.setData({
                ...form.data,
                roles: (form.data.roles || []).filter((r) => r !== role),
                ...(idKey ? { [idKey]: [] } : {}),
            });
            setPickerOpen(null);
            setPickerQuery('');
            return;
        }

        setPickerOpen(role);
        setPickerQuery('');
    };

    const toggleClassesTarget = () => {
        if (!classesEnabled) {
            setClassesEnabled(true);
            setPickerOpen('classes');
            setPickerQuery('');
            return;
        }

        if (pickerOpen === 'classes') {
            setClassesEnabled(false);
            form.setData('class_ids', []);
            setPickerOpen(null);
            setPickerQuery('');
            return;
        }

        setPickerOpen('classes');
        setPickerQuery('');
    };

    const filteredClasses = useMemo(() => {
        if (!form.data.sport) {
            return classes;
        }
        return classes.filter((c) => (c.sport || '') === form.data.sport);
    }, [classes, form.data.sport]);

    const pickerRows = useMemo(() => {
        const q = pickerQuery.trim().toLowerCase();
        const sport = (form.data.sport || '').trim();
        const classFilter = form.data.class_ids || [];

        if (pickerOpen === 'classes') {
            return filteredClasses
                .map((c) => ({
                    id: c.id,
                    label: c.name,
                    hint: c.sport || '',
                }))
                .filter(
                    (row) =>
                        !q ||
                        row.label.toLowerCase().includes(q) ||
                        row.hint.toLowerCase().includes(q),
                );
        }

        if (pickerOpen === 'parent') {
            return (audienceOptions.parents || []).filter(
                (row) =>
                    !q ||
                    row.label.toLowerCase().includes(q) ||
                    (row.hint || '').toLowerCase().includes(q),
            );
        }

        if (pickerOpen === 'coach') {
            return (audienceOptions.coaches || [])
                .filter((row) => !sport || !row.sport || row.sport === sport)
                .filter(
                    (row) =>
                        !q ||
                        row.label.toLowerCase().includes(q) ||
                        (row.hint || '').toLowerCase().includes(q),
                );
        }

        if (pickerOpen === 'athlete') {
            return (audienceOptions.athletes || [])
                .filter((row) => !sport || !row.sport || row.sport === sport)
                .filter(
                    (row) =>
                        classFilter.length === 0 ||
                        (row.class_ids || []).some((id) => classFilter.includes(id)),
                )
                .filter(
                    (row) =>
                        !q ||
                        row.label.toLowerCase().includes(q) ||
                        (row.hint || '').toLowerCase().includes(q),
                );
        }

        if (pickerOpen === 'staff') {
            return (audienceOptions.staff || []).filter(
                (row) =>
                    !q ||
                    row.label.toLowerCase().includes(q) ||
                    (row.hint || '').toLowerCase().includes(q),
            );
        }

        return [];
    }, [
        pickerOpen,
        pickerQuery,
        filteredClasses,
        audienceOptions,
        form.data.sport,
        form.data.class_ids,
    ]);

    const selectedCountFor = (role) => {
        if (role === 'classes') {
            return (form.data.class_ids || []).length;
        }
        const key = ROLE_ID_KEYS[role];
        return key ? (form.data[key] || []).length : 0;
    };

    const clearImageSelection = () => {
        if (imagePreviewUrl) {
            URL.revokeObjectURL(imagePreviewUrl);
        }
        imageFileRef.current = null;
        setImagePreviewUrl(null);
    };

    const onImageSelected = (file) => {
        clearImageSelection();
        if (!file) {
            return;
        }
        imageFileRef.current = file;
        setImagePreviewUrl(URL.createObjectURL(file));
        form.setData('remove_image', false);
    };

    const startEdit = (item) => {
        setEditingId(item.id);
        clearImageSelection();
        setExistingImageUrl(item.image_url || null);
        const audience = item.audience || {};
        const classIds = audience.class_ids || [];
        setClassesEnabled(classIds.length > 0);
        setPickerOpen(null);
        setPickerQuery('');
        form.setData({
            ...emptyForm(),
            title: item.title,
            body: item.body || '',
            roles: audience.roles || [],
            class_ids: classIds,
            parent_ids: audience.parent_ids || [],
            coach_ids: audience.coach_ids || [],
            athlete_ids: audience.athlete_ids || [],
            staff_ids: audience.staff_ids || [],
            sport: audience.sport || '',
            publish: item.is_published,
            send_email: false,
            send_sms: false,
            send_push: false,
            remove_image: false,
        });
        form.clearErrors();
        document.getElementById('announcement-compose')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    };

    const resetForm = () => {
        setEditingId(null);
        setExistingImageUrl(null);
        clearImageSelection();
        setPickerOpen(null);
        setClassesEnabled(false);
        setPickerQuery('');
        form.reset();
        form.setData(emptyForm());
        form.clearErrors();
    };

    const wrapBody = (prefix, suffix = prefix) => {
        const el = bodyRef.current;
        const value = form.data.body || '';
        if (!el) {
            form.setData('body', `${prefix}${value}${suffix}`);
            return;
        }
        const start = el.selectionStart ?? value.length;
        const end = el.selectionEnd ?? value.length;
        const selected = value.slice(start, end);
        const next = value.slice(0, start) + prefix + selected + suffix + value.slice(end);
        form.setData('body', next);
        requestAnimationFrame(() => {
            el.focus();
            const cursor = start + prefix.length + selected.length + suffix.length;
            el.setSelectionRange(cursor, cursor);
        });
    };

    const submit = (e, { asDraft = false } = {}) => {
        e.preventDefault();
        const publish = asDraft ? false : !!form.data.publish;
        const sendEmail = publish && !!form.data.send_email;
        const sendSms = publish && !!form.data.send_sms;
        const sendPush = publish && !!form.data.send_push;

        if (publish && (sendEmail || sendSms || sendPush)) {
            const roleLabels = (form.data.roles || [])
                .map((r) => roleOptions.find((o) => o.value === r)?.label || r)
                .join(', ');
            const classCount = (form.data.class_ids || []).length;
            const sportBit = form.data.sport ? ` · Άθλημα: ${form.data.sport}` : '';
            const classBit = classCount ? ` · Τμήματα: ${classCount}` : '';
            const channels = [
                sendEmail ? 'email' : null,
                sendSms ? 'SMS' : null,
                sendPush ? 'push' : null,
            ]
                .filter(Boolean)
                .join(' + ');
            const msg =
                `Θα σταλεί ${channels} στους παραλήπτες του ακροατηρίου` +
                ` (${roleLabels || 'όλοι'}${sportBit}${classBit}).\nΣυνέχεια;`;
            if (!confirm(msg)) {
                return;
            }
        }

        form.transform((data) => {
            const payload = {
                ...data,
                publish: publish ? 1 : 0,
                send_email: sendEmail ? 1 : 0,
                send_sms: sendSms ? 1 : 0,
                send_push: sendPush ? 1 : 0,
                remove_image: data.remove_image ? 1 : 0,
                class_ids: classesEnabled ? data.class_ids || [] : [],
            };
            if (imageFileRef.current instanceof File) {
                payload.image = imageFileRef.current;
            } else {
                delete payload.image;
            }
            return payload;
        });

        const options = {
            forceFormData: true,
            onSuccess: resetForm,
            onFinish: () => form.transform((data) => data),
        };

        if (editingId) {
            form.patch(route('admin.announcements.update', editingId), options);
        } else {
            form.post(route('admin.announcements.store'), options);
        }
    };

    const previewSrc =
        imagePreviewUrl || (!form.data.remove_image && existingImageUrl ? existingImageUrl : null);

    const isRowChecked = (id) => {
        if (pickerOpen === 'classes') {
            return (form.data.class_ids || []).includes(id);
        }
        const key = ROLE_ID_KEYS[pickerOpen];
        return key ? (form.data[key] || []).includes(id) : false;
    };

    const onRowToggle = (id) => {
        if (pickerOpen === 'classes') {
            toggleIdIn('class_ids', id);
            return;
        }
        const key = ROLE_ID_KEYS[pickerOpen];
        if (key) {
            toggleIdIn(key, id);
        }
    };

    const pickerEmptyHint = () => {
        if (pickerOpen === 'classes') {
            return classes.length === 0
                ? 'Δεν υπάρχουν τμήματα στον σύλλογο.'
                : 'Δεν βρέθηκαν τμήματα με τα τρέχοντα φίλτρα.';
        }
        if (pickerOpen === 'parent') {
            return 'Δεν υπάρχουν συνδεδεμένοι γονείς. Χωρίς επιλογή → όλοι οι γονείς.';
        }
        return 'Δεν βρέθηκαν εγγραφές.';
    };

    return (
        <AdminLayout title="Ανακοινώσεις">
            <Head title="Ανακοινώσεις" />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold text-ts-fg">Ανακοινώσεις</h2>
                    <p className="text-sm text-ts-muted">
                        Δημιουργία και αποστολή ανακοινώσεων σε γονείς, προπονητές και αθλητές
                    </p>
                </div>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover"
                    onClick={() => {
                        resetForm();
                        document.getElementById('announcement-compose')?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start',
                        });
                    }}
                >
                    <span aria-hidden>✈</span>
                    Νέα Ανακοίνωση
                </button>
            </div>

            <form
                id="announcement-compose"
                onSubmit={(e) => submit(e)}
                className="mb-6 rounded-lg border border-ts-border bg-ts-surface p-4"
            >
                <p className="mb-3 text-sm font-semibold text-ts-fg">
                    {editingId ? 'Επεξεργασία ανακοίνωσης' : 'Νέα ανακοίνωση'}
                </p>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(16rem,0.9fr)]">
                    <div className="space-y-3">
                        <label className="block text-sm">
                            <span className="mb-1 block text-xs text-ts-muted">Τίτλος *</span>
                            <input
                                required
                                placeholder="Πληκτρολογήστε τίτλο ανακοίνωσης"
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm text-ts-fg"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                            />
                            {form.errors.title && (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.title}</p>
                            )}
                        </label>

                        <div>
                            <span className="mb-1 block text-xs text-ts-muted">Περιεχόμενο *</span>
                            <div className="overflow-hidden rounded-md border border-ts-border bg-ts-bg">
                                <div
                                    className="flex flex-wrap gap-1 border-b border-ts-border px-2 py-1.5"
                                    role="toolbar"
                                    aria-label="Μορφοποίηση"
                                >
                                    {[
                                        { label: 'B', title: 'Έντονα', run: () => wrapBody('**', '**') },
                                        { label: 'I', title: 'Πλάγια', run: () => wrapBody('_', '_') },
                                        { label: 'U', title: 'Υπογράμμιση', run: () => wrapBody('__', '__') },
                                        { label: '•', title: 'Λίστα', run: () => wrapBody('\n- ', '') },
                                        { label: '1.', title: 'Αρίθμηση', run: () => wrapBody('\n1. ', '') },
                                    ].map((btn) => (
                                        <button
                                            key={btn.title}
                                            type="button"
                                            title={btn.title}
                                            aria-label={btn.title}
                                            onClick={btn.run}
                                            className="inline-flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-xs font-semibold text-ts-fg-soft hover:bg-ts-hover"
                                        >
                                            {btn.label}
                                        </button>
                                    ))}
                                </div>
                                <textarea
                                    ref={bodyRef}
                                    required
                                    rows={8}
                                    placeholder="Γράψτε το περιεχόμενο της ανακοίνωσης..."
                                    className="w-full resize-y border-0 bg-transparent px-2 py-2 text-sm text-ts-fg focus:outline-none focus:ring-0"
                                    value={form.data.body}
                                    onChange={(e) => form.setData('body', e.target.value)}
                                />
                            </div>
                            {form.errors.body && (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.body}</p>
                            )}
                        </div>

                        <div>
                            <span className="mb-1 block text-xs text-ts-muted">Εικόνα</span>
                            {previewSrc ? (
                                <div className="relative overflow-hidden rounded-xl border border-ts-border">
                                    <img
                                        src={previewSrc}
                                        alt=""
                                        className="max-h-64 w-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        className="absolute right-2 top-2 rounded-md bg-black/60 px-2 py-1 text-xs text-white"
                                        onClick={() => {
                                            clearImageSelection();
                                            if (existingImageUrl) {
                                                form.setData('remove_image', true);
                                            }
                                        }}
                                    >
                                        Αφαίρεση
                                    </button>
                                </div>
                            ) : (
                                <label className="flex cursor-pointer items-center justify-center gap-2 rounded-full bg-ts-accent px-4 py-3 text-sm font-semibold text-ts-accent-fg hover:bg-ts-accent-hover">
                                    <span aria-hidden>+</span>
                                    Προσθήκη εικόνας
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        className="sr-only"
                                        onChange={(e) => onImageSelected(e.target.files?.[0] || null)}
                                    />
                                </label>
                            )}
                            <p className="mt-1 text-xs text-ts-muted">
                                PNG, JPG ή WEBP (μέγ. 4MB). Η εικόνα εμφανίζεται στην ανακοίνωση και
                                επισυνάπτεται στο email.
                            </p>
                            {(form.errors.image || pageErrors.image) && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {form.errors.image || pageErrors.image}
                                </p>
                            )}
                        </div>
                    </div>

                    <aside className="space-y-4">
                        <div>
                            <div className="mb-2 text-xs font-medium text-ts-muted">Ακροατήριο</div>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                <label className="block text-sm">
                                    <span className="mb-1 block text-xs text-ts-muted">Άθλημα</span>
                                    <select
                                        className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                        value={form.data.sport}
                                        onChange={(e) => {
                                            const sport = e.target.value;
                                            const nextClasses = (form.data.class_ids || []).filter(
                                                (id) => {
                                                    const cls = classes.find((c) => c.id === id);
                                                    return !sport || !cls || cls.sport === sport;
                                                },
                                            );
                                            form.setData({
                                                ...form.data,
                                                sport,
                                                class_ids: nextClasses,
                                            });
                                        }}
                                    >
                                        <option value="">Όλα τα αθλήματα</option>
                                        {sports.map((name) => (
                                            <option key={name} value={name}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="block text-sm">
                                    <span className="mb-1 block text-xs text-ts-muted">Σωματείο</span>
                                    <select
                                        className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                        defaultValue=""
                                        disabled
                                    >
                                        <option value="">Όλα τα σωματεία</option>
                                    </select>
                                </label>
                                <label className="block text-sm">
                                    <span className="mb-1 block text-xs text-ts-muted">
                                        Τίτλος προσαρμοσμένης χρέωσης
                                    </span>
                                    <select
                                        className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                        defaultValue=""
                                        disabled
                                    >
                                        <option value="">Όλα</option>
                                    </select>
                                </label>
                                <label className="block text-sm">
                                    <span className="mb-1 block text-xs text-ts-muted">
                                        Πακέτο ρουχισμού
                                    </span>
                                    <select
                                        className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                        defaultValue=""
                                        disabled
                                    >
                                        <option value="">Όλα</option>
                                    </select>
                                </label>
                            </div>

                            <div className="mt-3 flex flex-wrap gap-2">
                                {roleOptions.map((r) => {
                                    const active = (form.data.roles || []).includes(r.value);
                                    const open = pickerOpen === r.value;
                                    const count = selectedCountFor(r.value);
                                    return (
                                        <button
                                            key={r.value}
                                            type="button"
                                            onClick={() => toggleAudience(r.value)}
                                            className={`rounded-md border px-2.5 py-1.5 text-left text-xs font-medium ${pillClass(active, open)}`}
                                        >
                                            <span className="block">{r.label}</span>
                                            {active ? (
                                                <span
                                                    className={`block text-[10px] font-normal ${
                                                        open || active
                                                            ? 'text-ts-accent-fg/80'
                                                            : 'text-ts-muted'
                                                    }`}
                                                >
                                                    {count > 0 ? `${count} επιλεγμένοι` : 'Όλοι'}
                                                </span>
                                            ) : null}
                                        </button>
                                    );
                                })}
                                <button
                                    type="button"
                                    onClick={toggleClassesTarget}
                                    className={`rounded-md border px-2.5 py-1.5 text-left text-xs font-medium ${pillClass(
                                        classesEnabled,
                                        pickerOpen === 'classes',
                                    )}`}
                                >
                                    <span className="block">Τμήματα</span>
                                    {classesEnabled ? (
                                        <span className="block text-[10px] font-normal text-ts-accent-fg/80">
                                            {(form.data.class_ids || []).length > 0
                                                ? `${form.data.class_ids.length} επιλεγμένα`
                                                : 'Επιλογή'}
                                        </span>
                                    ) : null}
                                </button>
                            </div>

                            {pickerOpen ? (
                                <div className="mt-3 rounded-md border border-ts-border bg-ts-bg p-2">
                                    <input
                                        type="search"
                                        value={pickerQuery}
                                        onChange={(e) => setPickerQuery(e.target.value)}
                                        placeholder="Αναζήτηση..."
                                        className="mb-2 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg"
                                    />
                                    <div
                                        className="max-h-48 space-y-1 overflow-y-auto"
                                        role="group"
                                        aria-label="Επιλογές ακροατηρίου"
                                    >
                                        {pickerRows.length === 0 ? (
                                            <p className="px-1 py-2 text-xs text-ts-muted">
                                                {pickerEmptyHint()}
                                            </p>
                                        ) : (
                                            pickerRows.map((row) => {
                                                const checked = isRowChecked(row.id);
                                                return (
                                                    <label
                                                        key={row.id}
                                                        className={`flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm ${
                                                            checked
                                                                ? 'bg-sky-100/80'
                                                                : 'hover:bg-ts-hover'
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            className="mt-0.5"
                                                            checked={checked}
                                                            onChange={() => onRowToggle(row.id)}
                                                        />
                                                        <span className="min-w-0">
                                                            <span className="block font-medium text-ts-fg">
                                                                {row.label}
                                                            </span>
                                                            {row.hint ? (
                                                                <span className="block text-xs text-ts-muted">
                                                                    {row.hint}
                                                                </span>
                                                            ) : null}
                                                        </span>
                                                    </label>
                                                );
                                            })
                                        )}
                                    </div>
                                    <p className="mt-2 text-[11px] text-ts-muted">
                                        Χωρίς επιλογή checkbox →{' '}
                                        {pickerOpen === 'classes'
                                            ? 'όλα τα τμήματα στο φίλτρο.'
                                            : 'όλοι σε αυτή την κατηγορία.'}
                                    </p>
                                </div>
                            ) : null}
                        </div>

                        <div>
                            <div className="mb-1 text-xs font-medium text-ts-muted">Προγραμματισμός</div>
                            <select
                                className="w-full rounded-md border border-ts-border bg-ts-bg px-2 py-1.5 text-sm"
                                value={form.data.publish ? 'now' : 'draft'}
                                onChange={(e) =>
                                    form.setData('publish', e.target.value === 'now')
                                }
                            >
                                <option value="now">Δημοσίευση τώρα</option>
                                <option value="draft">Πρόχειρο</option>
                            </select>
                            <label className="mt-2 flex items-center gap-2 text-sm text-ts-fg">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.send_email}
                                    disabled={!form.data.publish}
                                    onChange={(e) => form.setData('send_email', e.target.checked)}
                                />
                                Αποστολή και με email
                            </label>
                            <label className="mt-2 flex items-center gap-2 text-sm text-ts-fg">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.send_sms}
                                    disabled={!form.data.publish}
                                    onChange={(e) => form.setData('send_sms', e.target.checked)}
                                />
                                Αποστολή και με SMS
                            </label>
                            <label className="mt-2 flex items-center gap-2 text-sm text-ts-fg">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.send_push}
                                    disabled={!form.data.publish}
                                    onChange={(e) => form.setData('send_push', e.target.checked)}
                                />
                                Αποστολή και με push
                            </label>
                            {editingId && form.data.send_email ? (
                                <p className="mt-1 text-xs text-ts-muted">
                                    Σε επεξεργασία πρέπει να ξανατσεκάρετε για επαναποστολή email.
                                </p>
                            ) : null}
                            {(pageErrors.send_email || form.errors.send_email) && (
                                <p className="mt-1 text-sm text-rose-600">
                                    {pageErrors.send_email || form.errors.send_email}
                                </p>
                            )}
                            {(pageErrors.send_sms || form.errors.send_sms) && (
                                <p className="mt-1 text-sm text-rose-600">
                                    {pageErrors.send_sms || form.errors.send_sms}
                                </p>
                            )}
                        </div>
                    </aside>
                </div>

                <div className="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-ts-border pt-4">
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={(e) => submit(e, { asDraft: true })}
                        className="px-3 py-1.5 text-sm text-ts-fg-soft hover:text-ts-fg disabled:opacity-50"
                    >
                        Αποθήκευση ως πρόχειρο
                    </button>
                    <button
                        type="button"
                        onClick={resetForm}
                        className="px-3 py-1.5 text-sm text-ts-fg-soft hover:text-ts-fg"
                    >
                        Άκυρο
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.publish}
                        className="inline-flex items-center gap-2 rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
                    >
                        <span aria-hidden>✈</span>
                        {form.processing ? 'Αποστολή…' : editingId ? 'Αποθήκευση' : 'Αποστολή'}
                    </button>
                </div>
            </form>

            <section className="rounded-xl border border-ts-border bg-ts-surface p-4 shadow-sm sm:p-5">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <h3 className="text-lg font-semibold text-ts-fg">Λίστα ανακοινώσεων</h3>
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            className="min-h-10 rounded-[10px] border border-ts-border bg-ts-bg px-3 py-2 text-sm text-ts-fg"
                            value={filters.role || ''}
                            onChange={(e) => apply({ role: e.target.value, page: 1 })}
                        >
                            <option value="">Όλα τα ακροατήρια</option>
                            {roleOptions.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </select>
                        <label className="inline-flex min-h-10 min-w-[11.5rem] items-center gap-2 rounded-[10px] border border-ts-border bg-ts-bg px-3 text-sm text-ts-fg">
                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                className="shrink-0 text-ts-muted"
                                aria-hidden
                            >
                                <circle cx="11" cy="11" r="7" />
                                <path d="M20 20l-3.5-3.5" />
                            </svg>
                            <input
                                type="search"
                                className="w-full border-0 bg-transparent p-0 text-sm text-ts-fg placeholder:text-ts-muted focus:outline-none focus:ring-0"
                                placeholder="Αναζήτηση..."
                                value={listQuery}
                                onChange={(e) => setListQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        apply({ q: listQuery, page: 1 });
                                    }
                                }}
                                onBlur={() => {
                                    if ((filters.q || '') !== listQuery) {
                                        apply({ q: listQuery, page: 1 });
                                    }
                                }}
                            />
                        </label>
                    </div>
                </div>

                {(items.data || []).length === 0 ? (
                    <p className="py-8 text-center text-sm text-ts-muted">
                        Δεν υπάρχουν ανακοινώσεις με αυτά τα κριτήρια.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-ts-border bg-slate-50/80 text-left">
                                    <th className="px-3 py-3 text-[0.72rem] font-extrabold uppercase tracking-wide text-ts-muted">
                                        Τίτλος
                                    </th>
                                    <th className="px-3 py-3 text-[0.72rem] font-extrabold uppercase tracking-wide text-ts-muted">
                                        Ακροατήριο
                                    </th>
                                    <th className="px-3 py-3 text-[0.72rem] font-extrabold uppercase tracking-wide text-ts-muted">
                                        Email
                                    </th>
                                    <th className="px-3 py-3 text-[0.72rem] font-extrabold uppercase tracking-wide text-ts-muted">
                                        Δημοσίευση
                                    </th>
                                    <th className="px-3 py-3 text-[0.72rem] font-extrabold uppercase tracking-wide text-ts-muted">
                                        Δημιουργός
                                    </th>
                                    <th className="px-3 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {(items.data || []).map((item) => {
                                    const stats = item.email_stats;
                                    const sent = stats?.sentCount ?? 0;
                                    const failed = stats?.failedCount ?? 0;
                                    const classCount = (item.audience?.class_ids || []).length;

                                    return (
                                        <tr
                                            key={item.id}
                                            className="border-b border-ts-border align-middle last:border-b-0"
                                        >
                                            <td className="max-w-[22rem] px-3 py-3.5">
                                                <strong className="block text-[0.95rem] font-semibold leading-snug text-ts-fg">
                                                    {item.title}
                                                </strong>
                                                <span className="mt-0.5 block text-[0.82rem] leading-snug text-ts-muted">
                                                    {item.body_preview || ''}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3.5">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {(item.audience?.roles || []).map((role) => {
                                                        const count = recipientCountForRole(
                                                            item.audience,
                                                            role,
                                                        );
                                                        const style = ROLE_PILL_STYLE[role] || {
                                                            background: '#5b6b7c',
                                                            color: '#fff',
                                                        };
                                                        return (
                                                            <span
                                                                key={role}
                                                                className="inline-flex rounded-full px-2.5 py-0.5 text-[0.75rem] font-bold"
                                                                style={style}
                                                            >
                                                                {roleOptions.find(
                                                                    (o) => o.value === role,
                                                                )?.label || role}
                                                                {count > 0 ? ` · ${count}` : ''}
                                                            </span>
                                                        );
                                                    })}
                                                    {classCount > 0 ? (
                                                        <span
                                                            className="inline-flex rounded-full px-2.5 py-0.5 text-[0.75rem] font-bold"
                                                            style={{
                                                                background: '#c9971a',
                                                                color: '#0b1f3a',
                                                            }}
                                                        >
                                                            Τμήματα · {classCount}
                                                        </span>
                                                    ) : null}
                                                    {!item.is_published ? (
                                                        <span className="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-[0.75rem] font-bold text-slate-700">
                                                            Πρόχειρο
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3.5">
                                                {stats ? (
                                                    <button
                                                        type="button"
                                                        className="grid gap-0.5 text-left text-[0.82rem] font-semibold text-ts-fg hover:underline"
                                                        title="Κλικ για λεπτομέρειες αποστολής"
                                                        onClick={() => setEmailReport(item)}
                                                    >
                                                        <span>Στάλθηκαν {sent}</span>
                                                        <span
                                                            className={
                                                                failed > 0
                                                                    ? 'text-[#b91c1c]'
                                                                    : 'font-semibold text-ts-muted'
                                                            }
                                                        >
                                                            Απέτυχαν {failed}
                                                        </span>
                                                        <span className="text-[0.78rem] font-semibold text-[#1d4ed8] underline underline-offset-2">
                                                            {failed > 0
                                                                ? 'Δείτε ποια απέτυχαν ›'
                                                                : 'Λεπτομέρειες ›'}
                                                        </span>
                                                    </button>
                                                ) : (
                                                    <span className="text-sm text-ts-muted">—</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-3.5 text-sm text-ts-fg">
                                                {formatPublishedAt(item.published_at)}
                                            </td>
                                            <td className="px-3 py-3.5 text-sm font-medium uppercase tracking-wide text-ts-fg">
                                                {item.created_by_name || 'Διαχειριστής'}
                                            </td>
                                            <td className="px-3 py-3.5">
                                                <div className="inline-flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        className="inline-grid h-[34px] w-[34px] place-items-center rounded-lg border border-ts-border bg-ts-surface text-ts-fg hover:border-amber-400 hover:text-amber-600"
                                                        aria-label="Επεξεργασία"
                                                        title="Επεξεργασία"
                                                        onClick={() => startEdit(item)}
                                                    >
                                                        <svg
                                                            width="15"
                                                            height="15"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            aria-hidden
                                                        >
                                                            <path d="M12 20h9" />
                                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="inline-grid h-[34px] w-[34px] place-items-center rounded-lg border border-ts-border bg-ts-surface text-ts-fg hover:border-rose-400 hover:text-[#e15b5b]"
                                                        aria-label="Διαγραφή"
                                                        title="Διαγραφή"
                                                        onClick={() => {
                                                            if (confirm('Διαγραφή ανακοίνωσης;')) {
                                                                router.delete(
                                                                    route(
                                                                        'admin.announcements.destroy',
                                                                        item.id,
                                                                    ),
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <svg
                                                            width="15"
                                                            height="15"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            aria-hidden
                                                        >
                                                            <path d="M3 6h18" />
                                                            <path d="M8 6V4h8v2" />
                                                            <path d="M19 6l-1 14H6L5 6" />
                                                            <path d="M10 11v6M14 11v6" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="mt-3.5 flex flex-wrap items-center justify-between gap-3 border-t border-ts-border pt-3 text-sm font-semibold text-ts-muted">
                    <span>
                        {listMeta.total
                            ? `${listMeta.from ?? 0}-${listMeta.to ?? 0} από ${listMeta.total}`
                            : '0 από 0'}
                    </span>
                    <div className="inline-flex flex-wrap items-center gap-1">
                        {listLinks.map((link, idx) => {
                            const label = String(link.label || '')
                                .replace(/<[^>]*>/g, '')
                                .replace(/&laquo;/gi, '‹')
                                .replace(/&raquo;/gi, '›')
                                .replace(/Previous/gi, '‹')
                                .replace(/Next/gi, '›')
                                .replace(/«/g, '‹')
                                .replace(/»/g, '›')
                                .trim();
                            if (!label || label === '...') {
                                return (
                                    <span key={`dots-${idx}`} className="px-1 text-ts-muted">
                                        …
                                    </span>
                                );
                            }
                            return (
                                <button
                                    key={`${label}-${idx}`}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(
                                                link.url,
                                                {},
                                                { preserveState: true, preserveScroll: true },
                                            );
                                        }
                                    }}
                                    className={`inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm ${
                                        link.active
                                            ? 'bg-[#e0b13a] font-bold text-[#0b1f3a]'
                                            : 'border border-ts-border bg-ts-surface text-ts-fg-soft hover:bg-ts-hover disabled:opacity-40'
                                    }`}
                                >
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </section>

            {emailReport ? (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="ann-email-report-title"
                    onClick={() => setEmailReport(null)}
                >
                    <div
                        className="max-h-[80vh] w-full max-w-lg overflow-hidden rounded-lg border border-ts-border bg-ts-surface shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-3 border-b border-ts-border px-4 py-3">
                            <div>
                                <h4
                                    id="ann-email-report-title"
                                    className="text-sm font-semibold text-ts-fg"
                                >
                                    Αναφορά αποστολής email
                                </h4>
                                <p className="text-xs text-ts-muted">{emailReport.title}</p>
                            </div>
                            <button
                                type="button"
                                className="text-sm text-ts-muted hover:text-ts-fg"
                                onClick={() => setEmailReport(null)}
                            >
                                Κλείσιμο
                            </button>
                        </div>
                        <div className="max-h-[60vh] space-y-2 overflow-y-auto px-4 py-3 text-sm">
                            <p className="text-xs text-ts-muted">
                                Στάλθηκαν {emailReport.email_stats?.sentCount ?? 0} · Απέτυχαν{' '}
                                {emailReport.email_stats?.failedCount ?? 0}
                            </p>
                            {(emailReport.email_stats?.recipients || []).length === 0 ? (
                                <p className="text-ts-muted">Δεν υπάρχουν λεπτομέρειες παραληπτών.</p>
                            ) : (
                                <ul className="divide-y divide-ts-border">
                                    {(emailReport.email_stats.recipients || []).map((row, i) => (
                                        <li
                                            key={`${row.email}-${i}`}
                                            className="flex items-center justify-between gap-2 py-2"
                                        >
                                            <span className="truncate text-ts-fg">{row.email}</span>
                                            <span
                                                className={
                                                    row.status === 'failed'
                                                        ? 'text-xs font-medium text-rose-600'
                                                        : 'text-xs font-medium text-emerald-700'
                                                }
                                            >
                                                {row.status === 'failed' ? 'Απέτυχε' : 'Στάλθηκε'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        {(emailReport.email_stats?.failedCount ?? 0) > 0 ? (
                            <div className="flex justify-end gap-2 border-t border-ts-border px-4 py-3">
                                <button
                                    type="button"
                                    className="rounded-md border border-ts-border px-3 py-1.5 text-sm text-ts-fg hover:bg-ts-muted/10"
                                    onClick={() => setEmailReport(null)}
                                >
                                    Κλείσιμο
                                </button>
                                <button
                                    type="button"
                                    className="rounded-md bg-ts-accent px-3 py-1.5 text-sm font-medium text-ts-accent-fg hover:opacity-90"
                                    onClick={() => {
                                        router.post(
                                            `/admin/announcements/${emailReport.id}/resend-failed`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => setEmailReport(null),
                                            },
                                        );
                                    }}
                                >
                                    Επαναποστολή αποτυχημένων
                                </button>
                            </div>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </AdminLayout>
    );
}
