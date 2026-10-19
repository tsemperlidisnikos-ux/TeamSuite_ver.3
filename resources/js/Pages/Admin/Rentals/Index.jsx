import AdminLayout from '@/Layouts/AdminLayout';
import {
    bookingAmount,
    bookingStatusLabel,
    courtShareLabel,
    formatCurrency,
    formatDayChip,
    isRentalBookingCollected,
    listRentalSlots,
    localDateIso,
    lockerRoomFeeAmount,
    nextDays,
    occupancyForDate,
    paymentMethodLabel,
    ruleForFacility,
} from '@/lib/facilityRentalAvailability';
import { formatDate } from '@/lib/portalFormat';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

function formatBookingDate(value) {
    if (!value) {
        return '—';
    }
    const raw = String(value).slice(0, 10);
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (match) {
        return `${match[3]}/${match[2]}/${match[1]}`;
    }

    return formatDate(value);
}

const WEEKDAYS = [
    { value: 1, label: 'Δευ' },
    { value: 2, label: 'Τρί' },
    { value: 3, label: 'Τετ' },
    { value: 4, label: 'Πέμ' },
    { value: 5, label: 'Παρ' },
    { value: 6, label: 'Σάβ' },
    { value: 0, label: 'Κυρ' },
];

const SLOT_OPTIONS = [60, 90, 120];

const field =
    'w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm text-ts-fg disabled:opacity-50';
const btnSecondary =
    'inline-flex items-center gap-1.5 rounded-md border border-ts-border px-2.5 py-1.5 text-xs text-ts-fg-soft hover:bg-ts-hover disabled:opacity-50';
const btnPrimary =
    'rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50';

function defaultRule(facilityId) {
    return {
        facilityId,
        enabled: false,
        slotMinutes: 60,
        windows: [{ days: [1, 2, 3, 4, 5, 6, 0], startTime: '18:00', endTime: '22:00' }],
        hourlyRate: 0,
        hourlyRateFull: 0,
        hourlyRateHalf: 0,
        lockerRoomAvailable: false,
        lockerRoomFee: 0,
    };
}

function normalizeRule(rule, facilityId) {
    const base = rule ? { ...defaultRule(facilityId), ...rule } : defaultRule(facilityId);
    const full = Number(base.hourlyRateFull) > 0 ? Number(base.hourlyRateFull) : Number(base.hourlyRate) || 0;
    const windows = Array.isArray(base.windows) && base.windows.length
        ? base.windows.map((w) => ({
              days: Array.isArray(w.days) ? w.days.map(Number) : [1, 2, 3, 4, 5, 6, 0],
              startTime: w.startTime || '18:00',
              endTime: w.endTime || '22:00',
          }))
        : defaultRule(facilityId).windows;

    return {
        ...base,
        facilityId,
        enabled: Boolean(base.enabled),
        slotMinutes: Number(base.slotMinutes) || 60,
        windows,
        hourlyRate: full,
        hourlyRateFull: full,
        hourlyRateHalf: Number(base.hourlyRateHalf) || 0,
        lockerRoomAvailable: Boolean(base.lockerRoomAvailable),
        lockerRoomFee: Number(base.lockerRoomFee) || 0,
    };
}

function readImageFile(file) {
    return new Promise((resolve, reject) => {
        if (!file) {
            resolve(null);
            return;
        }
        const reader = new FileReader();
        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : null);
        reader.onerror = () => reject(new Error('Αποτυχία ανάγνωσης αρχείου'));
        reader.readAsDataURL(file);
    });
}

