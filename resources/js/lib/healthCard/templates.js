import { isVolleyballSport, normalizeSportKey } from './sport';

export const BUILTIN_HEALTH_CARD_STANDARD = '/health-card/health-card-template.pdf';
export const BUILTIN_HEALTH_CARD_VOLLEYBALL = '/health-card/health-card-volleyball-template.pdf';

/**
 * @param {Record<string, { pdfUrl?: string, layout?: string }>|null|undefined} templatesBySport
 * @param {string|null|undefined} primarySport
 * @param {string[]|null|undefined} extraSports
 */
export function resolveHealthCardTemplate(
    templatesBySport,
    primarySport,
    extraSports,
) {
    const map = templatesBySport && typeof templatesBySport === 'object' ? templatesBySport : {};

    const ordered = [
        ...(primarySport?.trim() ? [primarySport.trim()] : []),
        ...(Array.isArray(extraSports) ? extraSports : []),
    ]
        .map((s) => String(s || '').trim())
        .filter(Boolean);

    const unique = [];
    const seen = new Set();
    for (const name of ordered) {
        const key = normalizeSportKey(name);
        if (!key || seen.has(key)) {
            continue;
        }
        seen.add(key);
        unique.push(name);
    }

    for (const name of unique) {
        const mapped = lookupMapped(map, name);
        if (mapped) {
            return fromMapped(name, mapped);
        }
    }

    return builtInForSport(unique[0] ?? primarySport ?? '');
}

function lookupMapped(map, sportName) {
    const direct = map[sportName];
    if (direct) {
        return direct;
    }
    const want = normalizeSportKey(sportName);
    for (const [name, row] of Object.entries(map)) {
        if (normalizeSportKey(name) === want) {
            return row;
        }
    }
    return null;
}

function builtInForSport(sportName) {
    const volleyball = isVolleyballSport(sportName);
    return {
        templateUrl: volleyball ? BUILTIN_HEALTH_CARD_VOLLEYBALL : BUILTIN_HEALTH_CARD_STANDARD,
        layout: volleyball ? 'volleyball' : 'standard',
        volleyball,
        sportName,
    };
}

function fromMapped(sportName, mapped) {
    const custom = String(mapped?.pdfUrl || '').trim();
    const urlLooksVolleyball = /volleyball-template/i.test(custom);
    const volleyball =
        mapped?.layout === 'volleyball' ||
        urlLooksVolleyball ||
        (mapped?.layout !== 'standard' && isVolleyballSport(sportName));

    return {
        templateUrl: custom
            ? custom
            : volleyball
              ? BUILTIN_HEALTH_CARD_VOLLEYBALL
              : BUILTIN_HEALTH_CARD_STANDARD,
        layout: volleyball ? 'volleyball' : 'standard',
        volleyball,
        sportName,
    };
}
