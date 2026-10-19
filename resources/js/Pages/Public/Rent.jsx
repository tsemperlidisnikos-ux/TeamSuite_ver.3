import {
    PublicClubLoading,
    PublicClubMessage,
    PublicClubShell,
} from '@/Components/PublicClubShell';
import { formatCurrency } from '@/lib/portalFormat';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function localDateIso(date = new Date()) {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function formatDayChip(iso) {
    const d = new Date(`${iso}T12:00:00`);
    const name = d.toLocaleDateString('el-GR', { weekday: 'short' });
    return `${name} ${d.getDate()}/${d.getMonth() + 1}`;
}

function minutesOf(time) {
    const [h, m] = String(time || '0:0')
        .split(':')
        .map((n) => Number(n) || 0);
    return h * 60 + m;
}

function formatMinutes(total) {
    const normalized = ((total % (24 * 60)) + 24 * 60) % (24 * 60);
    const h = Math.floor(normalized / 60);
    const m = normalized % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

function windowEndMinutes(startMin, endTime) {
    let endMin = minutesOf(endTime);
    if (endTime === '00:00' || endMin <= startMin) {
        endMin += 24 * 60;
    }
    return endMin;
}

function weekdayOfIsoDate(iso) {
    return new Date(`${iso}T12:00:00`).getDay();
}

function listSlotsForFacility(facility, priceRow, date) {
    const windows = priceRow?.windows?.length
        ? priceRow.windows
        : facility?.windows?.length
          ? facility.windows
          : [{ days: [1, 2, 3, 4, 5, 6, 0], startTime: '18:00', endTime: '22:00' }];
    const slotMinutes = Number(priceRow?.slotMinutes || facility?.slotMinutes || 60) || 60;
    const day = weekdayOfIsoDate(date);
    const slots = [];
    const seen = new Set();

    for (const window of windows) {
        if (!Array.isArray(window.days) || !window.days.includes(day)) {
            continue;
        }
        const startMin = minutesOf(window.startTime);
        const endMin = windowEndMinutes(startMin, window.endTime);
        for (let t = startMin; t + slotMinutes <= endMin; t += slotMinutes) {
            const startTime = formatMinutes(t);
            const endTime = formatMinutes(t + slotMinutes);
            const key = `${startTime}-${endTime}`;
            if (seen.has(key)) {
                continue;
            }
            seen.add(key);
            slots.push({ startTime, endTime, available: true });
        }
    }

    return slots.sort((a, b) => a.startTime.localeCompare(b.startTime));
}

function bookingHours(startTime, endTime) {
    const start = minutesOf(startTime);
    let end = minutesOf(endTime);
    if (end <= start) {
        end += 24 * 60;
    }
    return Math.max(0, (end - start) / 60);
}

function chipClass(active) {
    return active
        ? 'rounded-md border border-sky-600 bg-sky-600 px-3 py-2 text-sm font-medium text-white'
        : 'rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 hover:border-sky-300';
}

export default function Rent({
    club,
    facilities = [],
    prices = [],
    publicEnabled = false,
    payOnline = false,
    loading = false,
}) {
    const flash = usePage().props.flash;
    const [date, setDate] = useState(localDateIso());
    const [facilityId, setFacilityId] = useState(facilities[0]?.id || '');
    const [slot, setSlot] = useState(null);
    const [clientError, setClientError] = useState('');

    const form = useForm({
        facility_id: facilities[0]?.id || '',
        customer_last_name: '',
        customer_first_name: '',
        customer_email: '',
        customer_phone: '',
        starts_at: '',
        ends_at: '',
        court_share: 'full',
        use_locker_room: false,
        notes: '',
        amount: 0,
        pay_online: false,
    });

    useEffect(() => {
        if (!facilityId && facilities[0]?.id) {
            setFacilityId(facilities[0].id);
            form.setData('facility_id', facilities[0].id);
        }
    }, [facilities, facilityId]);

    const days = useMemo(() => {
        const today = new Date();
        today.setHours(12, 0, 0, 0);
        return Array.from({ length: 14 }, (_, i) => {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            return localDateIso(d);
        });
    }, []);

    const selectedFacility = facilities.find((f) => f.id === facilityId) ?? null;
    const priceRow = prices.find((p) => p.facilityId === facilityId) ?? null;
    const lockerOffered = Boolean(priceRow?.lockerRoomAvailable);
    const lockerFee = lockerOffered ? Number(priceRow?.lockerRoomFee) || 0 : 0;

    const slots = useMemo(() => {
        if (!selectedFacility) {
            return [];
        }
        return listSlotsForFacility(selectedFacility, priceRow, date);
    }, [selectedFacility, priceRow, date]);

    const hourlyRate =
        form.data.court_share === 'half'
            ? Number(priceRow?.hourlyRateHalf) || 0
            : Number(priceRow?.hourlyRateFull) || 0;

    const amount = useMemo(() => {
        if (!slot) {
            return hourlyRate + (form.data.use_locker_room ? lockerFee : 0);
        }
        const hours = bookingHours(slot.startTime, slot.endTime);
        const base = Math.round(hours * hourlyRate * 100) / 100;
        return Math.round((base + (form.data.use_locker_room ? lockerFee : 0)) * 100) / 100;
    }, [slot, hourlyRate, form.data.use_locker_room, lockerFee]);

    const inputClass =
        'w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900';

    const hero = club?.heroImageUrl || club?.logo_url;

    if (loading || !club) {
        return <PublicClubLoading message="Φόρτωση..." />;
    }

    function selectFacility(id) {
        setFacilityId(id);
        setSlot(null);
        form.setData({
            ...form.data,
            facility_id: id,
            use_locker_room: false,
            starts_at: '',
            ends_at: '',
        });
    }

    function selectSlot(item) {
        setSlot(item);
        form.setData({
            ...form.data,
            starts_at: `${date}T${item.startTime}`,
            ends_at: `${date}T${item.endTime}`,
        });
    }

    function submitBooking(event, online = false) {
        event.preventDefault();
        setClientError('');

        if (!facilityId || !slot) {
            setClientError('Επιλέξτε γήπεδο και διαθέσιμη ώρα.');
            return;
        }
        if (
            form.data.customer_last_name.trim().length < 2 ||
            form.data.customer_first_name.trim().length < 2
        ) {
            setClientError('Συμπληρώστε επώνυμο και όνομα.');
            return;
        }
        if (online && !String(form.data.customer_email || '').includes('@')) {
            setClientError('Για online πληρωμή συμπληρώστε email.');
            return;
        }

        form.transform((data) => ({
            ...data,
            facility_id: facilityId,
            starts_at: `${date}T${slot.startTime}`,
            ends_at: `${date}T${slot.endTime}`,
            amount,
            pay_online: online,
            use_locker_room: Boolean(data.use_locker_room),
        }));
        form.post(route('public.rent.store', club.slug));
    }

    if (!publicEnabled) {
        return (
            <PublicClubMessage
                title={club.name}
                message="Η δημόσια ενοικίαση δεν είναι ενεργή."
            />
        );
    }

    if (facilities.length === 0) {
        return (
            <PublicClubShell title={`Ενοικίαση — ${club.name}`}>
                <h1 className="text-2xl font-semibold">{club.name}</h1>
                <p className="mt-2 text-sm text-slate-500">Δεν έχουν δηλωθεί γήπεδα προς ενοικίαση.</p>
            </PublicClubShell>
        );
    }

    return (
        <div className="min-h-screen bg-slate-100 px-4 py-8 text-slate-900">
            <Head title={`Ενοικίαση — ${club.name}`} />
            <div className="mx-auto w-full max-w-xl space-y-4">
                <header className="overflow-hidden rounded-2xl bg-slate-500 text-white shadow-md">
                    {hero ? (
                        <img
                            src={hero}
                            alt={club.name}
                            className="max-h-56 w-full object-cover object-center bg-slate-600"
                        />
                    ) : (
                        <div className="flex h-32 items-center justify-center bg-slate-600 text-5xl font-semibold">
                            {club.name.slice(0, 1)}
                        </div>
                    )}
                    <div className="px-6 py-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-200">
                            Δημόσια κράτηση
                        </p>
                        <h1 className="text-2xl font-semibold">{club.name}</h1>
                    </div>
                </header>

                {club.notes ? (
                    <p className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                        {club.notes}
                    </p>
                ) : null}

                <div className="rounded-2xl border border-slate-200/80 bg-white px-6 py-6 shadow-sm">
                    {flash?.success && (
                        <div className="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form className="space-y-5" onSubmit={(e) => submitBooking(e, false)}>
                        <div>
                            <p className="mb-2 text-sm font-medium text-slate-700">Επιλογή τοποθεσίας</p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {facilities.map((f) => (
                                    <button
                                        key={f.id}
                                        type="button"
                                        className={
                                            facilityId === f.id
                                                ? 'flex items-center gap-3 rounded-xl border-2 border-sky-600 bg-sky-50 p-3 text-left'
                                                : 'flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left hover:border-sky-300'
                                        }
                                        onClick={() => selectFacility(f.id)}
                                    >
                                        {f.photoUrl ? (
                                            <img
                                                src={f.photoUrl}
                                                alt=""
                                                className="h-12 w-12 rounded-lg object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-200 text-lg font-semibold text-slate-600">
                                                {f.name.slice(0, 1)}
                                            </div>
                                        )}
                                        <span className="text-sm font-medium text-slate-900">{f.name}</span>
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <p className="mb-2 text-sm font-medium text-slate-700">Ημερομηνία</p>
                            <div className="flex gap-2 overflow-x-auto pb-1">
                                {days.map((iso) => (
                                    <button
                                        key={iso}
                                        type="button"
                                        className={`shrink-0 ${chipClass(date === iso)}`}
                                        onClick={() => {
                                            setDate(iso);
                                            setSlot(null);
                                            form.setData({
                                                ...form.data,
                                                starts_at: '',
                                                ends_at: '',
                                            });
                                        }}
                                    >
                                        {formatDayChip(iso)}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="mb-2 text-sm font-medium text-slate-700">Τμήμα γηπέδου</p>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        className={chipClass(form.data.court_share === 'full')}
                                        onClick={() => {
                                            form.setData('court_share', 'full');
                                            setSlot(null);
                                        }}
                                    >
                                        Ολόκληρο
                                        {priceRow?.hourlyRateFull
                                            ? ` · ${formatCurrency(priceRow.hourlyRateFull)}/ώρα`
                                            : ''}
                                    </button>
                                    <button
                                        type="button"
                                        className={chipClass(form.data.court_share === 'half')}
                                        onClick={() => {
                                            form.setData('court_share', 'half');
                                            setSlot(null);
                                        }}
                                    >
                                        Μισό
                                        {priceRow?.hourlyRateHalf
                                            ? ` · ${formatCurrency(priceRow.hourlyRateHalf)}/ώρα`
                                            : ''}
                                    </button>
                                </div>
                            </div>

                            {lockerOffered ? (
                                <div>
                                    <p className="mb-2 text-sm font-medium text-slate-700">
                                        Χρήση αποδυτηρίου
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className={chipClass(form.data.use_locker_room)}
                                            onClick={() => form.setData('use_locker_room', true)}
                                        >
                                            Ναι
                                            {lockerFee > 0 ? ` · +${formatCurrency(lockerFee)}` : ''}
                                        </button>
                                        <button
                                            type="button"
                                            className={chipClass(!form.data.use_locker_room)}
                                            onClick={() => form.setData('use_locker_room', false)}
                                        >
                                            Όχι
                                        </button>
                                    </div>
                                </div>
                            ) : null}

                            <div className={lockerOffered ? 'sm:col-span-2' : ''}>
                                <p className="mb-1 text-sm font-medium text-slate-700">Σύνολο</p>
                                <div className="text-2xl font-semibold text-slate-900">
                                    {formatCurrency(amount)}
                                </div>
                            </div>
                        </div>

                        <div>
                            <p className="mb-2 text-sm font-medium text-slate-700">Ώρα</p>
                            {slots.filter((s) => s.available).length === 0 ? (
                                <p className="text-sm text-slate-500">Δεν υπάρχουν διαθέσιμες ώρες.</p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {slots.map((item) => {
                                        const selected =
                                            slot?.startTime === item.startTime &&
                                            slot?.endTime === item.endTime;
                                        return (
                                            <button
                                                key={`${item.startTime}-${item.endTime}`}
                                                type="button"
                                                disabled={!item.available}
                                                className={
                                                    selected
                                                        ? chipClass(true)
                                                        : item.available
                                                          ? chipClass(false)
                                                          : 'cursor-not-allowed rounded-md border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-400'
                                                }
                                                onClick={() => selectSlot(item)}
                                            >
                                                {item.startTime}–{item.endTime}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="text-slate-600">Επώνυμο</span>
                                <input
                                    className={`mt-1 ${inputClass}`}
                                    required
                                    minLength={2}
                                    value={form.data.customer_last_name}
                                    onChange={(e) =>
                                        form.setData('customer_last_name', e.target.value)
                                    }
                                />
                            </label>
                            <label className="block text-sm">
                                <span className="text-slate-600">Όνομα</span>
                                <input
                                    className={`mt-1 ${inputClass}`}
                                    required
                                    minLength={2}
                                    value={form.data.customer_first_name}
                                    onChange={(e) =>
                                        form.setData('customer_first_name', e.target.value)
                                    }
                                />
                            </label>
                            <label className="block text-sm">
                                <span className="text-slate-600">
                                    Email{payOnline ? ' *' : ''}
                                </span>
                                <input
                                    type="email"
                                    className={`mt-1 ${inputClass}`}
                                    value={form.data.customer_email}
                                    onChange={(e) => form.setData('customer_email', e.target.value)}
                                />
                            </label>
                            <label className="block text-sm">
                                <span className="text-slate-600">Τηλέφωνο</span>
                                <input
                                    className={`mt-1 ${inputClass}`}
                                    required
                                    minLength={6}
                                    value={form.data.customer_phone}
                                    onChange={(e) => form.setData('customer_phone', e.target.value)}
                                />
                            </label>
                            <label className="block text-sm sm:col-span-2">
                                <span className="text-slate-600">Σημειώσεις</span>
                                <input
                                    className={`mt-1 ${inputClass}`}
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                            </label>
                        </div>

                        {(clientError || form.errors.facility_id || form.errors.starts_at) && (
                            <p className="text-sm text-rose-600">
                                {clientError ||
                                    form.errors.facility_id ||
                                    form.errors.starts_at ||
                                    Object.values(form.errors)[0]}
                            </p>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-md bg-sky-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-60"
                            >
                                {form.processing ? 'Αποστολή…' : 'Κράτηση'}
                            </button>
                            {payOnline ? (
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    className="rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-50 disabled:opacity-60"
                                    onClick={(e) => submitBooking(e, true)}
                                >
                                    {form.processing ? 'Αποστολή…' : 'Πληρωμή online'}
                                </button>
                            ) : null}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
