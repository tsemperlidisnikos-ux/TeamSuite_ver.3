export const STAFF_ROLE_OPTIONS = [
    { value: 'admin', label: 'Διαχειριστής' },
    { value: 'secretariat', label: 'Γραμματεία' },
    { value: 'employee', label: 'Υπάλληλος' },
    { value: 'cleaner', label: 'Καθαρίστρια' },
    { value: 'cook', label: 'Μάγειρας / Μαγείρισσα' },
    { value: 'misc', label: 'Διάφορα' },
    { value: 'coach', label: 'Προπονητής' },
];

const LABEL_BY_KEY = Object.fromEntries(
    STAFF_ROLE_OPTIONS.map((option) => [option.value, option.label]),
);

const KEY_BY_LABEL = Object.fromEntries(
    STAFF_ROLE_OPTIONS.map((option) => [option.label.toLowerCase(), option.value]),
);

/** @param {unknown} value */
export function staffRoleLabel(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value).trim();
    if (!raw) {
        return '—';
    }

    const key = raw.toLowerCase();
    if (LABEL_BY_KEY[key]) {
        return LABEL_BY_KEY[key];
    }

    if (LABEL_BY_KEY[raw]) {
        return LABEL_BY_KEY[raw];
    }

    return raw;
}

/** Normalize stored English keys or Greek labels to the canonical option value. */
export function staffRoleValue(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const raw = String(value).trim();
    if (!raw) {
        return '';
    }

    const lower = raw.toLowerCase();
    if (LABEL_BY_KEY[lower]) {
        return lower;
    }
    if (LABEL_BY_KEY[raw]) {
        return raw;
    }
    if (KEY_BY_LABEL[lower]) {
        return KEY_BY_LABEL[lower];
    }

    return raw;
}
