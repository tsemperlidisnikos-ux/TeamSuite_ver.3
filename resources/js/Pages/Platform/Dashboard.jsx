import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const TABS = [
    { id: 'platform', label: 'Πλατφόρμα' },
    { id: 'club', label: 'Σύλλογος' },
    { id: 'backup', label: 'Backup' },
];

const NAV = {
    platform: {
        categories: [
            {
                id: 'ops',
                label: 'Λειτουργία',
                items: [
                    { id: 'users', label: 'Επιλογή χρηστών', hint: 'Ρόλοι / είσοδος ως χρήστης' },
                    { id: 'waitlist', label: 'Λίστα αναμονής ακαδημιών', hint: 'Αιτήσεις /register' },
                    { id: 'logins', label: 'Ιστορικό εισόδων', hint: 'Φίλτρο συλλόγου' },
                    { id: 'club-log', label: 'Ημερολόγιο συλλόγου', hint: 'Κινήσεις ανά ημέρα' },
                    { id: 'jpegs', label: 'Φόρμες δημόσιας εγγραφής', hint: 'URL / εγγραφές' },
                    { id: 'health-cards', label: 'Κάρτες υγείας ανά άθλημα', hint: 'PDF προτύπου' },
                ],
            },
            {
                id: 'look',
                label: 'Εμφάνιση',
                items: [
                    { id: 'logo', label: 'Logo εφαρμογής', hint: 'Καθολικό σήμα' },
                    { id: 'theme', label: 'Εμφάνιση εφαρμογής', hint: 'Light / Dark ανά σύλλογο' },
                ],
            },
            {
                id: 'catalog',
                label: 'Κατάλογος',
                items: [
                    { id: 'incomeCat', label: 'Κατηγορίες εσόδων', hint: 'Κατάλογος' },
                    { id: 'incomeDesc', label: 'Περιγραφές εσόδων', hint: 'Κατάλογος' },
                    { id: 'expenseCat', label: 'Κατηγορίες εξόδων', hint: 'Κατάλογος' },
                    { id: 'expenseDesc', label: 'Περιγραφές εξόδων', hint: 'Κατάλογος' },
                    { id: 'roles', label: 'Δικαιώματα ρόλων', hint: 'Πρόσβαση modules' },
                ],
            },
        ],
    },
    club: {
        categories: [
            {
                id: 'preview',
                label: 'Preview',
                items: [
                    { id: 'preview', label: 'Preview συλλόγου', hint: 'Ενεργοί σύλλογοι' },
                    { id: 'clubLogo', label: 'Κεφαλίδα ανά σύλλογο', hint: 'Όνομα κεφαλίδας' },
                    { id: 'associations', label: 'Ομάδες σωματείου', hint: 'Ανά σύλλογο στις Ρυθμίσεις' },
                ],
            },
            {
                id: 'academy',
                label: 'Κατάλογος ακαδημίας',
                items: [
                    { id: 'financeTabs', label: 'Καρτέλες Οικονομικών', hint: 'Παγκόσμια ενεργοποίηση' },
                    { id: 'menu', label: 'Καρτέλες μενού', hint: 'Sidebar ανά σύλλογο' },
                ],
            },
            {
                id: 'season',
                label: 'Σεζόν & άδειες',
                items: [
                    { id: 'seasons', label: 'Σεζόν', hint: 'Κατάλογος σεζόν πλατφόρμας' },
                    { id: 'licenses', label: 'Άδειες / πακέτο', hint: 'Πακέτα συνδρομής' },
                ],
            },
        ],
    },
    backup: {
        categories: [
            {
                id: 'copies',
                label: 'Αντίγραφα',
                items: [
                    { id: 'club', label: 'Backup συλλόγου', hint: 'Επαναφορά JSON' },
                    { id: 'full', label: 'Backup όλης της εφαρμογής', hint: 'Πλατφόρμα' },
                    { id: 'gdrive', label: 'Google Drive', hint: 'Σύνδεση cloud' },
                ],
            },
            {
                id: 'schedule',
                label: 'Πρόγραμμα',
                items: [{ id: 'schedule', label: 'Πρόγραμμα backup', hint: 'Χρονοπρογραμματισμός' }],
            },
            {
                id: 'check',
                label: 'Έλεγχος',
                items: [
                    { id: 'diagnostic', label: 'Αναλυτικός έλεγχος λειτουργιών', hint: 'Διαγνωστικά' },
                    { id: 'receipts', label: 'Μητρώο αποδείξεων συλλόγων', hint: 'Επισκόπηση' },
                ],
            },
        ],
    },
};

