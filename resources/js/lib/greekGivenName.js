/**
 * Guess parent role from a Greek given name (or full guardian name).
 * @param {string|null|undefined} fullName
 * @returns {'father'|'mother'|null}
 */
export function guessParentRoleFromName(fullName) {
    const raw = String(fullName || '').trim();
    if (!raw) {
        return null;
    }

    const tokens = raw.split(/\s+/).filter(Boolean);
    const given = tokens[0] || '';
    const upper = given
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLocaleUpperCase('el-GR');

    if (!upper) {
        return null;
    }

    // Female given names typically end in Α / Η / Ω (ΜΑΡΙΑ, ΕΛΕΝΗ, ΗΡΩ).
    if (/[ΑΗΩ]$/u.test(upper)) {
        return 'mother';
    }

    // Male given names typically end in Σ / Ν / Ρ / Ξ / Ψ / Σ-forms (ΝΙΚΟΣ, ΘΕΟΦΑΝΗΣ).
    if (/[ΣΝΡΞΨ]$/u.test(upper)) {
        return 'father';
    }

    // Fallback: look at a second token if it looks like a gendered surname.
    if (tokens.length > 1) {
        const surname = tokens[tokens.length - 1]
            .normalize('NFD')
            .replace(/\p{M}/gu, '')
            .toLocaleUpperCase('el-GR');
        if (
            surname.endsWith('ΠΟΥΛΟΥ') ||
            surname.endsWith('ΑΚΟΥ') ||
            surname.endsWith('ΙΔΟΥ') ||
            (surname.endsWith('ΟΥ') && !surname.endsWith('ΟΓΛΟΥ'))
        ) {
            return 'mother';
        }
        if (
            surname.endsWith('ΠΟΥΛΟΣ') ||
            surname.endsWith('ΑΚΗΣ') ||
            surname.endsWith('ΙΔΗΣ') ||
            surname.endsWith('ΟΣ') ||
            surname.endsWith('ΗΣ')
        ) {
            return 'father';
        }
    }

    return null;
}

/**
 * First given name from a full guardian name.
 * @param {string|null|undefined} fullName
 */
export function extractGivenName(fullName) {
    const raw = String(fullName || '').trim();
    if (!raw) {
        return '';
    }
    return raw.split(/\s+/).filter(Boolean)[0] || '';
}

/**
 * Move legacy guardian_name into father_first_name / mother_first_name when empty.
 * @param {Record<string, any>} data
 * @returns {Record<string, any>}
 */
export function migrateGuardianNameIntoParentFields(data) {
    const next = { ...data };
    const guardian = String(next.guardian_name || '').trim();
    if (!guardian) {
        return next;
    }

    const role = guessParentRoleFromName(guardian);
    const given = extractGivenName(guardian);
    if (!role || !given) {
        return next;
    }

    if (role === 'father' && !String(next.father_first_name || '').trim()) {
        next.father_first_name = given;
        next.guardian_name = '';
    } else if (role === 'mother' && !String(next.mother_first_name || '').trim()) {
        next.mother_first_name = given;
        next.guardian_name = '';
    }

    return next;
}
