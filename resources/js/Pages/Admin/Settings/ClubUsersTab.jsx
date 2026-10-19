import { router, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg';
const card = 'rounded-xl border border-ts-border bg-ts-surface p-4 shadow-sm space-y-3';
const btnPrimary =
    'rounded-md bg-ts-accent px-3 py-1.5 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';
const btnSecondary =
    'rounded-md border border-ts-border bg-ts-surface px-3 py-1.5 text-sm text-ts-fg-soft hover:bg-ts-hover';
const btnGhost = 'text-sm text-ts-accent hover:underline disabled:opacity-50';
const btnDanger = 'text-sm text-rose-400 hover:underline';

function splitFullName(value) {
    const parts = String(value || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (parts.length === 0) {
        return { lastName: '', firstName: '' };
    }
    if (parts.length === 1) {
        return { lastName: parts[0], firstName: '' };
    }
    return { lastName: parts[0], firstName: parts.slice(1).join(' ') };
}

function roleLabel(roles, roleId) {
    const match = (roles || []).find((r) => (typeof r === 'string' ? r === roleId : r.id === roleId));
    if (!match) {
        return roleId;
    }
    return typeof match === 'string' ? match : match.label || roleId;
}

export default function ClubUsersTab({
    users = [],
    roles = [],
    permissionModules = [],
    roleDefaultPermissions = {},
    coaches = [],
}) {
    const formRef = useRef(null);
    const [editingId, setEditingId] = useState(null);
    const [searchLastName, setSearchLastName] = useState('');
    const [searchFirstName, setSearchFirstName] = useState('');
    const [searchRole, setSearchRole] = useState('');
    const [togglingId, setTogglingId] = useState(null);

    const defaultsForRole = (role) =>
        Array.isArray(roleDefaultPermissions?.[role])
            ? [...roleDefaultPermissions[role]]
            : [];

    const form = useForm({
        first_name: '',
        last_name: '',
        name: '',
        email: '',
        password: '',
        role: 'coach',
        coach_id: '',
        permissions: defaultsForRole('coach'),
        finance_own_entries_only: false,
        is_active: true,
    });

    const isEditMode = Boolean(editingId);

    const applyRoleDefaults = (nextRole) => {
        form.setData((data) => ({
            ...data,
            role: nextRole,
            permissions: defaultsForRole(nextRole),
            coach_id: nextRole === 'coach' ? data.coach_id : '',
            finance_own_entries_only:
                nextRole === 'admin' || nextRole === 'doctor' ? false : data.finance_own_entries_only,
        }));
    };

    const togglePermission = (moduleId) => {
        const current = form.data.permissions || [];
        form.setData(
            'permissions',
            current.includes(moduleId)
                ? current.filter((id) => id !== moduleId)
                : [...current, moduleId],
        );
    };

    const resetForm = () => {
        setEditingId(null);
        form.setData({
            first_name: '',
            last_name: '',
            name: '',
            email: '',
            password: '',
            role: 'coach',
            coach_id: '',
            permissions: defaultsForRole('coach'),
            finance_own_entries_only: false,
            is_active: true,
        });
        form.clearErrors();
    };

    const scrollToForm = () => {
        window.requestAnimationFrame(() => {
            formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    };

    const startCreate = () => {
        resetForm();
        scrollToForm();
    };

    const startEdit = (user) => {
        const split = splitFullName(user.name);
        setEditingId(user.id);
        form.setData({
            first_name: split.firstName,
            last_name: split.lastName,
            name: user.name || '',
            email: user.email || '',
            password: '',
            role: user.role || 'coach',
            coach_id: user.coach_id || '',
            permissions: Array.isArray(user.permissions) ? [...user.permissions] : defaultsForRole(user.role),
            finance_own_entries_only: Boolean(user.finance_own_entries_only),
            is_active: user.is_active !== false,
        });
        form.clearErrors();
        scrollToForm();
    };

    const submit = (e) => {
        e.preventDefault();
        const fullName = [form.data.last_name, form.data.first_name]
            .map((part) => String(part || '').trim())
            .filter(Boolean)
            .join(' ');

        form.transform((data) => ({
            ...data,
            name: fullName || data.name,
            finance_own_entries_only: data.finance_own_entries_only ? 1 : 0,
            is_active: data.is_active ? 1 : 0,
            coach_id: data.role === 'coach' ? data.coach_id || null : null,
            permissions: Array.isArray(data.permissions) ? data.permissions : [],
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onFinish: () => form.transform((data) => data),
        };

        if (isEditMode) {
            form.patch(route('admin.settings.users.update', editingId), options);
        } else {
            form.post(route('admin.settings.users.store'), options);
        }
    };

    const toggleActive = (user) => {
        if (togglingId) {
            return;
        }
        setTogglingId(user.id);
        router.patch(
            route('admin.settings.users.update', user.id),
            {
                name: user.name,
                role: user.role,
                permissions: user.permissions || [],
                finance_own_entries_only: user.finance_own_entries_only ? 1 : 0,
                is_active: user.is_active ? 0 : 1,
                coach_id: user.coach_id || null,
            },
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
            },
        );
    };

    const roleFilterOptions = useMemo(() => {
        const labels = new Set(
            (users || []).map((u) => roleLabel(roles, u.role)).filter(Boolean),
        );
        return [...labels].sort((a, b) => a.localeCompare(b, 'el'));
    }, [users, roles]);

    const filteredUsers = useMemo(() => {
        const lastQ = searchLastName.trim().toLowerCase();
        const firstQ = searchFirstName.trim().toLowerCase();
        const roleQ = searchRole.trim().toLowerCase();

        return (users || []).filter((user) => {
            const split = splitFullName(user.name);
            const lastName = split.lastName.toLowerCase();
            const firstName = split.firstName.toLowerCase();
            const full = String(user.name || '').toLowerCase();
            const label = roleLabel(roles, user.role).toLowerCase();

            if (lastQ && !lastName.includes(lastQ) && !full.includes(lastQ)) {
                return false;
            }
            if (firstQ && !firstName.includes(firstQ) && !full.includes(firstQ)) {
                return false;
            }
            if (roleQ && label !== roleQ) {
                return false;
            }
            return true;
        });
    }, [users, roles, searchLastName, searchFirstName, searchRole]);

    const showFinanceOwn = form.data.role !== 'admin' && form.data.role !== 'doctor';
    const listNeedsScroll = filteredUsers.length > 20;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ts-fg">Χρήστες</h2>
                    <p className="mt-1 text-sm text-ts-muted">
                        Δημιουργήστε λογαριασμό με μοναδικό email και κωδικό εισόδου. Κάθε χρήστης
                        δικό του email. Μετά την αποθήκευση ο λογαριασμός ανεβαίνει στο cloud για
                        σύνδεση από κάθε συσκευή.
                    </p>
                </div>
                {!isEditMode ? (
                    <button type="button" className={btnPrimary} onClick={startCreate}>
                        👤+ Νέος χρήστης
                    </button>
                ) : null}
            </div>

            <div className="grid items-start gap-4 xl:grid-cols-2">
            <form
                id="settings-new-user"
                ref={formRef}
                className={`${card} xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto`}
                onSubmit={submit}
            >
                <h3 className="text-base font-semibold text-ts-fg">
                    {isEditMode ? 'Επεξεργασία χρήστη' : 'Νέος χρήστης / Πρόσκληση'}
                </h3>

                {(form.errors.name ||
                    form.errors.email ||
                    form.errors.password ||
                    form.errors.role ||
                    form.errors.permissions ||
                    form.errors.coach_id) && (
                    <div className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        {form.errors.name ||
                            form.errors.email ||
                            form.errors.password ||
                            form.errors.role ||
                            form.errors.permissions ||
                            form.errors.coach_id}
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="block text-xs text-ts-muted">
                        Επώνυμο
                        <input
                            required
                            className={`mt-1 ${field}`}
                            value={form.data.last_name}
                            onChange={(e) => form.setData('last_name', e.target.value)}
                            autoComplete="family-name"
                        />
                    </label>
                    <label className="block text-xs text-ts-muted">
                        Όνομα
                        <input
                            required
                            className={`mt-1 ${field}`}
                            value={form.data.first_name}
                            onChange={(e) => form.setData('first_name', e.target.value)}
                            autoComplete="given-name"
                        />
                    </label>
                    <label className="block text-xs text-ts-muted sm:col-span-2">
                        Email
                        <input
                            required
                            type="email"
                            className={`mt-1 ${field}`}
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            disabled={isEditMode}
                        />
                    </label>
                    <label className="block text-xs text-ts-muted sm:col-span-2">
                        Κωδικός εισόδου
                        <input
                            required={!isEditMode}
                            type="text"
                            minLength={isEditMode && !form.data.password ? undefined : 6}
                            placeholder={
                                isEditMode
                                    ? 'Πληκτρολογήστε νέο κωδικό ή αφήστε κενό'
                                    : 'Πληκτρολογήστε κωδικό (τουλάχιστον 6 χαρακτήρες)'
                            }
                            className={`mt-1 ${field}`}
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                        <span className="mt-1 block text-[11px] text-ts-muted">
                            {isEditMode
                                ? 'Πληκτρολογήστε χειροκίνητα νέο κωδικό μόνο αν θέλετε να αλλάξει.'
                                : 'Πληκτρολογήστε χειροκίνητα τον κωδικό. Θα τον χρησιμοποιήσει ο χρήστης στο /login μαζί με το email.'}
                        </span>
                    </label>
                    <label className="block text-xs text-ts-muted sm:col-span-2">
                        Ρόλος
                        <select
                            className={`mt-1 ${field}`}
                            value={form.data.role}
                            onChange={(e) => applyRoleDefaults(e.target.value)}
                        >
                            {roles.map((r) => {
                                const id = typeof r === 'string' ? r : r.id;
                                const label = typeof r === 'string' ? r : r.label;
                                return (
                                    <option key={id} value={id}>
                                        {label}
                                    </option>
                                );
                            })}
                        </select>
                    </label>
                    {form.data.role === 'coach' ? (
                        <label className="block text-xs text-ts-muted sm:col-span-2">
                            Σύνδεση με προπονητή
                            <select
                                className={`mt-1 ${field}`}
                                value={form.data.coach_id || ''}
                                onChange={(e) => form.setData('coach_id', e.target.value)}
                            >
                                <option value="">— χωρίς σύνδεση —</option>
                                {coaches.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.last_name} {c.first_name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : null}
                </div>

                <div>
                    <div className="text-xs font-medium text-ts-muted">Δικαιώματα πρόσβασης</div>
                    <p className="mt-1 text-[11px] text-ts-muted">
                        Προεπιλογή από Platform Admin για τον ρόλο. Αλλάξτε τα μόνο αν θέλετε εξαίρεση
                        για αυτόν τον χρήστη.
                    </p>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                        {permissionModules.map((mod) => (
                            <label
                                key={mod.id}
                                className="flex items-center gap-2 text-sm text-ts-fg-soft"
                            >
                                <input
                                    type="checkbox"
                                    checked={(form.data.permissions || []).includes(mod.id)}
                                    onChange={() => togglePermission(mod.id)}
                                />
                                <span>{mod.label}</span>
                            </label>
                        ))}
                    </div>
                </div>

                {showFinanceOwn ? (
                    <div>
                        <div className="text-xs font-medium text-ts-muted">
                            Οικονομικά (έσοδα / έξοδα)
                        </div>
                        <label className="mt-2 flex items-center gap-2 text-sm text-ts-fg-soft">
                            <input
                                type="checkbox"
                                checked={Boolean(form.data.finance_own_entries_only)}
                                onChange={(e) =>
                                    form.setData('finance_own_entries_only', e.target.checked)
                                }
                            />
                            <span>Μόνο οι δικές του καταχωρήσεις</span>
                        </label>
                        <p className="mt-1 text-[11px] text-ts-muted">
                            Ενεργοποιήστε το στο λογαριασμό με αυτό το email. Θα βλέπει στα Οικονομικά
                            μόνο έσοδα/έξοδα που καταχώρησε ο ίδιος. Οι συνδρομές αθλητών και οι
                            εγγραφές άλλων χρηστών μένουν εκτός της οθόνης του. Ο διαχειριστής τα
                            βλέπει όλα. Χρειάζεται δικαίωμα «Οικονομικά».
                        </p>
                    </div>
                ) : null}

                <div className="flex flex-wrap gap-2">
                    <button type="submit" className={btnPrimary} disabled={form.processing}>
                        {isEditMode ? 'Αποθήκευση' : 'Δημιουργία χρήστη'}
                    </button>
                    <button type="button" className={btnSecondary} onClick={resetForm}>
                        Άκυρο
                    </button>
                </div>
            </form>

            <div className={`${card} min-w-0`}>
                <h3 className="text-base font-semibold text-ts-fg">Εγγεγραμμένοι στην εφαρμογή</h3>
                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="block text-xs text-ts-muted">
                        Επώνυμο
                        <input
                            className={`mt-1 ${field}`}
                            value={searchLastName}
                            onChange={(e) => setSearchLastName(e.target.value)}
                            placeholder="Αναζήτηση επωνύμου"
                        />
                    </label>
                    <label className="block text-xs text-ts-muted">
                        Όνομα
                        <input
                            className={`mt-1 ${field}`}
                            value={searchFirstName}
                            onChange={(e) => setSearchFirstName(e.target.value)}
                            placeholder="Αναζήτηση ονόματος"
                        />
                    </label>
                    <label className="block text-xs text-ts-muted">
                        Ρόλος
                        <select
                            className={`mt-1 ${field}`}
                            value={searchRole}
                            onChange={(e) => setSearchRole(e.target.value)}
                        >
                            <option value="">Όλοι οι ρόλοι</option>
                            {roleFilterOptions.map((label) => (
                                <option key={label} value={label}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                {!users.length ? (
                    <p className="text-sm text-ts-muted">Δεν υπάρχουν ακόμα εγγεγραμμένα μέλη.</p>
                ) : null}
                {users.length > 0 && filteredUsers.length === 0 ? (
                    <p className="text-sm text-ts-muted">Δεν βρέθηκαν αποτελέσματα για την αναζήτηση.</p>
                ) : null}

                <ul
                    className={`divide-y divide-ts-border ${
                        listNeedsScroll
                            ? 'max-h-[min(calc(2.75rem+20*2.5rem),calc(100vh-16rem))] overflow-y-auto overscroll-contain pr-1'
                            : ''
                    }`}
                >
                    {filteredUsers.map((user) => {
                        const label = roleLabel(roles, user.role);
                        const active = user.is_active !== false;
                        return (
                            <li
                                key={user.id}
                                className="flex flex-wrap items-start justify-between gap-3 py-3"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="text-sm font-semibold text-ts-fg">{label}</div>
                                    <div className="text-sm text-ts-fg">
                                        <span className="font-medium uppercase tracking-wide">
                                            {user.name}
                                        </span>
                                        <span className="ms-2 text-ts-muted normal-case">
                                            {user.email}
                                        </span>
                                        <span className="ms-2 text-ts-muted">· Λογαριασμός</span>
                                        <span
                                            className={`ms-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                                active
                                                    ? 'bg-emerald-500/15 text-emerald-300'
                                                    : 'bg-amber-500/15 text-amber-300'
                                            }`}
                                        >
                                            {active ? 'Ενεργός' : 'Ανενεργός'}
                                        </span>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <button
                                        type="button"
                                        className={btnGhost}
                                        disabled={togglingId === user.id}
                                        onClick={() => toggleActive(user)}
                                    >
                                        {active ? 'Ανενεργός' : 'Ενεργός'}
                                    </button>
                                    <button
                                        type="button"
                                        className={btnGhost}
                                        onClick={() => startEdit(user)}
                                    >
                                        Κωδικός / Επεξεργασία
                                    </button>
                                    <button
                                        type="button"
                                        className={btnDanger}
                                        onClick={() => {
                                            if (confirm('Διαγραφή χρήστη από τον σύλλογο;')) {
                                                router.delete(
                                                    route('admin.settings.users.destroy', user.id),
                                                );
                                            }
                                        }}
                                    >
                                        Διαγραφή
                                    </button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </div>
            </div>
        </div>
    );
}
