export function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString('el-GR', {
        style: 'currency',
        currency: 'EUR',
    });
}

export function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    const raw = String(iso).slice(0, 10);
    const d = new Date(`${raw}T12:00:00`);
    if (Number.isNaN(d.getTime())) {
        return raw;
    }
    return d.toLocaleDateString('el-GR', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatTimeRange(startsAt, endsAt) {
    if (!startsAt) {
        return '';
    }
    const start = new Date(startsAt);
    const t1 = start.toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' });
    if (!endsAt) {
        return t1;
    }
    const end = new Date(endsAt);
    const t2 = end.toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' });
    return `${t1}–${t2}`;
}

export function formatLongDate(date = new Date()) {
    return date.toLocaleDateString('el-GR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export function athleteDisplayName(row) {
    return `${row.last_name || ''} ${row.first_name || ''}`.trim() || '—';
}

export function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toLocaleUpperCase('el') ?? '')
        .join('');
}

export function healthDocStatus(athlete, todayIso) {
    const exp = athlete.health_card_expires?.trim?.() || athlete.health_card_expires || '';
    const hasCard = Boolean(athlete.health_card);
    if (!hasCard) {
        if (athlete.health_card_status === 'Όχι') {
            return { label: 'Κάρτα υγείας: Όχι', tone: 'warn' };
        }
        return { label: 'Χωρίς καταχώριση', tone: 'warn' };
    }
    if (exp && exp < todayIso) {
        return { label: 'Ληγμένη ιατρική', tone: 'negative' };
    }
    if (exp) {
        const soon = new Date(`${exp}T12:00:00`);
        const now = new Date(`${todayIso}T12:00:00`);
        const days = Math.ceil((soon.getTime() - now.getTime()) / 86400000);
        if (days <= 30) {
            return { label: `Λήγει ${formatDate(exp)}`, tone: 'warn' };
        }
        return { label: 'Κάρτα υγείας ενεργή', tone: 'positive' };
    }
    return { label: 'Κάρτα υγείας ενεργή', tone: 'positive' };
}

export function gdprLabel(value) {
    if (value === 'full') {
        return 'Πλήρης';
    }
    if (value === 'pending') {
        return 'Εκκρεμεί';
    }
    if (value === 'locked') {
        return 'Κλειδωμένη';
    }
    return '—';
}

export function attendanceStatusLabel(status) {
    if (status === 'present') {
        return 'Παρών/ούσα';
    }
    if (status === 'absent') {
        return 'Απών/ούσα';
    }
    if (status === 'excused') {
        return 'Δικαιολογημένη απουσία';
    }
    return '—';
}

export function downloadIcs(events, filename) {
    if (!events.length) {
        return;
    }
    const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TeamSuite//Portal//EL'];
    for (const ev of events) {
        const uid = ev.uid || crypto.randomUUID?.() || String(Math.random());
        const dt = String(ev.date || '').replace(/-/g, '');
        const [sh, sm] = (ev.startTime || '18:00').split(':');
        const [eh, em] = (ev.endTime || '19:30').split(':');
        lines.push('BEGIN:VEVENT');
        lines.push(`UID:${uid}@teamsuite`);
        lines.push(`DTSTART:${dt}T${String(sh).padStart(2, '0')}${String(sm || 0).padStart(2, '0')}00`);
        lines.push(`DTEND:${dt}T${String(eh).padStart(2, '0')}${String(em || 0).padStart(2, '0')}00`);
        lines.push(`SUMMARY:${(ev.title || 'Προπόνηση').replace(/\n/g, ' ')}`);
        if (ev.location) {
            lines.push(`LOCATION:${ev.location.replace(/\n/g, ' ')}`);
        }
        lines.push('END:VEVENT');
    }
    lines.push('END:VCALENDAR');
    const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}