function BookingsTable({
    rows = [],
    emptyLabel = 'Δεν υπάρχουν κρατήσεις.',
    allowActions = true,
    canDelete = false,
    onCollect,
    onCancel,
    onDelete,
}) {
    const showActions = allowActions || canDelete;

    return (
        <div className="overflow-x-auto rounded-lg border border-ts-border">
            <table className="min-w-full text-sm">
                <thead className="bg-ts-bg text-left text-[11px] uppercase tracking-wide text-ts-muted">
                    <tr>
                        <th className="px-3 py-2">Ημερομηνία</th>
                        <th className="px-3 py-2">Ώρα</th>
                        <th className="px-3 py-2">Γήπεδο</th>
                        <th className="px-3 py-2">Τμήμα</th>
                        <th className="px-3 py-2">Αποδυτήρια</th>
                        <th className="px-3 py-2">Όνομα</th>
                        <th className="px-3 py-2">Τηλ.</th>
                        <th className="px-3 py-2">Πηγή</th>
                        <th className="px-3 py-2">Κατάσταση</th>
                        <th className="px-3 py-2">Τελικό κόστος</th>
                        <th className="px-3 py-2">Πληρωμή</th>
                        {showActions ? <th className="px-3 py-2" /> : null}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={showActions ? 12 : 11}
                                className="px-3 py-6 text-center text-ts-muted"
                            >
                                {emptyLabel}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={row.id} className="border-t border-ts-border">
                                <td className="whitespace-nowrap px-3 py-2">
                                    {formatBookingDate(row.date)}
                                </td>
                                <td className="whitespace-nowrap px-3 py-2">
                                    {row.startTime}–{row.endTime}
                                </td>
                                <td className="px-3 py-2">{row.facilityName}</td>
                                <td className="px-3 py-2">{courtShareLabel(row.courtShare)}</td>
                                <td className="px-3 py-2">{row.useLockerRoom ? 'Ναι' : '—'}</td>
                                <td className="px-3 py-2">{row.customerName}</td>
                                <td className="px-3 py-2">{row.customerPhone || '—'}</td>
                                <td className="px-3 py-2">
                                    {row.source === 'public' ? 'Δημόσιο link' : 'Γραμματεία'}
                                </td>
                                <td className="px-3 py-2">{bookingStatusLabel(row)}</td>
                                <td className="whitespace-nowrap px-3 py-2">
                                    {formatCurrency(row.amount)}
                                </td>
                                <td className="px-3 py-2">
                                    {isRentalBookingCollected(row)
                                        ? paymentMethodLabel(row.paymentMethod)
                                        : '—'}
                                </td>
                                {showActions ? (
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1.5">
                                            {allowActions &&
                                                !isRentalBookingCollected(row) &&
                                                Number(row.amount) > 0 && (
                                                    <button
                                                        type="button"
                                                        className={btnSecondary}
                                                        onClick={() => onCollect?.(row)}
                                                    >
                                                        Είσπραξη
                                                    </button>
                                                )}
                                            {allowActions ? (
                                                <button
                                                    type="button"
                                                    className="text-xs text-rose-500 hover:text-rose-400"
                                                    onClick={() => onCancel?.(row)}
                                                >
                                                    Ακύρωση
                                                </button>
                                            ) : null}
                                            {canDelete ? (
                                                <button
                                                    type="button"
                                                    className="text-xs font-medium text-rose-600 hover:text-rose-500"
                                                    onClick={() => onDelete?.(row)}
                                                >
                                                    Διαγραφή
                                                </button>
                                            ) : null}
                                        </div>
                                    </td>
                                ) : null}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function Index({
    title = 'Ενοικίαση – Διαθεσιμότητα γηπέδων',
    subtitle = 'Διαθεσιμότητα γηπέδων, κρατήσεις γραμματείας και δημόσιο link. Οι ώρες προπόνησης, έκτακτες προπονήσεις και αγώνες αφαιρούνται αυτόματα. Τα έσοδα μπαίνουν στα Οικονομικά μόνο με είσπραξη (μετρητά / POS) ή όταν ολοκληρωθεί online πληρωμή.',
    routes,
    filters = {},
    facilities = [],
    rentalRules = [],
    publicRentEnabled = false,
    rentalNotes = '',
    heroImageUrl = null,
    uncollectedCount = 0,
    uncollected = [],
    upcomingBookings = [],
    pastBookings = [],
    canDeleteBookings = false,
    occupancy = {},
}) {
    const { currentClub, flash } = usePage().props;
    const initialTab =
        filters?.tab === 'bookings' || filters?.tab === 'link' || filters?.tab === 'availability'
            ? filters.tab
            : uncollectedCount > 0
              ? 'bookings'
              : 'availability';
    const [tab, setTab] = useState(initialTab);
    const [saving, setSaving] = useState(false);
    const [bookingBusy, setBookingBusy] = useState(false);
    const [bookDate, setBookDate] = useState(() => localDateIso());
    const [facilityId, setFacilityId] = useState(() => facilities?.[0]?.id || '');
    const [selectedSlot, setSelectedSlot] = useState(null);
    const [courtShare, setCourtShare] = useState('full');
    const [useLockerRoom, setUseLockerRoom] = useState(false);
    const [customerLastName, setCustomerLastName] = useState('');
    const [customerFirstName, setCustomerFirstName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [customerEmail, setCustomerEmail] = useState('');
    const [bookingNotes, setBookingNotes] = useState('');
    const [specialDiscount, setSpecialDiscount] = useState('');
    const [paidNow, setPaidNow] = useState(false);
    const [paidNowMethod, setPaidNowMethod] = useState('cash');
    const [recurring, setRecurring] = useState(false);
    const [untilDate, setUntilDate] = useState('');
    const [recWeekdays, setRecWeekdays] = useState([]);
    const [collectBooking, setCollectBooking] = useState(null);
    const [collectMethod, setCollectMethod] = useState('cash');
    const [message, setMessage] = useState('');
    const [notes, setNotes] = useState(rentalNotes || '');
    const [publicEnabled, setPublicEnabled] = useState(Boolean(publicRentEnabled));
    const [heroUrl, setHeroUrl] = useState(heroImageUrl || null);
    const facilityPhotoRefs = useRef({});
    const heroFileRef = useRef(null);

    const [draftFacilities, setDraftFacilities] = useState(() =>
        (facilities || []).map((f) => ({
            id: f.id,
            name: f.name,
            is_rentable: Boolean(f.is_rentable),
            photoUrl: f.photoUrl || null,
            sports: Array.isArray(f.sports) ? f.sports : [],
        })),
    );

    const [draftRules, setDraftRules] = useState(() => {
        const map = {};
        (facilities || []).forEach((f) => {
            const existing = (rentalRules || []).find(
                (r) => r.facilityId === f.id || r.facility_id === f.id,
            );
            map[f.id] = normalizeRule(existing, f.id);
            if (!existing && f.is_rentable) {
                map[f.id] = { ...map[f.id], enabled: true };
            }
        });
        return map;
    });

    const publicUrl = currentClub?.slug
        ? `${typeof window !== 'undefined' ? window.location.origin : ''}/rent/${currentClub.slug}`
        : '/rent/{slug}';

    const bookingsLabel =
        uncollectedCount > 0 ? `Κρατήσεις (${uncollectedCount})` : 'Κρατήσεις';

    useEffect(() => {
        if (!facilityId && facilities?.[0]?.id) {
            setFacilityId(facilities[0].id);
        }
    }, [facilities, facilityId]);

    const selectedFacility = useMemo(
        () => (facilities || []).find((f) => f.id === facilityId) || null,
        [facilities, facilityId],
    );

    const occupancySource = useMemo(
        () => ({
            ...(occupancy || {}),
            rentalRules: rentalRules || occupancy?.rentalRules || [],
        }),
        [occupancy, rentalRules],
    );

    const patchRule = (facilityId, patch) => {
        setDraftRules((prev) => ({
            ...prev,
            [facilityId]: normalizeRule({ ...prev[facilityId], ...patch }, facilityId),
        }));
    };

    const patchWindow = (facilityId, patch) => {
        const rule = draftRules[facilityId] || defaultRule(facilityId);
        const window = { ...(rule.windows?.[0] || defaultRule(facilityId).windows[0]), ...patch };
        patchRule(facilityId, { windows: [window] });
    };

    const toggleDay = (facilityId, day) => {
        const rule = draftRules[facilityId] || defaultRule(facilityId);
        const window = rule.windows?.[0] || defaultRule(facilityId).windows[0];
        const days = window.days.includes(day)
            ? window.days.filter((d) => d !== day)
            : [...window.days, day];
        patchWindow(facilityId, { days });
    };

    const setFacilityPhoto = (facilityId, photoUrl) => {
        setDraftFacilities((prev) =>
            prev.map((f) => (f.id === facilityId ? { ...f, photoUrl } : f)),
        );
    };

    const handleFacilityPhoto = async (facilityId, event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        try {
            const dataUrl = await readImageFile(file);
            setFacilityPhoto(facilityId, dataUrl);
            setMessage('Η φωτογραφία ενημερώθηκε — πατήστε Αποθήκευση διαθεσιμότητας.');
        } catch {
            setMessage('Αποτυχία φόρτωσης φωτογραφίας.');
        }
    };

    const handleHeroFile = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        try {
            const dataUrl = await readImageFile(file);
            setHeroUrl(dataUrl);
            setMessage('Η φωτογραφία δημόσιου link ενημερώθηκε — πατήστε Αποθήκευση διαθεσιμότητας.');
        } catch {
            setMessage('Αποτυχία φόρτωσης φωτογραφίας.');
        }
    };

    const saveAvailability = () => {
        setSaving(true);
        setMessage('');
        const rules = draftFacilities.map((f) => normalizeRule(draftRules[f.id], f.id));
        router.post(
            route(routes.saveAvailability || 'admin.rentals.save-availability'),
            {
                public_enabled: publicEnabled,
                notes,
                hero_image_url: heroUrl,
                rules,
                facilities: draftFacilities.map((f) => ({
                    id: f.id,
                    photoUrl: f.photoUrl,
                    is_rentable: Boolean(draftRules[f.id]?.enabled ?? f.is_rentable),
                    sports: f.sports,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => setMessage(''),
            },
        );
    };

    const selectedRule = selectedFacility
        ? ruleForFacility(rentalRules, selectedFacility.id)
        : null;

    const slots = useMemo(
        () =>
            selectedFacility
                ? listRentalSlots(occupancySource, selectedFacility, bookDate, courtShare)
                : [],
        [occupancySource, selectedFacility, bookDate, courtShare],
    );

    const occupied = useMemo(
        () =>
            selectedFacility
                ? occupancyForDate(occupancySource, selectedFacility, bookDate)
                : [],
        [occupancySource, selectedFacility, bookDate],
    );

    const baseAmount =
        selectedSlot && selectedRule
            ? bookingAmount(
                  selectedRule,
                  selectedSlot.startTime,
                  selectedSlot.endTime,
                  courtShare,
              ) + lockerRoomFeeAmount(selectedRule, useLockerRoom)
            : 0;
    const discountValue = Math.max(0, Number(String(specialDiscount).replace(',', '.')) || 0);
    const payableAmount = Math.max(0, Math.round((baseAmount - discountValue) * 100) / 100);

    const uncollectedTotal = (uncollected || []).reduce(
        (sum, row) => sum + (Number(row.amount) || 0),
        0,
    );

    const submitBooking = () => {
        if (!selectedFacility || !selectedSlot) {
            setMessage('Επιλέξτε γήπεδο και διαθέσιμη ώρα.');
            return;
        }
        if (!customerLastName.trim() || !customerFirstName.trim()) {
            setMessage('Συμπληρώστε επώνυμο και όνομα.');
            return;
        }
        setBookingBusy(true);
        setMessage('');
        router.post(
            route(routes.book || 'admin.rentals.book'),
            {
                facility_id: selectedFacility.id,
                date: bookDate,
                start_time: selectedSlot.startTime,
                end_time: selectedSlot.endTime,
                court_share: courtShare,
                use_locker_room: useLockerRoom,
                customer_last_name: customerLastName.trim(),
                customer_first_name: customerFirstName.trim(),
                customer_phone: customerPhone.trim() || null,
                customer_email: customerEmail.trim() || null,
                notes: bookingNotes.trim() || null,
                special_discount: discountValue,
                paid_now: paidNow,
                payment_method: paidNow ? paidNowMethod : null,
                amount: payableAmount,
                recurring,
                until_date: recurring ? untilDate || null : null,
                rec_weekdays: recurring ? recWeekdays : [],
            },
            {
                preserveScroll: true,
                onFinish: () => setBookingBusy(false),
                onSuccess: () => {
                    setSelectedSlot(null);
                    setCustomerLastName('');
                    setCustomerFirstName('');
                    setCustomerPhone('');
                    setCustomerEmail('');
                    setBookingNotes('');
                    setSpecialDiscount('');
                    setPaidNow(false);
                    setRecurring(false);
                    setTab('bookings');
                },
            },
        );
    };

    const collectPayment = (booking) => {
        router.post(
            route(routes.collect || 'admin.rentals.collect', booking.id),
            { payment_method: collectMethod },
            {
                preserveScroll: true,
                onSuccess: () => setCollectBooking(null),
            },
        );
    };

    const cancelBooking = (booking) => {
        if (!confirm(`Ακύρωση κράτησης «${booking.customerName}»;`)) {
            return;
        }
        router.post(
            route(routes.cancel || 'admin.rentals.cancel', booking.id),
            {},
            { preserveScroll: true },
        );
    };

    const deleteBooking = (booking) => {
        if (
            !confirm(
                `Οριστική διαγραφή κράτησης «${booking.customerName}» (${formatBookingDate(booking.date)});`,
            )
        ) {
            return;
        }
        router.delete(route(routes.destroy || 'admin.rentals.destroy', booking.id), {
            preserveScroll: true,
        });
    };

    const tabs = useMemo(
        () => [
            ['link', 'Δημόσιο link'],
            ['availability', 'Διαθεσιμότητα'],
            ['bookings', bookingsLabel],
        ],
        [bookingsLabel],
    );

    return (
        <AdminLayout title={title}>
            <Head title={title} />
            {flash?.success && (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}
            {message ? (
                <div className="mb-4 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                    {message}
                </div>
            ) : null}

            <div className="mb-4">
                <h2 className="text-xl font-semibold text-ts-fg">{title}</h2>
                {subtitle ? <p className="mt-1 max-w-4xl text-sm text-ts-muted">{subtitle}</p> : null}
            </div>

            <div className="mb-4 flex gap-1 rounded-md border border-ts-border p-0.5 w-fit">
                {tabs.map(([id, label]) => (
                    <button
                        key={id}
                        type="button"
                        onClick={() => setTab(id)}
                        className={`rounded px-3 py-1.5 text-sm ${
                            tab === id
                                ? 'bg-ts-accent text-ts-accent-fg'
                                : 'text-ts-fg-soft hover:bg-ts-hover'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'link' && (
                <section className="space-y-4 rounded-lg border border-ts-border bg-ts-surface p-4">
                    <label className="flex items-center gap-2 text-sm text-ts-fg">
                        <input
                            type="checkbox"
                            checked={publicEnabled}
                            onChange={(e) => setPublicEnabled(e.target.checked)}
                        />
                        Δημόσιο link ενεργό
                    </label>
                    <div className="flex flex-wrap gap-2">
                        <code className="rounded border border-ts-border bg-ts-bg px-3 py-2 text-sm text-ts-accent">
                            {publicUrl}
                        </code>
                        <button
                            type="button"
                            className={btnSecondary}
                            onClick={() => navigator.clipboard?.writeText(publicUrl)}
                        >
                            Αντιγραφή
                        </button>
                    </div>
                    <div className="flex flex-wrap items-start gap-4">
                        <div className="flex h-28 w-44 items-center justify-center overflow-hidden rounded border border-ts-border bg-ts-bg text-xs text-ts-muted">
                            {heroUrl ? (
                                <img src={heroUrl} alt="" className="h-full w-full object-cover" />
                            ) : (
                                <span>Χωρίς φωτογραφία κεφαλίδας</span>
                            )}
                        </div>
                        <div className="space-y-2">
                            <input
                                ref={heroFileRef}
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                hidden
                                onChange={(e) => void handleHeroFile(e)}
                            />
                            <button
                                type="button"
                                className={btnSecondary}
                                onClick={() => heroFileRef.current?.click()}
                            >
                                Φωτογραφία δημόσιου link
                            </button>
                            {heroUrl ? (
                                <button
                                    type="button"
                                    className={btnSecondary}
                                    onClick={() => {
                                        setHeroUrl(null);
                                        setMessage(
                                            'Η φωτογραφία αφαιρέθηκε — πατήστε Αποθήκευση διαθεσιμότητας.',
                                        );
                                    }}
                                >
                                    Αφαίρεση
                                </button>
                            ) : null}
                        </div>
                    </div>
                    <label className="block text-xs text-ts-muted">
                        Σημείωση στο δημόσιο link
                        <input
                            className={`${field} mt-1`}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="π.χ. Ελάχιστη διάρκεια 1 ώρα. Πληρωμή στη γραμματεία."
                        />
                    </label>
                    <button type="button" className={btnPrimary} disabled={saving} onClick={saveAvailability}>
                        {saving ? 'Αποθήκευση…' : 'Αποθήκευση'}
                    </button>
                </section>
            )}

            {tab === 'availability' && (
                <section className="space-y-4">
                    {draftFacilities.length === 0 ? (
                        <div className="rounded-lg border border-ts-border bg-ts-surface p-4 text-sm text-ts-muted">
                            Δεν υπάρχουν ενεργά γήπεδα. Πρόσθεσε εγκαταστάσεις από Ρυθμίσεις → Γήπεδο.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {draftFacilities.map((facility) => {
                                const rule = draftRules[facility.id] || defaultRule(facility.id);
                                const window = rule.windows?.[0] || defaultRule(facility.id).windows[0];

                                return (
                                    <article
                                        key={facility.id}
                                        className="rounded-lg border border-ts-border bg-ts-surface p-4 space-y-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <label className="flex items-center gap-2 text-sm text-ts-fg">
                                                <input
                                                    type="checkbox"
                                                    checked={rule.enabled}
                                                    onChange={(e) =>
                                                        patchRule(facility.id, {
                                                            enabled: e.target.checked,
                                                        })
                                                    }
                                                />
                                                <strong>{facility.name}</strong>
                                            </label>
                                            <span className="text-xs text-ts-muted">
                                                {(facility.sports || []).join(', ') || '—'}
                                            </span>
                                        </div>

                                        <div className="flex flex-wrap items-start gap-4">
                                            <div className="flex h-24 w-36 items-center justify-center overflow-hidden rounded border border-ts-border bg-ts-bg text-xs text-ts-muted">
                                                {facility.photoUrl ? (
                                                    <img
                                                        src={facility.photoUrl}
                                                        alt={facility.name}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <span>Χωρίς φωτο</span>
                                                )}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <input
                                                    ref={(el) => {
                                                        facilityPhotoRefs.current[facility.id] = el;
                                                    }}
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    hidden
                                                    onChange={(e) =>
                                                        void handleFacilityPhoto(facility.id, e)
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    className={btnSecondary}
                                                    onClick={() =>
                                                        facilityPhotoRefs.current[facility.id]?.click()
                                                    }
                                                >
                                                    Φωτογραφία γηπέδου
                                                </button>
                                                {facility.photoUrl ? (
                                                    <button
                                                        type="button"
                                                        className={btnSecondary}
                                                        onClick={() => {
                                                            setFacilityPhoto(facility.id, null);
                                                            setMessage(
                                                                'Η φωτογραφία αφαιρέθηκε — πατήστε Αποθήκευση διαθεσιμότητας.',
                                                            );
                                                        }}
                                                    >
                                                        Αφαίρεση
                                                    </button>
                                                ) : null}
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            {WEEKDAYS.map((day) => {
                                                const on = window.days.includes(day.value);
                                                return (
                                                    <button
                                                        key={day.value}
                                                        type="button"
                                                        disabled={!rule.enabled}
                                                        onClick={() => toggleDay(facility.id, day.value)}
                                                        className={`flex h-9 w-9 items-center justify-center rounded-full text-xs font-medium disabled:opacity-40 ${
                                                            on
                                                                ? 'bg-ts-accent text-ts-accent-fg'
                                                                : 'border border-ts-border text-ts-fg-soft'
                                                        }`}
                                                    >
                                                        {day.label}
                                                    </button>
                                                );
                                            })}
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                                            <label className="block text-xs text-ts-muted">
                                                Από
                                                <input
                                                    type="time"
                                                    className={`${field} mt-1`}
                                                    value={window.startTime}
                                                    disabled={!rule.enabled}
                                                    onChange={(e) =>
                                                        patchWindow(facility.id, {
                                                            startTime: e.target.value,
                                                        })
                                                    }
                                                />
                                            </label>
                                            <label className="block text-xs text-ts-muted">
                                                Έως
                                                <input
                                                    type="time"
                                                    className={`${field} mt-1`}
                                                    value={
                                                        window.endTime === '00:00'
                                                            ? '23:59'
                                                            : window.endTime
                                                    }
                                                    disabled={!rule.enabled}
                                                    onChange={(e) =>
                                                        patchWindow(facility.id, {
                                                            endTime: e.target.value || '00:00',
                                                        })
                                                    }
                                                />
                                            </label>
                                            <label className="block text-xs text-ts-muted">
                                                Διάρκεια
                                                <select
                                                    className={`${field} mt-1`}
                                                    value={rule.slotMinutes}
                                                    disabled={!rule.enabled}
                                                    onChange={(e) =>
                                                        patchRule(facility.id, {
                                                            slotMinutes: Number(e.target.value) || 60,
                                                        })
                                                    }
                                                >
                                                    {SLOT_OPTIONS.map((n) => (
                                                        <option key={n} value={n}>
                                                            {n} λεπτά
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label className="block text-xs text-ts-muted">
                                                €/ώρα ολόκληρο
                                                <input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    className={`${field} mt-1`}
                                                    value={rule.hourlyRateFull || ''}
                                                    disabled={!rule.enabled}
                                                    onChange={(e) => {
                                                        const full = Number(e.target.value) || 0;
                                                        patchRule(facility.id, {
                                                            hourlyRateFull: full,
                                                            hourlyRate: full,
                                                        });
                                                    }}
                                                />
                                            </label>
                                            <label className="block text-xs text-ts-muted">
                                                €/ώρα μισό
                                                <input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    className={`${field} mt-1`}
                                                    value={rule.hourlyRateHalf || ''}
                                                    disabled={!rule.enabled}
                                                    onChange={(e) =>
                                                        patchRule(facility.id, {
                                                            hourlyRateHalf: Number(e.target.value) || 0,
                                                        })
                                                    }
                                                />
                                            </label>
                                            <label className="flex items-end gap-2 pb-1.5 text-sm text-ts-fg">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(rule.lockerRoomAvailable)}
                                                    disabled={!rule.enabled}
                                                    onChange={(e) =>
                                                        patchRule(facility.id, {
                                                            lockerRoomAvailable: e.target.checked,
                                                        })
                                                    }
                                                />
                                                Χρήση αποδυτηρίου
                                            </label>
                                            <label className="block text-xs text-ts-muted">
                                                € αποδυτήριο
                                                <input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    className={`${field} mt-1`}
                                                    value={rule.lockerRoomFee || ''}
                                                    disabled={!rule.enabled || !rule.lockerRoomAvailable}
                                                    onChange={(e) =>
                                                        patchRule(facility.id, {
                                                            lockerRoomFee: Number(e.target.value) || 0,
                                                        })
                                                    }
                                                />
                                            </label>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    <div>
                        <button
                            type="button"
                            className={btnPrimary}
                            disabled={saving || draftFacilities.length === 0}
                            onClick={saveAvailability}
                        >
                            {saving ? 'Αποθήκευση…' : 'Αποθήκευση διαθεσιμότητας'}
                        </button>
                    </div>
                </section>
            )}

            {tab === 'bookings' && (
                <section className="space-y-5">
                    {(uncollected || []).length > 0 && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            <p className="mb-2">
                                <strong>
                                    {(uncollected || []).length === 1
                                        ? '1 εκκρεμής είσπραξη'
                                        : `${(uncollected || []).length} εκκρεμείς εισπράξεις`}
                                </strong>
                                {' · '}
                                {formatCurrency(uncollectedTotal)} χωρίς να έχουν μπει στα έσοδα
                            </p>
                            <ul className="space-y-2">
                                {(uncollected || []).slice(0, 8).map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span>
                                            {formatBookingDate(row.date)} {row.startTime}–
                                            {row.endTime} ·{' '}
                                            {row.facilityName} · {row.customerName} ·{' '}
                                            {formatCurrency(row.amount)}
                                        </span>
                                        <button
                                            type="button"
                                            className={btnSecondary}
                                            onClick={() => {
                                                setCollectMethod('cash');
                                                setCollectBooking(row);
                                            }}
                                        >
                                            Είσπραξη
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div>
                        <h3 className="mb-3 text-base font-semibold text-ts-fg">
                            Καταχώρηση κράτησης
                        </h3>

                        <div className="mb-4 flex flex-wrap gap-1.5">
                            {nextDays(14).map((iso) => (
                                <button
                                    key={iso}
                                    type="button"
                                    onClick={() => {
                                        setBookDate(iso);
                                        setSelectedSlot(null);
                                    }}
                                    className={`rounded-md px-2.5 py-1.5 text-xs font-medium ${
                                        bookDate === iso
                                            ? 'bg-amber-300 text-amber-950'
                                            : 'border border-ts-border text-ts-fg-soft hover:bg-ts-hover'
                                    }`}
                                >
                                    {formatDayChip(iso)}
                                </button>
                            ))}
                        </div>

                        <div className="mb-4 grid gap-3 lg:grid-cols-[1fr_1fr_auto_140px]">
                            <label className="block text-xs text-ts-muted">
                                Γήπεδο
                                <select
                                    className={`${field} mt-1`}
                                    value={facilityId}
                                    onChange={(e) => {
                                        setFacilityId(e.target.value);
                                        setSelectedSlot(null);
                                        setUseLockerRoom(false);
                                    }}
                                >
                                    {(facilities || []).map((f) => (
                                        <option key={f.id} value={f.id}>
                                            {f.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div>
                                <div className="mb-1 text-xs text-ts-muted">Τμήμα γηπέδου</div>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        className={`rounded-md px-3 py-1.5 text-sm ${
                                            courtShare === 'full'
                                                ? 'bg-amber-300 text-amber-950'
                                                : 'border border-ts-border text-ts-fg-soft'
                                        }`}
                                        onClick={() => {
                                            setCourtShare('full');
                                            setSelectedSlot(null);
                                        }}
                                    >
                                        Ολόκληρο
                                        {selectedRule?.hourlyRateFull
                                            ? ` · ${formatCurrency(selectedRule.hourlyRateFull)}`
                                            : ''}
                                    </button>
                                    <button
                                        type="button"
                                        className={`rounded-md px-3 py-1.5 text-sm ${
                                            courtShare === 'half'
                                                ? 'bg-amber-300 text-amber-950'
                                                : 'border border-ts-border text-ts-fg-soft'
                                        }`}
                                        onClick={() => {
                                            setCourtShare('half');
                                            setSelectedSlot(null);
                                        }}
                                    >
                                        Μισό
                                        {selectedRule?.hourlyRateHalf
                                            ? ` · ${formatCurrency(selectedRule.hourlyRateHalf)}`
                                            : ''}
                                    </button>
                                </div>
                            </div>
                            {selectedRule?.lockerRoomAvailable ? (
                                <label className="flex items-end gap-2 pb-1.5 text-sm text-ts-fg">
                                    <input
                                        type="checkbox"
                                        checked={useLockerRoom}
                                        onChange={(e) => setUseLockerRoom(e.target.checked)}
                                    />
                                    Χρήση αποδυτηρίου
                                    {selectedRule.lockerRoomFee
                                        ? ` · +${formatCurrency(selectedRule.lockerRoomFee)}`
                                        : ''}
                                </label>
                            ) : (
                                <div />
                            )}
                            <div className="rounded-md border border-ts-border bg-ts-bg px-3 py-2 text-right">
                                <div className="text-[11px] uppercase text-ts-muted">Σύνολο</div>
                                <div className="text-lg font-semibold text-ts-fg">
                                    {selectedSlot && selectedRule
                                        ? formatCurrency(payableAmount)
                                        : '—'}
                                </div>
                            </div>
                        </div>

                        <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label className="block text-xs text-ts-muted">
                                Επώνυμο
                                <input
                                    className={`${field} mt-1`}
                                    value={customerLastName}
                                    onChange={(e) => setCustomerLastName(e.target.value)}
                                />
                            </label>
                            <label className="block text-xs text-ts-muted">
                                Όνομα
                                <input
                                    className={`${field} mt-1`}
                                    value={customerFirstName}
                                    onChange={(e) => setCustomerFirstName(e.target.value)}
                                />
                            </label>
                            <label className="block text-xs text-ts-muted">
                                Τηλέφωνο
                                <input
                                    className={`${field} mt-1`}
                                    value={customerPhone}
                                    onChange={(e) => setCustomerPhone(e.target.value)}
                                />
                            </label>
                            <label className="block text-xs text-ts-muted">
                                Email
                                <input
                                    className={`${field} mt-1`}
                                    type="email"
                                    value={customerEmail}
                                    onChange={(e) => setCustomerEmail(e.target.value)}
                                />
                            </label>
                        </div>

                        <div className="mb-3 grid gap-3 lg:grid-cols-[1fr_180px]">
                            <div>
                                <div className="mb-1 text-xs text-ts-muted">Διαθέσιμες ώρες</div>
                                {slots.filter((s) => s.available).length === 0 ? (
                                    <p className="text-sm text-ts-muted">
                                        Δεν υπάρχουν ελεύθερες ώρες ενοικίασης για αυτή την
                                        ημερομηνία.
                                    </p>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {slots.map((slot) => {
                                            const on =
                                                selectedSlot?.startTime === slot.startTime &&
                                                selectedSlot?.endTime === slot.endTime;
                                            return (
                                                <button
                                                    key={`${slot.startTime}-${slot.endTime}`}
                                                    type="button"
                                                    disabled={!slot.available}
                                                    title={slot.available ? '' : slot.reason}
                                                    onClick={() =>
                                                        setSelectedSlot({
                                                            startTime: slot.startTime,
                                                            endTime: slot.endTime,
                                                        })
                                                    }
                                                    className={`rounded-md px-2.5 py-1.5 text-xs ${
                                                        on
                                                            ? 'bg-amber-300 text-amber-950'
                                                            : slot.available
                                                              ? 'border border-ts-border text-ts-fg-soft hover:bg-ts-hover'
                                                              : 'border border-ts-border/50 text-ts-muted opacity-50'
                                                    }`}
                                                >
                                                    {slot.startTime}–{slot.endTime}
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                            <label className="block text-xs text-ts-muted">
                                Ειδική έκπτωση (€)
                                <input
                                    className={`${field} mt-1`}
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={specialDiscount}
                                    onChange={(e) => setSpecialDiscount(e.target.value)}
                                    placeholder="π.χ. 10"
                                />
                            </label>
                        </div>

                        {occupied.length > 0 && (
                            <p className="mb-3 text-xs text-ts-muted">
                                Κλειστά λόγω προγράμματος:{' '}
                                {occupied
                                    .map((b) => {
                                        const hh = String(Math.floor(b.startMin / 60)).padStart(
                                            2,
                                            '0',
                                        );
                                        const mm = String(b.startMin % 60).padStart(2, '0');
                                        return `${b.reason} ${hh}:${mm}`;
                                    })
                                    .join(' · ')}
                            </p>
                        )}

                        <label className="mb-3 block text-xs text-ts-muted">
                            Σημείωση κράτησης
                            <input
                                className={`${field} mt-1`}
                                value={bookingNotes}
                                onChange={(e) => setBookingNotes(e.target.value)}
                            />
                        </label>

                        <fieldset className="mb-3">
                            <legend className="mb-1 text-xs text-ts-muted">Πληρωμή με</legend>
                            <div className="flex flex-wrap gap-4 text-sm text-ts-fg">
                                {[
                                    ['cash', 'Μετρητά'],
                                    ['card', 'POS'],
                                    ['transfer', 'Τραπεζική κατάθεση'],
                                ].map(([value, label]) => (
                                    <label key={value} className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={paidNow && paidNowMethod === value}
                                            onChange={(e) => {
                                                if (e.target.checked) {
                                                    setPaidNow(true);
                                                    setPaidNowMethod(value);
                                                } else if (paidNowMethod === value) {
                                                    setPaidNow(false);
                                                }
                                            }}
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            <p className="mt-1 text-xs text-ts-muted">
                                Εμφάνιση στα ημερήσια έσοδα όταν επιλεγεί τρόπος πληρωμής.
                            </p>
                        </fieldset>

                        <label className="mb-3 flex items-center gap-2 text-sm text-ts-fg">
                            <input
                                type="checkbox"
                                checked={recurring}
                                onChange={(e) => {
                                    const on = e.target.checked;
                                    setRecurring(on);
                                    if (on) {
                                        const start = new Date(`${bookDate}T12:00:00`);
                                        const end = new Date(start);
                                        end.setDate(end.getDate() + 56);
                                        setUntilDate(localDateIso(end));
                                        setRecWeekdays([start.getDay()]);
                                    }
                                }}
                            />
                            Επαναλαμβανόμενη κράτηση (ίδια ώρα κάθε εβδομάδα)
                        </label>

                        {recurring && (
                            <div className="mb-3 flex flex-wrap items-end gap-3 rounded-md border border-ts-border bg-ts-bg p-3">
                                <label className="block text-xs text-ts-muted">
                                    Μέχρι
                                    <input
                                        className={`${field} mt-1`}
                                        type="date"
                                        min={bookDate}
                                        value={untilDate}
                                        onChange={(e) => setUntilDate(e.target.value)}
                                    />
                                </label>
                                <div>
                                    <div className="mb-1 text-xs text-ts-muted">Ημέρες</div>
                                    <div className="flex flex-wrap gap-1">
                                        {WEEKDAYS.map((day) => {
                                            const on = recWeekdays.includes(day.value);
                                            return (
                                                <button
                                                    key={day.value}
                                                    type="button"
                                                    className={`flex h-8 w-8 items-center justify-center rounded-full text-xs ${
                                                        on
                                                            ? 'bg-ts-accent text-ts-accent-fg'
                                                            : 'border border-ts-border text-ts-fg-soft'
                                                    }`}
                                                    onClick={() =>
                                                        setRecWeekdays((prev) =>
                                                            prev.includes(day.value)
                                                                ? prev.filter((d) => d !== day.value)
                                                                : [...prev, day.value].sort(
                                                                      (a, b) => a - b,
                                                                  ),
                                                        )
                                                    }
                                                >
                                                    {day.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        )}

                        <button
                            type="button"
                            className={btnPrimary}
                            disabled={bookingBusy}
                            onClick={submitBooking}
                        >
                            {bookingBusy
                                ? 'Καταχώρηση…'
                                : recurring
                                  ? 'Καταχώρηση σειράς'
                                  : 'Καταχώρηση κράτησης'}
                        </button>
                    </div>

                    <div className="space-y-8">
                        <div>
                            <h3 className="mb-3 text-base font-semibold text-ts-fg">
                                Επερχόμενες κρατήσεις
                            </h3>
                            <BookingsTable
                                rows={upcomingBookings || []}
                                emptyLabel="Δεν υπάρχουν επερχόμενες κρατήσεις."
                                allowActions
                                canDelete={canDeleteBookings}
                                onCollect={(row) => {
                                    setCollectMethod('cash');
                                    setCollectBooking(row);
                                }}
                                onCancel={cancelBooking}
                                onDelete={deleteBooking}
                            />
                        </div>

                        <div>
                            <h3 className="mb-3 text-base font-semibold text-ts-fg">
                                Προγενέστερες κρατήσεις
                            </h3>
                            <BookingsTable
                                rows={pastBookings || []}
                                emptyLabel="Δεν υπάρχουν προγενέστερες κρατήσεις."
                                allowActions={false}
                                canDelete={canDeleteBookings}
                                onDelete={deleteBooking}
                            />
                        </div>
                    </div>

                    {collectBooking && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                            <div className="w-full max-w-md rounded-lg border border-ts-border bg-ts-surface p-4 shadow-xl">
                                <h4 className="mb-2 text-base font-semibold text-ts-fg">
                                    Είσπραξη κράτησης
                                </h4>
                                <p className="mb-3 text-sm text-ts-muted">
                                    {collectBooking.customerName} ·{' '}
                                    {formatBookingDate(collectBooking.date)}{' '}
                                    {collectBooking.startTime}–{collectBooking.endTime} ·{' '}
                                    {formatCurrency(collectBooking.amount)}
                                </p>
                                <div className="mb-4 flex flex-wrap gap-3 text-sm">
                                    {[
                                        ['cash', 'Μετρητά'],
                                        ['card', 'POS'],
                                        ['transfer', 'Τραπεζική κατάθεση'],
                                    ].map(([value, label]) => (
                                        <label key={value} className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="collect-method"
                                                checked={collectMethod === value}
                                                onChange={() => setCollectMethod(value)}
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        className={btnSecondary}
                                        onClick={() => setCollectBooking(null)}
                                    >
                                        Άκυρο
                                    </button>
                                    <button
                                        type="button"
                                        className={btnPrimary}
                                        onClick={() => collectPayment(collectBooking)}
                                    >
                                        Είσπραξη
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </section>
            )}
        </AdminLayout>
    );
}
