/** Shared Greek labels for stored English gender keys. */

const CLASS_LABELS = {
    male: 'Αγόρια',
    female: 'Κορίτσια',
    mixed: 'Μικτό',
    boys: 'Αγόρια',
    girls: 'Κορίτσια',
    boy: 'Αγόρια',
    girl: 'Κορίτσια',
    other: 'Άλλο',
};

const PERSON_LABELS = {
    male: 'Αγόρι',
    female: 'Κορίτσι',
    boy: 'Αγόρι',
    girl: 'Κορίτσι',
    other: 'Άλλο',
    mixed: 'Μικτό',
};

/** @param {unknown} value */
export function classGenderLabel(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value).trim();
    if (!raw) {
        return '—';
    }

    const key = raw.toLowerCase();
    return CLASS_LABELS[key] || raw;
}

/** @param {unknown} value */
export function personGenderLabel(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value).trim();
    if (!raw) {
        return '—';
    }

    const key = raw.toLowerCase();
    return PERSON_LABELS[key] || raw;
}