const field =
    'w-full rounded-lg border border-ts-border bg-ts-surface px-3 py-2 text-sm text-ts-fg';
const btn = 'rounded-lg bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';

function initialNav(url) {
    const params = new URLSearchParams((url || '').split('?')[1] || '');
    const requestedTab = params.get('tab');
    const tab = TABS.some((entry) => entry.id === requestedTab) ? requestedTab : 'platform';
    const categories = NAV[tab].categories;
    const requestedItem = params.get('item');
    const category = categories.find((entry) => entry.items.some((item) => item.id === requestedItem));

    if (category && requestedItem) {
        return { tab, categoryId: category.id, itemId: requestedItem };
    }

    return {
        tab,
        categoryId: categories[0].id,
        itemId: categories[0].items[0].id,
    };
}

function NavIcon({ name }) {
    const common = 'h-4 w-4';

    if (name === 'monitor') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <rect x="3" y="4" width="18" height="12" rx="2" />
                <path d="M8 20h8M12 16v4" />
            </svg>
        );
    }

    if (name === 'image') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <circle cx="8.5" cy="10" r="1.5" />
                <path d="M21 16l-5-5-9 9" />
            </svg>
        );
    }

    if (name === 'building') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <path d="M4 20h16M6 20V6h12v14M10 10h.01M14 10h.01M10 14h.01M14 14h.01" />
            </svg>
        );
    }

    if (name === 'layers') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <path d="M12 3l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5" />
            </svg>
        );
    }

    if (name === 'calendar') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <rect x="3" y="5" width="18" height="16" rx="2" />
                <path d="M8 3v4M16 3v4M3 10h18" />
            </svg>
        );
    }

    return (
        <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <circle cx="12" cy="12" r="3" />
        </svg>
    );
}

const ITEM_ICONS = {
    preview: 'monitor',
    clubLogo: 'image',
    associations: 'building',
    financeTabs: 'layers',
    menu: 'layers',
    seasons: 'calendar',
    licenses: 'calendar',
};

