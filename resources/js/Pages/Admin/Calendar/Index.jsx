import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const weekdays = ['ΔΕΥ', 'ΤΡΙ', 'ΤΕΤ', 'ΠΕΜ', 'ΠΑΡ', 'ΣΑΒ', 'ΚΥΡ'];

const kindTone = {
    training: 'bg-sky-500',
    match: 'bg-amber-500',
    rental: 'bg-violet-500',
    other: 'bg-slate-400',
};

const kindBlock = {
    training: 'border-sky-300 bg-sky-50 text-sky-950',
    match: 'border-amber-300 bg-amber-50 text-amber-950',
    rental: 'border-violet-300 bg-violet-50 text-violet-950',
    other: 'border-slate-300 bg-slate-50 text-slate-800',
};

const kindLabel = {
    training: 'Προπόνηση',
    match: 'Αγώνας',
    rental: 'Ενοικίαση',
    other: 'Άλλο',
};

function pad(n) {
    return String(n).padStart(2, '0');
}

const FACILITY_HOUR_COLUMNS = Array.from({ length: 16 }, (_, i) => {
    const hour = 8 + i;
    const start = `${pad(hour)}:00`;
    const endHour = hour + 1;
    const end = endHour === 24 ? '24:00' : `${pad(endHour)}:00`;
    const endLabel = endHour === 24 ? '00:00' : `${pad(endHour)}:00`;
    return { start, end, label: `${start}-${endLabel}` };
});

const DAY_QUARTER_SLOTS = Array.from({ length: 64 }, (_, i) => {
    const total = 8 * 60 + i * 15;
    return `${pad(Math.floor(total / 60))}:${pad(total % 60)}`;
});

function eventOccupiesSlot(event, slot, nextSlot) {
    const start = event.when || '';
    if (!start) return false;
    const slotEnd = nextSlot || '24:00';
    const end = event.when_end && event.when_end > start ? event.when_end : '';
    if (end) return start < slotEnd && end > slot;
    return start >= slot && start < slotEnd;
}

function eventMatchesFacilityRow(event, row) {
    if (event.facility_id && row.id === event.facility_id) return true;
    const loc = (event.location || event.venue || '').trim();
    if (!loc) return row.id === 'cal-no-location';
    if (loc === row.name) return true;
    return false;
}

function quarterIndex(time) {
    const value = String(time || '').slice(0, 5);
    if (value === '00:00' || value === '24:00') return DAY_QUARTER_SLOTS.length;
    return DAY_QUARTER_SLOTS.findIndex((slot, i) => {
        const next = DAY_QUARTER_SLOTS[i + 1] ?? '24:00';
        return value >= slot && value < next;
    });
}

function eventQuarterSpan(event) {
    if (!event.when) return null;
    const start = quarterIndex(event.when);
    if (start < 0 || start >= DAY_QUARTER_SLOTS.length) return null;
    const endTime = event.when_end && event.when_end > event.when ? event.when_end : '';
    if (!endTime) return { start, span: 1 };
    const end = quarterIndex(endTime);
    const exclusive = end < 0 ? start + 1 : end;
    return { start, span: Math.max(1, exclusive - start) };
}

function FacilityEventBlock({ event }) {
    const end = event.when_end && event.when_end > event.when ? event.when_end : '';
    const timeLabel = event.when ? (end ? `${event.when} - ${end}` : event.when) : '';
    const title =
        event.kind === 'rental' ||
        event.kind === 'match' ||
        String(event.label || '')
            .toLowerCase()
            .startsWith('προπόνηση')
            ? event.label
            : `Προπόνηση - ${event.label}`;
    const cls = `block rounded border px-1.5 py-1 text-[10px] leading-snug ${kindBlock[event.kind] || kindBlock.other}`;
    const body = (
        <>
            {timeLabel ? <em className="not-italic opacity-80">{timeLabel}</em> : null}
            <strong className="mt-0.5 block font-semibold">{title}</strong>
            {event.location ? <span className="mt-0.5 block opacity-70">{event.location}</span> : null}
        </>
    );
    if (event.href) {
        return (
            <Link href={event.href} className={cls} title={`${timeLabel} ${title}`.trim()}>
                {body}
            </Link>
        );
    }
    return (
        <div className={cls} title={`${timeLabel} ${title}`.trim()}>
            {body}
        </div>
    );
}

