/** Client-side rental slot / pricing helpers (TeamSuite parity). */

export function minutesOf(hhmm) {
    const [h, m] = String(hhmm || '0:0')
        .split(':')
        .map((n) => Number(n));
    return (Number.isFinite(h) ? h : 0) * 60 + (Number.isFinite(m) ? m : 0);
}

export function formatMinutes(total) {
    const wrapped = ((total % (24 * 60)) + 24 * 60) % (24 * 60);
    const h = Math.floor(wrapped / 60);
    const m = wrapped % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

export function weekdayOfIsoDate(date) {
    const parsed = new Date(`${date}T12:00:00`);
    return Number.isNaN(parsed.getTime()) ? -1 : parsed.getDay();
}

export function normalizeRentalRule(rule, facilityId) {
    const full =
        Number(rule?.hourlyRateFull) > 0
            ? Number(rule.hourlyRateFull)
            : Number(rule?.hourlyRate) || 0;
    const half = Number(rule?.hourlyRateHalf);
    const fee = Number(rule?.lockerRoomFee);

    return {
        facilityId: rule?.facilityId || facilityId,
        enabled: Boolean(rule?.enabled),
        slotMinutes: Number(rule?.slotMinutes) || 60,
        windows:
            Array.isArray(rule?.windows) && rule.windows.length
                ? rule.windows.map((w) => ({
                      days: Array.isArray(w.days) ? w.days.map(Number) : [1, 2, 3, 4, 5, 6, 0],
                      startTime: w.startTime || '18:00',
                      endTime: w.endTime || '22:00',
                  }))
                : [{ days: [1, 2, 3, 4, 5, 6, 0], startTime: '18:00', endTime: '22:00' }],
        hourlyRate: full,
        hourlyRateFull: full,
        hourlyRateHalf: Number.isFinite(half) && half >= 0 ? half : 0,
        lockerRoomAvailable: Boolean(rule?.lockerRoomAvailable),
        lockerRoomFee: Number.isFinite(fee) && fee >= 0 ? fee : 0,
    };
}

export function ruleForFacility(rules, facilityId) {
    const existing = (rules || []).find(
        (r) => r.facilityId === facilityId || r.facility_id === facilityId,
    );
    return normalizeRentalRule(existing || { facilityId, enabled: false }, facilityId);
}

function sameLocation(location, facilityName) {
    return String(location ?? '')
        .trim()
        .toLowerCase() === String(facilityName ?? '')
        .trim()
        .toLowerCase();
}

function overlaps(aStart, aEnd, bStart, bEnd) {
    return aStart < bEnd && bStart < aEnd;
}

function windowEndMinutes(startMin, endTime) {
    let endMin = minutesOf(endTime);
    if (endTime === '00:00' || endMin <= startMin) {
        endMin += 24 * 60;
    }
    return endMin;
}

export function occupancyForDate(source, facility, date) {
    const day = weekdayOfIsoDate(date);
    const name = facility.name;
    const blocks = [];

    for (const slot of source.schedule || []) {
        if (Number(slot.dayOfWeek) !== day) {
            continue;
        }
        if (slot.facilityId && slot.facilityId !== facility.id) {
            continue;
        }
        if (!slot.facilityId && !sameLocation(slot.location, name)) {
            continue;
        }
        const startMin = minutesOf(slot.startTime);
        let endMin = minutesOf(slot.endTime);
        if (endMin <= startMin) {
            endMin += 24 * 60;
        }
        blocks.push({ startMin, endMin, reason: 'Προπόνηση τμήματος', share: 'blocked' });
    }

    for (const training of source.trainings || []) {
        if (training.date !== date) {
            continue;
        }
        if (training.facilityId && training.facilityId !== facility.id) {
            continue;
        }
        if (!training.facilityId && !sameLocation(training.location, name)) {
            continue;
        }
        const startMin = minutesOf(training.startTime);
        let endMin = minutesOf(training.endTime || '');
        if (!training.endTime) {
            endMin = startMin + 60;
        }
        if (endMin <= startMin) {
            endMin += 24 * 60;
        }
        blocks.push({ startMin, endMin, reason: 'Προπόνηση', share: 'blocked' });
    }

    for (const match of source.matches || []) {
        if (match.date !== date || match.status === 'cancelled') {
            continue;
        }
        if (match.homeAway === 'away') {
            continue;
        }
        if (match.facilityId && match.facilityId !== facility.id) {
            continue;
        }
        if (!match.facilityId && !sameLocation(match.location, name)) {
            continue;
        }
        const startMin = minutesOf(match.time || '00:00');
        blocks.push({
            startMin,
            endMin: startMin + 120,
            reason: 'Αγώνας',
            share: 'blocked',
        });
    }

    for (const booking of source.rentalBookings || []) {
        if (booking.status === 'cancelled' || booking.date !== date) {
            continue;
        }
        if (
            booking.facilityId !== facility.id &&
            !sameLocation(booking.facilityName, name)
        ) {
            continue;
        }
        const startMin = minutesOf(booking.startTime);
        let endMin = minutesOf(booking.endTime);
        if (endMin <= startMin) {
            endMin += 24 * 60;
        }
        blocks.push({
            startMin,
            endMin,
            reason:
                booking.courtShare === 'half'
                    ? 'Κράτηση μισού γηπέδου'
                    : 'Κράτηση ολόκληρου γηπέδου',
            share: booking.courtShare === 'half' ? 'half' : 'full',
        });
    }

    return blocks.sort((a, b) => a.startMin - b.startMin);
}

function occupancyForRange(occupied, startMin, endMin) {
    let blocked = false;
    let full = false;
    let halfCount = 0;
    let reason = '';
    for (const block of occupied) {
        if (!overlaps(startMin, endMin, block.startMin, block.endMin)) {
            continue;
        }
        if (block.share === 'blocked') {
            blocked = true;
            reason = block.reason;
            break;
        }
        if (block.share === 'full') {
            full = true;
            reason = block.reason;
        } else if (block.share === 'half') {
            halfCount += 1;
            reason = block.reason;
        }
    }
    return { blocked, full, halfCount, reason };
}

export function listRentalSlots(source, facility, date, courtShare = 'full') {
    const rule = ruleForFacility(source.rentalRules || source.rentalSettings?.rules, facility.id);
    if (!rule.enabled) {
        return [];
    }
    const day = weekdayOfIsoDate(date);
    const occupied = occupancyForDate(source, facility, date);
    const slotMinutes = rule.slotMinutes || 60;
    const slots = [];
    const seen = new Set();

    for (const window of rule.windows) {
        if (!window.days.includes(day)) {
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
            const occ = occupancyForRange(occupied, t, t + slotMinutes);
            const remainingHalves = occ.blocked || occ.full ? 0 : Math.max(0, 2 - occ.halfCount);
            let available = remainingHalves > 0;
            let reason = '';
            if (occ.blocked || occ.full) {
                available = false;
                reason = occ.reason || 'Μη διαθέσιμο';
            } else if (courtShare === 'full' && remainingHalves < 2) {
                available = false;
                reason = occ.halfCount > 0 ? 'Δεσμευμένο μισό γήπεδο' : 'Μη διαθέσιμο';
            } else if (courtShare === 'half' && remainingHalves < 1) {
                available = false;
                reason = occ.reason || 'Μη διαθέσιμο';
            }
            slots.push({ startTime, endTime, available, reason, remainingHalves });
        }
    }

    return slots.sort((a, b) => a.startTime.localeCompare(b.startTime));
}

export function bookingAmount(rule, startTime, endTime, courtShare = 'full') {
    const normalized = normalizeRentalRule(rule, rule?.facilityId);
    const startMin = minutesOf(startTime);
    let endMin = minutesOf(endTime);
    if (endMin <= startMin) {
        endMin += 24 * 60;
    }
    const hours = (endMin - startMin) / 60;
    const rate = courtShare === 'half' ? normalized.hourlyRateHalf : normalized.hourlyRateFull;
    return Math.round(Math.max(0, hours) * rate * 100) / 100;
}

export function lockerRoomFeeAmount(rule, useLockerRoom) {
    if (!useLockerRoom || !rule?.lockerRoomAvailable) {
        return 0;
    }
    const fee = Number(rule?.lockerRoomFee) || 0;
    return fee > 0 ? Math.round(fee * 100) / 100 : 0;
}

export function courtShareLabel(share) {
    return share === 'half' ? 'Μισό γήπεδο' : 'Ολόκληρο γήπεδο';
}

export function formatCurrency(value) {
    return `${Number(value || 0).toLocaleString('el-GR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} €`;
}

export function localDateIso(date = new Date()) {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export function nextDays(count) {
    const today = new Date();
    today.setHours(12, 0, 0, 0);
    return Array.from({ length: count }, (_, i) => {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        return localDateIso(d);
    });
}

export function formatDayChip(iso) {
    const d = new Date(`${iso}T12:00:00`);
    const name = d.toLocaleDateString('el-GR', { weekday: 'short' });
    return `${name} ${d.getDate()}/${d.getMonth() + 1}`;
}

export function isRentalBookingCollected(booking) {
    if (!booking || booking.status === 'cancelled' || booking.status === 'pending_payment') {
        return false;
    }
    if (booking.paymentCollected === false) {
        return false;
    }
    if (booking.paymentCollected === true) {
        return true;
    }
    if (booking.paidOn || booking.paidAt) {
        return true;
    }
    return booking.status === 'confirmed';
}

export function paymentMethodLabel(method) {
    if (method === 'cash') {
        return 'Μετρητά';
    }
    if (method === 'card') {
        return 'POS';
    }
    if (method === 'transfer') {
        return 'Τραπεζική κατάθεση';
    }
    return method || '—';
}

export function bookingStatusLabel(booking) {
    if (booking.status === 'cancelled') {
        return 'Ακυρωμένη';
    }
    if (booking.status === 'pending_payment') {
        return 'Εκκρεμής πληρωμή';
    }
    if (!isRentalBookingCollected(booking) && Number(booking.amount) > 0) {
        return 'Εκκρεμής είσπραξη';
    }
    if (booking.status === 'confirmed' || isRentalBookingCollected(booking)) {
        return 'Επιβεβαιωμένη';
    }
    return booking.status || 'Εκκρεμής';
}