export default function Dashboard({
    clubsCount,
    packagesCount,
    waitlistCount,
    clubs,
    platformConfig,
    catalogs,
    activePreview = null,
}) {
    const page = usePage();
    const initial = initialNav(page.url);
    const [tab, setTab] = useState(initial.tab);
    const [categoryId, setCategoryId] = useState(initial.categoryId);
    const [itemId, setItemId] = useState(initial.itemId);
    const [catalogClubId, setCatalogClubId] = useState(clubs?.[0]?.id || '');
    const [newSeason, setNewSeason] = useState('');
    const [newIncomeCat, setNewIncomeCat] = useState('');
    const [newExpenseCat, setNewExpenseCat] = useState('');
    const [descCategory, setDescCategory] = useState('');
    const [newDesc, setNewDesc] = useState('');

    const form = useForm({ ...platformConfig });

    useEffect(() => {
        form.setData({ ...platformConfig });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [platformConfig]);

    const categories = NAV[tab]?.categories || [];
    const activeCategory = categories.find((c) => c.id === categoryId) || categories[0];
    const items = activeCategory?.items || [];
    const activeItem = items.find((i) => i.id === itemId) || items[0];

    const switchTab = (next) => {
        setTab(next);
        const firstCat = NAV[next].categories[0];
        setCategoryId(firstCat.id);
        setItemId(firstCat.items[0].id);
    };

    const save = (patch = {}) => {
        const payload = { ...form.data, ...patch };
        form.setData(payload);
        form.transform(() => payload);
        form.post(route('platform.settings.update'), { preserveScroll: true });
    };

    const toggleList = (list, id) =>
        list.includes(id) ? list.filter((x) => x !== id) : [...list, id];

    const clubModules =
        (catalogClubId && form.data.academyModulesByClub?.[catalogClubId]) ||
        form.data.defaultAcademyModules ||
        [];

    return (
        <PlatformLayout title="Διαχείριση" activeNav="manage">
            <Head title="Διαχείριση πλατφόρμας" />

            <div className="mb-4 flex flex-wrap gap-2">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => switchTab(t.id)}
                        className={`rounded-full border px-4 py-1.5 text-sm ${
                            tab === t.id
                                ? 'border-ts-accent bg-ts-accent text-ts-accent-fg'
                                : 'border-ts-border bg-ts-surface text-ts-fg-soft hover:bg-ts-surface-2'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <div className="grid items-start gap-3 lg:grid-cols-[220px_250px_minmax(0,1fr)]">
                <aside className="rounded-xl border border-ts-border bg-ts-surface p-3">
                    <div className="mb-2 px-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-ts-muted">
                        Κατηγορίες
                    </div>
                    <div className="space-y-1">
                        {categories.map((cat) => {
                            const selected = activeCategory?.id === cat.id;

                            return (
                                <button
                                    key={cat.id}
                                    type="button"
                                    onClick={() => {
                                        setCategoryId(cat.id);
                                        setItemId(cat.items[0].id);
                                    }}
                                    className={`flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm ${
                                        selected
                                            ? 'bg-ts-accent-soft font-medium text-ts-accent'
                                            : 'text-ts-fg-soft hover:bg-ts-surface-2'
                                    }`}
                                >
                                    <span
                                        className={`flex h-7 w-7 items-center justify-center rounded-md ${
                                            selected
                                                ? 'bg-ts-accent text-ts-accent-fg'
                                                : 'bg-slate-100 text-ts-muted'
                                        }`}
                                    >
                                        <NavIcon name={ITEM_ICONS[cat.items[0]?.id] || 'dot'} />
                                    </span>
                                    {cat.label}
                                </button>
                            );
                        })}
                    </div>
                </aside>

                <aside className="rounded-xl border border-ts-border bg-ts-surface p-3">
                    <div className="mb-2 px-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-ts-muted">
                        {activeCategory?.label}
                    </div>
                    <div className="space-y-1">
                        {items.map((item) => {
                            const selected = activeItem?.id === item.id;

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => setItemId(item.id)}
                                    className={`flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm ${
                                        selected
                                            ? 'bg-ts-accent-soft font-medium text-ts-accent'
                                            : 'text-ts-fg-soft hover:bg-ts-surface-2'
                                    }`}
                                >
                                    <span
                                        className={`flex h-7 w-7 items-center justify-center rounded-md ${
                                            selected
                                                ? 'bg-ts-accent text-ts-accent-fg'
                                                : 'bg-slate-100 text-ts-muted'
                                        }`}
                                    >
                                        <NavIcon name={ITEM_ICONS[item.id] || 'dot'} />
                                    </span>
                                    {item.label}
                                </button>
                            );
                        })}
                    </div>
                </aside>

                <section className="rounded-xl border border-ts-border bg-ts-surface p-5">
                    {activeItem?.id !== 'preview' && (
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold text-ts-fg">{activeItem?.label}</h2>
                            <p className="text-sm text-ts-muted">{activeItem?.hint}</p>
                        </div>
                    )}

                        {activeItem?.id === 'users' && (
                            <QuickLink
                                href={route('platform.users.index')}
                                label="Άνοιγμα επιλογής χρηστών"
                                body="Διαχειριστές, γραμματεία, προπονητές, αθλητές — είσοδος ως χρήστης."
                            />
                        )}
                        {activeItem?.id === 'waitlist' && (
                            <QuickLink
                                href={route('platform.waitlist.index')}
                                label="Άνοιγμα λίστας αναμονής"
                                body="Διαχείριση αιτήσεων εγγραφής νέων ακαδημιών."
                            />
                        )}
                        {activeItem?.id === 'logins' && (
                            <QuickLink
                                href={route('platform.logins.index')}
                                label="Άνοιγμα ιστορικού εισόδων"
                                body="Έλεγχος συνδέσεων χρηστών στην πλατφόρμα."
                            />
                        )}
                        {activeItem?.id === 'club-log' && (
                            <QuickLink
                                href={route('platform.activity.index')}
                                label="Άνοιγμα ημερολογίου συλλόγου"
                                body="Αναλυτικές κινήσεις χρηστών ανά σύλλογο (ημερομηνία, ώρα, περιγραφή)."
                            />
                        )}
                        {activeItem?.id === 'jpegs' && (
                            <QuickLink
                                href={route('admin.settings.index', { tab: 'publicRegistration' })}
                                label="Δημόσια εγγραφή"
                                body="Ρυθμίσεις φόρμας δημόσιας εγγραφής ανά ενεργό σύλλογο."
                            />
                        )}
                        {activeItem?.id === 'health-cards' && (
                            <Panel>
                                <p className="mb-3 text-sm text-ts-muted">
                                    Ορίστε σημείωση / URL προτύπου PDF ανά άθλημα (κλειδί = όνομα
                                    αθλήματος).
                                </p>
                                <textarea
                                    className={field}
                                    rows={6}
                                    placeholder={'Μπάσκετ=https://...\nΒόλεϊ='}
                                    value={Object.entries(form.data.healthCardTemplatesBySport || {})
                                        .map(([k, v]) => `${k}=${v?.pdfUrl || v || ''}`)
                                        .join('\n')}
                                    onChange={(e) => {
                                        const map = {};
                                        e.target.value.split('\n').forEach((line) => {
                                            const [k, ...rest] = line.split('=');
                                            if (!k?.trim()) return;
                                            map[k.trim()] = { pdfUrl: rest.join('=').trim() };
                                        });
                                        form.setData('healthCardTemplatesBySport', map);
                                    }}
                                />
                                <button type="button" className={`${btn} mt-3`} onClick={() => save()} disabled={form.processing}>
                                    Αποθήκευση προτύπων
                                </button>
                            </Panel>
                        )}

                        {activeItem?.id === 'logo' && (
                            <Panel>
                                <label className="mb-2 block text-sm text-ts-fg-soft">
                                    Όνομα εφαρμογής
                                    <input
                                        className={`${field} mt-1`}
                                        value={form.data.appName || ''}
                                        onChange={(e) => form.setData('appName', e.target.value)}
                                    />
                                </label>
                                <label className="mb-2 block text-sm text-ts-fg-soft">
                                    URL λογότυπου
                                    <input
                                        className={`${field} mt-1`}
                                        value={form.data.appLogoUrl || ''}
                                        onChange={(e) => form.setData('appLogoUrl', e.target.value)}
                                        placeholder="https://… ή data:image/…"
                                    />
                                </label>
                                <button type="button" className={btn} onClick={() => save()} disabled={form.processing}>
                                    Αποθήκευση εμφάνισης
                                </button>
                            </Panel>
                        )}

                        {activeItem?.id === 'theme' && (
                            <Panel>
                                <p className="mb-3 text-sm text-ts-muted">
                                    Προεπιλεγμένο θέμα πλατφόρμας. Ανά σύλλογο μπορείτε να ορίσετε
                                    override παρακάτω. Το κλικ κάνει άμεση προεπισκόπηση.
                                </p>
                                <div className="mb-4 grid gap-2 sm:grid-cols-3">
                                    {(catalogs?.appearanceThemes || []).map((theme) => {
                                        const swatches = {
                                            'ocean-slate': ['#000000', '#f0f4f8', '#2a9bb5'],
                                            'aegean-light': ['#eef3f8', '#ffffff', '#2a9bb5'],
                                            'harbor-navy': ['#e8eef5', '#ffffff', '#1e3a5f'],
                                            'night-pitch': ['#0f1419', '#161f29', '#14b8a6'],
                                        }[theme.id] || ['#000000', '#f0f4f8', '#2a9bb5'];

                                        return (
                                            <button
                                                key={theme.id}
                                                type="button"
                                                onClick={() => {
                                                    form.setData('appearanceTheme', theme.id);
                                                    document.documentElement.dataset.theme = theme.id;
                                                }}
                                                className={`rounded-md border px-3 py-3 text-left text-sm ${
                                                    form.data.appearanceTheme === theme.id
                                                        ? 'border-ts-accent bg-ts-accent-soft text-ts-accent'
                                                        : 'border-ts-border text-ts-fg-soft hover:border-ts-border'
                                                }`}
                                            >
                                                <div className="mb-2 flex gap-1">
                                                    {swatches.map((color) => (
                                                        <span
                                                            key={color}
                                                            className="h-5 flex-1 rounded border border-black/10"
                                                            style={{ backgroundColor: color }}
                                                        />
                                                    ))}
                                                </div>
                                                {theme.label}
                                            </button>
                                        );
                                    })}
                                </div>
                                <label className="mb-2 block text-sm text-ts-fg-soft">
                                    Θέμα ανά σύλλογο
                                    <select
                                        className={`${field} mt-1`}
                                        value={catalogClubId}
                                        onChange={(e) => setCatalogClubId(e.target.value)}
                                    >
                                        {(clubs || []).map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <select
                                    className={`${field} mb-3`}
                                    value={form.data.appearanceThemeByClub?.[catalogClubId] || ''}
                                    onChange={(e) => {
                                        const next = { ...(form.data.appearanceThemeByClub || {}) };
                                        if (!e.target.value) delete next[catalogClubId];
                                        else next[catalogClubId] = e.target.value;
                                        form.setData('appearanceThemeByClub', next);
                                    }}
                                >
                                    <option value="">Προεπιλογή πλατφόρμας</option>
                                    {(catalogs?.appearanceThemes || []).map((theme) => (
                                        <option key={theme.id} value={theme.id}>
                                            {theme.label}
                                        </option>
                                    ))}
                                </select>
                                <button type="button" className={btn} onClick={() => save()} disabled={form.processing}>
                                    Αποθήκευση θέματος
                                </button>
                            </Panel>
                        )}

                        {['incomeCat', 'expenseCat'].includes(activeItem?.id) && (
                            <CategoryEditor
                                fieldClass={field}
                                btnClass={btn}
                                label={activeItem.id === 'incomeCat' ? 'εσόδων' : 'εξόδων'}
                                items={
                                    activeItem.id === 'incomeCat'
                                        ? form.data.incomeCategories || []
                                        : form.data.expenseCategories || []
                                }
                                draft={activeItem.id === 'incomeCat' ? newIncomeCat : newExpenseCat}
                                setDraft={activeItem.id === 'incomeCat' ? setNewIncomeCat : setNewExpenseCat}
                                onChange={(next) =>
                                    form.setData(
                                        activeItem.id === 'incomeCat'
                                            ? 'incomeCategories'
                                            : 'expenseCategories',
                                        next,
                                    )
                                }
                                onSave={() => save()}
                                processing={form.processing}
                            />
                        )}

                        {['incomeDesc', 'expenseDesc'].includes(activeItem?.id) && (
                            <DescriptionEditor
                                fieldClass={field}
                                btnClass={btn}
                                categories={
                                    activeItem.id === 'incomeDesc'
                                        ? form.data.incomeCategories || []
                                        : form.data.expenseCategories || []
                                }
                                descriptions={
                                    activeItem.id === 'incomeDesc'
                                        ? form.data.incomeDescriptions || {}
                                        : form.data.expenseDescriptions || {}
                                }
                                category={descCategory}
                                setCategory={setDescCategory}
                                draft={newDesc}
                                setDraft={setNewDesc}
                                onChange={(next) =>
                                    form.setData(
                                        activeItem.id === 'incomeDesc'
                                            ? 'incomeDescriptions'
                                            : 'expenseDescriptions',
                                        next,
                                    )
                                }
                                onSave={() => save()}
                                processing={form.processing}
                            />
                        )}

                        {activeItem?.id === 'roles' && (
                            <RolesEditor
                                fieldClass={field}
                                btnClass={btn}
                                modules={catalogs?.academyModules || []}
                                roleLabels={catalogs?.roleLabels || {}}
                                permissions={form.data.clubRolePermissions || {}}
                                onChange={(next) => form.setData('clubRolePermissions', next)}
                                onSave={() => save()}
                                processing={form.processing}
                            />
                        )}

                        {activeItem?.id === 'preview' && (
                            <ClubsPreview clubs={clubs} activePreview={activePreview} />
                        )}

                        {activeItem?.id === 'clubLogo' && (
                            <Panel>
                                <label className="mb-2 block text-sm text-ts-fg-soft">
                                    Σύλλογος
                                    <select
                                        className={`${field} mt-1`}
                                        value={catalogClubId}
                                        onChange={(e) => setCatalogClubId(e.target.value)}
                                    >
                                        {(clubs || []).map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="mb-3 block text-sm text-ts-fg-soft">
                                    Κείμενο κεφαλίδας (πριν το Suite)
                                    <input
                                        className={`${field} mt-1`}
                                        value={form.data.clubHeaderTitles?.[catalogClubId] || ''}
                                        onChange={(e) => {
                                            const next = { ...(form.data.clubHeaderTitles || {}) };
                                            if (!e.target.value.trim()) delete next[catalogClubId];
                                            else next[catalogClubId] = e.target.value.trim();
                                            form.setData('clubHeaderTitles', next);
                                        }}
                                    />
                                </label>
                                <button type="button" className={btn} onClick={() => save()} disabled={form.processing}>
                                    Αποθήκευση κεφαλίδας
                                </button>
                            </Panel>
                        )}

                        {activeItem?.id === 'associations' && (
                            <QuickLink
                                href={route('admin.settings.index', { tab: 'associations' })}
                                label="Σωματεία συλλόγου"
                                body="Οι ομάδες σωματείου ορίζονται ανά ενεργό σύλλογο στις Ρυθμίσεις → Σωματείο."
                            />
                        )}

                        {activeItem?.id === 'financeTabs' && (
                            <Panel>
                                <p className="mb-3 text-sm text-ts-muted">
                                    Ποιες επιλογές εμφανίζονται στο μενού Οικονομικά για όλους τους
                                    συλλόγους.
                                </p>
                                <div className="space-y-2">
                                    {(catalogs?.financeTabs || []).map((ft) => (
                                        <label key={ft.id} className="flex items-center justify-between gap-3 text-sm text-ts-fg-soft">
                                            <span>{ft.label}</span>
                                            <input
                                                type="checkbox"
                                                checked={(form.data.financeTabs || []).includes(ft.id)}
                                                onChange={() =>
                                                    form.setData(
                                                        'financeTabs',
                                                        toggleList(form.data.financeTabs || [], ft.id),
                                                    )
                                                }
                                            />
                                        </label>
                                    ))}
                                </div>
                                <button type="button" className={`${btn} mt-3`} onClick={() => save()} disabled={form.processing}>
                                    Αποθήκευση καρτελών
                                </button>
                            </Panel>
                        )}

                        {activeItem?.id === 'menu' && (
                            <Panel>
                                <label className="mb-3 block text-sm text-ts-fg-soft">
                                    Σύλλογος
                                    <select
                                        className={`${field} mt-1`}
                                        value={catalogClubId}
                                        onChange={(e) => setCatalogClubId(e.target.value)}
                                    >
                                        {(clubs || []).map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="max-h-80 space-y-2 overflow-y-auto">
                                    {(catalogs?.academyModules || []).map((mod) => (
                                        <label
                                            key={mod.id}
                                            className="flex items-center justify-between gap-3 text-sm text-ts-fg-soft"
                                        >
                                            <span>{mod.label}</span>
                                            <input
                                                type="checkbox"
                                                checked={clubModules.includes(mod.id)}
                                                disabled={!catalogClubId}
                                                onChange={() => {
                                                    const nextModules = toggleList(clubModules, mod.id);
                                                    const byClub = {
                                                        ...(form.data.academyModulesByClub || {}),
                                                        [catalogClubId]: nextModules,
                                                    };
                                                    form.setData('academyModulesByClub', byClub);
                                                }}
                                            />
                                        </label>
                                    ))}
                                </div>
                                <button
                                    type="button"
                                    className={`${btn} mt-3`}
                                    onClick={() => save()}
                                    disabled={form.processing || !catalogClubId}
                                >
                                    Αποθήκευση μενού
                                </button>
                            </Panel>
                        )}

                        {activeItem?.id === 'seasons' && (
                            <Panel>
                                <form
                                    className="mb-3 flex flex-wrap gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        const label = newSeason.trim();
                                        if (!label) return;
                                        const seasons = [...(form.data.seasons || [])];
                                        if (!seasons.includes(label)) seasons.push(label);
                                        form.setData('seasons', seasons);
                                        setNewSeason('');
                                    }}
                                >
                                    <input
                                        className={`${field} max-w-xs`}
                                        value={newSeason}
                                        onChange={(e) => setNewSeason(e.target.value)}
                                        placeholder="π.χ. 2027–2028"
                                    />
                                    <button type="submit" className={btn}>
                                        Προσθήκη
                                    </button>
                                </form>
                                <ul className="mb-3 space-y-1 text-sm text-ts-fg-soft">
                                    {(form.data.seasons || []).map((season) => (
                                        <li key={season} className="flex items-center justify-between rounded bg-ts-surface-2 px-2 py-1">
                                            <span>{season}</span>
                                            <button
                                                type="button"
                                                className="text-rose-400"
                                                onClick={() =>
                                                    form.setData(
                                                        'seasons',
                                                        (form.data.seasons || []).filter((s) => s !== season),
                                                    )
                                                }
                                            >
                                                Διαγραφή
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                                <button type="button" className={btn} onClick={() => save()} disabled={form.processing}>
                                    Αποθήκευση σεζόν
                                </button>
                            </Panel>
                        )}

                        {activeItem?.id === 'licenses' && (
                            <QuickLink
                                href={route('platform.license-packages.index')}
                                label="Πακέτα αδειών"
                                body="Διαχείριση ορίων αθλητών / πακέτων συνδρομής."
                            />
                        )}

                        {activeItem?.id === 'club' && (
                            <QuickLink
                                href={route('admin.settings.backup.index')}
                                label="Επαναφορά συλλόγου από JSON"
                                body="Προεπισκόπηση και εισαγωγή αντιγράφου TeamSuite."
                            />
                        )}
                        {activeItem?.id === 'full' && (
                            <div className="flex flex-col gap-4">
                                <p className="text-sm leading-relaxed text-ts-muted">
                                    Κατεβάζει ένα JSON με όλους τους συλλόγους, χρήστες και
                                    ρυθμίσεις πλατφόρμας (ίδιο format με το legacy TeamSuite
                                    platform backup).
                                </p>
                                <div>
                                    <a
                                        href={route('platform.backup.download')}
                                        className={`inline-flex items-center ${btn}`}
                                    >
                                        Λήψη πλήρους backup
                                    </a>
                                </div>
                            </div>
                        )}
                        {activeItem?.id === 'gdrive' && (
                            <Panel>
                                <p className="text-sm text-ts-muted">
                                    Η σύνδεση Google Drive για αυτόματα αντίγραφα θα ενεργοποιηθεί σε
                                    επόμενη έκδοση.
                                </p>
                            </Panel>
                        )}
                        {activeItem?.id === 'schedule' && (
                            <Panel>
                                <p className="text-sm text-ts-muted">
                                    Ο χρονοπρογραμματισμός backup (ημερήσιο / εβδομαδιαίο) θα
                                    διαμορφωθεί μαζί με το cloud sync.
                                </p>
                            </Panel>
                        )}
                        {activeItem?.id === 'diagnostic' && (
                            <Panel>
                                <ul className="space-y-1 text-sm text-ts-fg-soft">
                                    <li>Σύλλογοι: {clubsCount}</li>
                                    <li>Πακέτα αδειών: {packagesCount}</li>
                                    <li>Waitlist: {waitlistCount}</li>
                                    <li>Modules καταλόγου: {(catalogs?.academyModules || []).length}</li>
                                    <li>Ενεργές καρτέλες οικονομικών: {(form.data.financeTabs || []).length}</li>
                                </ul>
                            </Panel>
                        )}
                        {activeItem?.id === 'receipts' && (
                            <QuickLink
                                href={route('admin.settings.index', { tab: 'receipts' })}
                                label="Σειρές αποδείξεων"
                                body="Μητρώο αποδείξεων του ενεργού συλλόγου."
                            />
                        )}
                </section>
            </div>
        </PlatformLayout>
    );
}

function Panel({ children }) {
    return <div className="space-y-2">{children}</div>;
}

function QuickLink({ href, label, body }) {
    return (
        <div className="flex flex-col gap-4">
            {body ? <p className="text-sm leading-relaxed text-ts-muted">{body}</p> : null}
            <div>
                <Link href={href} className={`inline-flex items-center ${btn}`}>
                    {label}
                </Link>
            </div>
        </div>
    );
}

function CategoryEditor({
    fieldClass,
    btnClass,
    label,
    items,
    draft,
    setDraft,
    onChange,
    onSave,
    processing,
}) {
    return (
        <Panel>
            <form
                className="mb-3 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    const value = draft.trim();
                    if (!value || items.includes(value)) return;
                    onChange([...items, value]);
                    setDraft('');
                }}
            >
                <input
                    className={`${fieldClass} max-w-sm`}
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    placeholder={`Νέα κατηγορία ${label}`}
                />
                <button type="submit" className={btnClass}>
                    Προσθήκη
                </button>
            </form>
            <ul className="mb-3 max-h-72 space-y-1 overflow-y-auto text-sm text-ts-fg-soft">
                {items.map((item) => (
                    <li key={item} className="flex items-center justify-between rounded bg-ts-surface-2 px-2 py-1">
                        <span>{item}</span>
                        <button
                            type="button"
                            className="text-rose-400"
                            onClick={() => onChange(items.filter((x) => x !== item))}
                        >
                            Διαγραφή
                        </button>
                    </li>
                ))}
            </ul>
            <button type="button" className={btnClass} onClick={onSave} disabled={processing}>
                Αποθήκευση κατηγοριών
            </button>
        </Panel>
    );
}

function DescriptionEditor({
    fieldClass,
    btnClass,
    categories,
    descriptions,
    category,
    setCategory,
    draft,
    setDraft,
    onChange,
    onSave,
    processing,
}) {
    const active = category || categories[0] || '';
    const list = descriptions[active] || [];

    return (
        <Panel>
            <label className="mb-2 block text-sm text-ts-fg-soft">
                Κατηγορία
                <select
                    className={`${fieldClass} mt-1`}
                    value={active}
                    onChange={(e) => setCategory(e.target.value)}
                >
                    {categories.map((c) => (
                        <option key={c} value={c}>
                            {c}
                        </option>
                    ))}
                </select>
            </label>
            <form
                className="mb-3 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    const value = draft.trim();
                    if (!active || !value || list.includes(value)) return;
                    onChange({ ...descriptions, [active]: [...list, value] });
                    setDraft('');
                }}
            >
                <input
                    className={`${fieldClass} max-w-sm`}
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    placeholder="Νέα περιγραφή"
                />
                <button type="submit" className={btnClass}>
                    Προσθήκη
                </button>
            </form>
            <ul className="mb-3 max-h-64 space-y-1 overflow-y-auto text-sm text-ts-fg-soft">
                {list.map((item) => (
                    <li key={item} className="flex items-center justify-between rounded bg-ts-surface-2 px-2 py-1">
                        <span>{item}</span>
                        <button
                            type="button"
                            className="text-rose-400"
                            onClick={() =>
                                onChange({
                                    ...descriptions,
                                    [active]: list.filter((x) => x !== item),
                                })
                            }
                        >
                            Διαγραφή
                        </button>
                    </li>
                ))}
            </ul>
            <button type="button" className={btnClass} onClick={onSave} disabled={processing}>
                Αποθήκευση περιγραφών
            </button>
        </Panel>
    );
}

function RolesEditor({
    fieldClass,
    btnClass,
    modules,
    roleLabels,
    permissions,
    onChange,
    onSave,
    processing,
}) {
    const roles = Object.keys(roleLabels);
    const [role, setRole] = useState(roles[0] || 'admin');
    const selected = permissions[role] || [];

    return (
        <Panel>
            <label className="mb-3 block text-sm text-ts-fg-soft">
                Ρόλος
                <select className={`${fieldClass} mt-1`} value={role} onChange={(e) => setRole(e.target.value)}>
                    {roles.map((r) => (
                        <option key={r} value={r}>
                            {roleLabels[r]}
                        </option>
                    ))}
                </select>
            </label>
            <div className="mb-3 max-h-80 space-y-2 overflow-y-auto">
                {modules.map((mod) => (
                    <label key={mod.id} className="flex items-center justify-between gap-3 text-sm text-ts-fg-soft">
                        <span>{mod.label}</span>
                        <input
                            type="checkbox"
                            checked={selected.includes(mod.id)}
                            onChange={() => {
                                const next = selected.includes(mod.id)
                                    ? selected.filter((x) => x !== mod.id)
                                    : [...selected, mod.id];
                                onChange({ ...permissions, [role]: next });
                            }}
                        />
                    </label>
                ))}
            </div>
            <button type="button" className={btnClass} onClick={onSave} disabled={processing}>
                Αποθήκευση δικαιωμάτων
            </button>
        </Panel>
    );
}

function ClubsPreview({ clubs, activePreview }) {
    const [clubId, setClubId] = useState(activePreview?.id || clubs?.[0]?.id || '');
    const [opening, setOpening] = useState(false);

    const openPreview = () => {
        if (!clubId || opening) {
            return;
        }

        const previewWindow = window.open('about:blank', '_blank');
        setOpening(true);
        router.post(
            route('platform.clubs.activate', clubId),
            { preview: true },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (previewWindow) {
                        previewWindow.location = route('dashboard');
                    }
                },
                onError: () => {
                    previewWindow?.close();
                },
                onFinish: () => setOpening(false),
            },
        );
    };

    return (
        <div>
            <h2 className="text-lg font-semibold text-ts-fg">Preview συλλόγου</h2>
            <p className="mt-1 text-sm text-ts-muted">
                Δείτε την εφαρμογή όπως εμφανίζεται σε συγκεκριμένο λογαριασμό.
            </p>

            <label className="mt-5 block text-sm text-ts-muted">
                Λογαριασμός
                <select
                    className={`${field} mt-1`}
                    value={clubId}
                    onChange={(e) => setClubId(e.target.value)}
                >
                    {(clubs || []).map((club) => (
                        <option key={club.id} value={club.id}>
                            {club.name}
                        </option>
                    ))}
                </select>
            </label>

            <button type="button" className={`${btn} mt-4`} onClick={openPreview} disabled={!clubId || opening}>
                Preview εφαρμογής
            </button>

            <div className="mt-6 grid grid-cols-[9rem_1fr] overflow-hidden rounded-lg border border-ts-border text-sm">
                <div className="border-r border-ts-border bg-ts-surface-2 px-3 py-2.5 text-ts-muted">Κατάσταση</div>
                <div className="px-3 py-2.5 text-ts-fg-soft">
                    {activePreview ? `Ενεργό preview: ${activePreview.name}` : 'Δεν υπάρχει ενεργό preview.'}
                </div>
            </div>
        </div>
    );
}