function AgendaLegend() {
    return (
        <footer className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-ts-border pt-3 text-xs text-ts-muted">
            <ul className="flex flex-wrap gap-3">
                {Object.entries(kindLabel).map(([k, label]) => (
                    <li key={k} className="flex items-center gap-1.5">
                        <span className={`h-2 w-2 rounded-full ${kindTone[k]}`} />
                        {label}
                    </li>
                ))}
            </ul>
            <p>Κάντε κλικ σε προπόνηση για παρουσίες, ή σε αγώνα/ενοικίαση για επεξεργασία</p>
        </footer>
    );
}

export default function Index({
    month,
    year,
    monthLabel,
    daysInMonth,
    startWeekday,
    events,
    classes = [],
    facilities = [],
    prev,
    next,
    stats,
}) {
    const [view, setView] = useState('month');
    const [kindFilter, setKindFilter] = useState('');
    const [classFilter, setClassFilter] = useState('');
    const [facilityFilter, setFacilityFilter] = useState('');
    const [selectedDay, setSelectedDay] = useState(() => {
        const t = new Date();
        if (t.getFullYear() === year && t.getMonth() + 1 === month) {
            return t.getDate();
        }
        return 1;
    });

    const cells = [];
    for (let i = 0; i < startWeekday; i++) {
        cells.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push(d);
    }

    const go = (m, y) => {
        router.get(route('admin.calendar.index'), { month: m, year: y }, { preserveState: true });
    };

    const today = new Date();
    const isToday = (d) =>
        d &&
        today.getFullYear() === year &&
        today.getMonth() + 1 === month &&
        today.getDate() === d;

    const dayKey = (d) =>
        `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

    const selectedIso = dayKey(selectedDay);

    const filteredEvents = useMemo(() => {
        const out = {};
        Object.entries(events || {}).forEach(([day, list]) => {
            const kept = list.filter((e) => {
                if (kindFilter && e.kind !== kindFilter) return false;
                if (classFilter && e.class_id !== classFilter) return false;
                if (facilityFilter && e.facility_id !== facilityFilter) return false;
                return true;
            });
            if (kept.length) out[day] = kept;
        });
        return out;
    }, [events, kindFilter, classFilter, facilityFilter]);

    const facilityColumns = useMemo(() => {
        const rows = (facilities || []).map((f) => ({ id: f.id, name: f.name }));
        const knownIds = new Set(rows.map((r) => r.id));
        const knownNames = new Set(rows.map((r) => r.name));
        const extra = new Set();
        Object.values(filteredEvents).forEach((list) => {
            list.forEach((event) => {
                if (event.facility_id && knownIds.has(event.facility_id)) return;
                const loc = (event.location || event.venue || '').trim();
                if (!loc) {
                    extra.add('');
                    return;
                }
                if (knownNames.has(loc)) return;
                extra.add(loc);
            });
        });
        if (extra.has('') || rows.length === 0) {
            rows.push({ id: 'cal-no-location', name: 'Χωρίς γήπεδο' });
        }
        [...extra]
            .filter(Boolean)
            .sort((a, b) => a.localeCompare(b, 'el'))
            .forEach((name) => rows.push({ id: `cal-loc:${name}`, name }));
        if (facilityFilter) {
            const selected = (facilities || []).find((f) => f.id === facilityFilter);
            return selected
                ? [{ id: selected.id, name: selected.name }]
                : rows.filter((r) => r.id === facilityFilter);
        }
        return rows;
    }, [facilities, filteredEvents, facilityFilter]);

    const downloadIcs = () => {
        const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TeamSuite//EL'];
        Object.entries(filteredEvents).forEach(([day, list]) => {
            list.forEach((ev) => {
                const stamp = `${day.replaceAll('-', '')}T${(ev.when || '09:00').replace(':', '')}00`;
                lines.push(
                    'BEGIN:VEVENT',
                    `UID:${ev.id}@teamsuite`,
                    `DTSTART:${stamp}`,
                    `SUMMARY:${ev.label}`,
                    'END:VEVENT',
                );
            });
        });
        lines.push('END:VCALENDAR');
        const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `teamsuite-${year}-${month}.ics`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const weekStartDay = useMemo(() => {
        const d = selectedDay || 1;
        const idx = startWeekday + d - 1;
        const mondayOffset = idx % 7;
        return Math.max(1, d - mondayOffset);
    }, [selectedDay, startWeekday]);

    const weekDays = useMemo(() => {
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = weekStartDay + i;
            if (d >= 1 && d <= daysInMonth) days.push(d);
        }
        return days;
    }, [weekStartDay, daysInMonth]);

    const weekDayMeta = useMemo(
        () =>
            weekDays.map((d) => {
                const iso = dayKey(d);
                const heading = new Date(`${iso}T12:00:00`).toLocaleDateString('el-GR', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                });
                return { day: d, iso, heading };
            }),
        [weekDays, year, month],
    );

    const dayTitle = useMemo(
        () =>
            new Date(`${selectedIso}T12:00:00`).toLocaleDateString('el-GR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }),
        [selectedIso],
    );

    const miniCells = [];
    for (let i = 0; i < startWeekday; i++) miniCells.push(null);
    for (let d = 1; d <= daysInMonth; d++) miniCells.push(d);

    const showSidebar = view === 'month';

    return (
        <AdminLayout title="Ημερολόγιο">
            <Head title="Ημερολόγιο" />

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Ημερολόγιο</h1>
                    <p className="text-sm text-slate-500">
                        Προπονήσεις, αγώνες και ενοικιάσεις γηπέδων.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={downloadIcs}
                    className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 hover:bg-slate-50"
                >
                    <span aria-hidden>⬇</span> Λήψη ημερολογίου (.ics)
                </button>
            </div>

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-2.5 py-1.5 text-sm"
                        onClick={() => go(prev.month, prev.year)}
                    >
                        ‹
                    </button>
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-2.5 py-1.5 text-sm"
                        onClick={() => go(next.month, next.year)}
                    >
                        ›
                    </button>
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                        onClick={() => {
                            const t = new Date();
                            go(t.getMonth() + 1, t.getFullYear());
                            setSelectedDay(t.getDate());
                        }}
                    >
                        Σήμερα
                    </button>
                    {(view === 'week' || view === 'day') && (
                        <input
                            type="date"
                            className="rounded-md border border-ts-border px-2 py-1.5 text-sm"
                            value={selectedIso}
                            onChange={(e) => {
                                const iso = e.target.value;
                                if (!iso) return;
                                const [y, m, d] = iso.split('-').map(Number);
                                if (y !== year || m !== month) {
                                    go(m, y);
                                }
                                setSelectedDay(d);
                            }}
                        />
                    )}
                    {view === 'month' && (
                        <h3 className="ms-1 min-w-[9rem] text-lg font-semibold capitalize text-ts-fg">
                            {monthLabel}
                        </h3>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex rounded-md border border-ts-border p-0.5">
                        {[
                            ['month', 'Μήνας'],
                            ['week', 'Εβδομάδα'],
                            ['day', 'Ημέρα'],
                        ].map(([id, label]) => (
                            <button
                                key={id}
                                type="button"
                                onClick={() => setView(id)}
                                className={`rounded px-3 py-1.5 text-sm ${
                                    view === id
                                        ? 'bg-teal-600 text-white'
                                        : 'text-ts-fg-soft hover:bg-ts-hover'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <Link
                        href={route('admin.trainings.index')}
                        className="inline-flex items-center gap-1.5 rounded-md bg-teal-600 px-3 py-1.5 text-sm text-white hover:bg-teal-700"
                    >
                        <span aria-hidden>＋</span> Νέο Γεγονός
                    </Link>
                </div>
            </div>

            <div className={`grid gap-4 ${showSidebar ? 'xl:grid-cols-[1fr_15rem]' : ''}`}>
                <div>
                    {view === 'month' && (
                        <div className="grid grid-cols-7 gap-px overflow-hidden rounded-xl border border-ts-border bg-ts-border">
                            {weekdays.map((d) => (
                                <div
                                    key={d}
                                    className="bg-ts-surface-2 px-2 py-2 text-center text-[11px] font-semibold tracking-wide text-ts-muted"
                                >
                                    {d}
                                </div>
                            ))}
                            {cells.map((d, i) => {
                                const key = d ? dayKey(d) : `e-${i}`;
                                const dayEvents = d ? filteredEvents[key] || [] : [];
                                return (
                                    <button
                                        type="button"
                                        key={key}
                                        disabled={!d}
                                        onClick={() => d && setSelectedDay(d)}
                                        className={`min-h-[5.5rem] bg-ts-surface p-1.5 text-left ${
                                            selectedDay === d ? 'bg-sky-50' : ''
                                        }`}
                                    >
                                        {d && (
                                            <>
                                                <div
                                                    className={`inline-flex h-6 w-6 items-center justify-center text-xs font-semibold ${
                                                        isToday(d)
                                                            ? 'rounded-full bg-teal-700 text-white'
                                                            : 'text-ts-fg'
                                                    }`}
                                                >
                                                    {d}
                                                </div>
                                                <ul className="mt-1 space-y-0.5">
                                                    {dayEvents.slice(0, 3).map((ev) => (
                                                        <li
                                                            key={ev.id}
                                                            className="flex items-center gap-1 truncate text-[10px] text-ts-fg"
                                                            title={ev.label}
                                                        >
                                                            <span
                                                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                                                                    kindTone[ev.kind] || kindTone.other
                                                                }`}
                                                            />
                                                            {ev.when ? `${ev.when} ` : ''}
                                                            {ev.label}
                                                        </li>
                                                    ))}
                                                    {dayEvents.length > 3 && (
                                                        <li className="text-[10px] text-ts-muted">
                                                            +{dayEvents.length - 3}
                                                        </li>
                                                    )}
                                                </ul>
                                            </>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {view === 'week' && (
                        <div className="space-y-4 rounded-xl border border-ts-border bg-ts-surface p-3">
                            {facilityColumns.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν ενεργά γήπεδα. Πρόσθεσέ τα στις Ρυθμίσεις → Γήπεδο.
                                </p>
                            ) : (
                                weekDayMeta.map((day) => {
                                    const dayEvents = filteredEvents[day.iso] || [];
                                    return (
                                        <section
                                            key={day.iso}
                                            className={`rounded-lg border border-ts-border ${
                                                isToday(day.day) ? 'ring-1 ring-teal-600' : ''
                                            }`}
                                        >
                                            <h3 className="border-b border-ts-border bg-ts-surface-2 px-3 py-2 text-sm font-semibold capitalize text-ts-fg">
                                                {day.heading}
                                            </h3>
                                            <div className="overflow-x-auto">
                                                <div
                                                    className="min-w-[720px] grid"
                                                    style={{
                                                        gridTemplateColumns: `minmax(140px, 180px) repeat(${FACILITY_HOUR_COLUMNS.length}, minmax(64px, 1fr))`,
                                                    }}
                                                >
                                                    <div className="border-b border-ts-border bg-ts-surface-2" />
                                                    {FACILITY_HOUR_COLUMNS.map((col) => (
                                                        <div
                                                            key={`${day.iso}-${col.start}`}
                                                            className="border-b border-l border-ts-border bg-ts-surface-2 px-1 py-1 text-center text-[10px] text-ts-muted"
                                                        >
                                                            {col.label}
                                                        </div>
                                                    ))}
                                                    {facilityColumns.map((facility) => (
                                                        <div
                                                            key={`${day.iso}-${facility.id}`}
                                                            className="contents"
                                                        >
                                                            <div className="border-b border-ts-border px-2 py-2 text-xs font-medium text-ts-fg">
                                                                {facility.name}
                                                            </div>
                                                            {FACILITY_HOUR_COLUMNS.map((col) => {
                                                                const cellEvents = dayEvents.filter(
                                                                    (event) =>
                                                                        eventMatchesFacilityRow(
                                                                            event,
                                                                            facility,
                                                                        ) &&
                                                                        eventOccupiesSlot(
                                                                            event,
                                                                            col.start,
                                                                            col.end,
                                                                        ),
                                                                );
                                                                return (
                                                                    <div
                                                                        key={`${day.iso}-${facility.id}-${col.start}`}
                                                                        className="min-h-[3.25rem] space-y-1 border-b border-l border-ts-border p-1"
                                                                    >
                                                                        {cellEvents.map((event) => (
                                                                            <FacilityEventBlock
                                                                                key={event.id}
                                                                                event={event}
                                                                            />
                                                                        ))}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </section>
                                    );
                                })
                            )}
                            <AgendaLegend />
                        </div>
                    )}

                    {view === 'day' && (
                        <div className="rounded-xl border border-ts-border bg-ts-surface p-3">
                            {facilityColumns.length === 0 ? (
                                <p className="text-sm text-ts-muted">
                                    Δεν υπάρχουν ενεργά γήπεδα. Πρόσθεσέ τα στις Ρυθμίσεις → Γήπεδο.
                                </p>
                            ) : (
                                <section
                                    className={`rounded-lg border border-ts-border ${
                                        isToday(selectedDay) ? 'ring-1 ring-teal-600' : ''
                                    }`}
                                >
                                    <h3 className="border-b border-ts-border bg-ts-surface-2 px-3 py-2 text-sm font-semibold capitalize text-ts-fg">
                                        {dayTitle}
                                    </h3>
                                    <div className="overflow-x-auto">
                                        <div
                                            className="min-w-[900px] grid"
                                            style={{
                                                gridTemplateColumns: `minmax(140px, 180px) repeat(${DAY_QUARTER_SLOTS.length}, minmax(14px, 1fr))`,
                                            }}
                                        >
                                            <div className="border-b border-ts-border bg-ts-surface-2" />
                                            {FACILITY_HOUR_COLUMNS.map((col) => (
                                                <div
                                                    key={`day-h-${col.start}`}
                                                    className="border-b border-l border-ts-border bg-ts-surface-2 px-1 py-1 text-center text-[10px] text-ts-muted"
                                                    style={{ gridColumn: 'span 4' }}
                                                >
                                                    {col.label}
                                                </div>
                                            ))}
                                            {facilityColumns.map((facility) => {
                                                const dayEvents = (filteredEvents[selectedIso] || []).filter(
                                                    (event) =>
                                                        eventMatchesFacilityRow(event, facility),
                                                );
                                                const used = new Set();
                                                const cellsOut = [];
                                                for (let i = 0; i < DAY_QUARTER_SLOTS.length; i += 1) {
                                                    if (used.has(i)) continue;
                                                    const starting = dayEvents.filter(
                                                        (event) =>
                                                            eventQuarterSpan(event)?.start === i,
                                                    );
                                                    if (starting.length > 0) {
                                                        const span = Math.max(
                                                            ...starting.map(
                                                                (event) =>
                                                                    eventQuarterSpan(event)?.span ?? 1,
                                                            ),
                                                        );
                                                        const safeSpan = Math.min(
                                                            span,
                                                            DAY_QUARTER_SLOTS.length - i,
                                                        );
                                                        for (let k = 0; k < safeSpan; k += 1) {
                                                            used.add(i + k);
                                                        }
                                                        cellsOut.push(
                                                            <div
                                                                key={`${facility.id}-q-${i}`}
                                                                className={`min-h-[3.25rem] space-y-1 border-b border-l border-ts-border p-1 ${
                                                                    (i + safeSpan) % 4 === 0
                                                                        ? 'border-r border-r-ts-border/60'
                                                                        : ''
                                                                }`}
                                                                style={{
                                                                    gridColumn: `span ${safeSpan}`,
                                                                }}
                                                            >
                                                                {starting.map((event) => (
                                                                    <FacilityEventBlock
                                                                        key={event.id}
                                                                        event={event}
                                                                    />
                                                                ))}
                                                            </div>,
                                                        );
                                                    } else {
                                                        cellsOut.push(
                                                            <div
                                                                key={`${facility.id}-q-${i}`}
                                                                className={`min-h-[3.25rem] border-b border-l border-ts-border ${
                                                                    (i + 1) % 4 === 0
                                                                        ? 'border-r border-r-ts-border/40'
                                                                        : ''
                                                                }`}
                                                            />,
                                                        );
                                                    }
                                                }
                                                return (
                                                    <div key={facility.id} className="contents">
                                                        <div className="border-b border-ts-border px-2 py-2 text-xs font-medium text-ts-fg">
                                                            {facility.name}
                                                        </div>
                                                        {cellsOut}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                    <div className="px-3 pb-3">
                                        <AgendaLegend />
                                    </div>
                                </section>
                            )}
                        </div>
                    )}
                </div>

                {showSidebar && (
                    <aside className="space-y-4">
                        <section className="rounded-xl border border-ts-border bg-ts-surface p-3">
                            <div className="mb-2 text-center text-sm font-semibold capitalize text-ts-fg">
                                {monthLabel}
                            </div>
                            <div className="grid grid-cols-7 gap-0.5 text-center text-[10px] text-ts-muted">
                                {['Δ', 'Τ', 'Τ', 'Π', 'Π', 'Σ', 'Κ'].map((d, i) => (
                                    <div key={`${d}-${i}`}>{d}</div>
                                ))}
                                {miniCells.map((d, i) => (
                                    <button
                                        key={`m-${i}`}
                                        type="button"
                                        disabled={!d}
                                        onClick={() => d && setSelectedDay(d)}
                                        className={`rounded py-1 ${
                                            isToday(d)
                                                ? 'bg-teal-700 text-white'
                                                : selectedDay === d
                                                  ? 'bg-sky-100 text-ts-fg'
                                                  : d
                                                    ? 'text-ts-fg hover:bg-ts-hover'
                                                    : ''
                                        }`}
                                    >
                                        {d || ''}
                                    </button>
                                ))}
                            </div>
                        </section>

                        <section className="space-y-2 rounded-xl border border-ts-border bg-ts-surface p-3">
                            <h3 className="text-sm font-semibold text-ts-fg">Φίλτρα</h3>
                            <select
                                className="w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                                value={kindFilter}
                                onChange={(e) => setKindFilter(e.target.value)}
                            >
                                <option value="">Όλες οι Κατηγορίες</option>
                                <option value="training">Προπόνηση</option>
                                <option value="match">Αγώνας</option>
                                <option value="rental">Ενοικίαση</option>
                            </select>
                            <select
                                className="w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                                value={classFilter}
                                onChange={(e) => setClassFilter(e.target.value)}
                            >
                                <option value="">Όλες οι Ομάδες</option>
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-sm"
                                value={facilityFilter}
                                onChange={(e) => setFacilityFilter(e.target.value)}
                            >
                                <option value="">Όλα τα γήπεδα</option>
                                {facilities.map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.name}
                                    </option>
                                ))}
                            </select>
                        </section>

                        <section className="rounded-xl border border-ts-border bg-ts-surface p-3">
                            <h3 className="mb-2 text-sm font-semibold text-ts-fg">Υπόμνημα</h3>
                            <ul className="space-y-1.5 text-sm">
                                {Object.entries(kindLabel).map(([k, label]) => (
                                    <li key={k} className="flex items-center gap-2">
                                        <span className={`h-2.5 w-2.5 rounded-full ${kindTone[k]}`} />
                                        {label}
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-3 text-xs text-ts-muted">
                                Προπονήσεις: {stats.trainings} · Αγώνες: {stats.matches} · Ενοικιάσεις:{' '}
                                {stats.rentals ?? 0}
                            </p>
                            <p className="mt-2 text-xs text-ts-muted">
                                Κάντε κλικ σε προπόνηση για παρουσίες…
                            </p>
                        </section>
                    </aside>
                )}
            </div>
        </AdminLayout>
    );
}
