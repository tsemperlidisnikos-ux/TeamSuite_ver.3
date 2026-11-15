import { GREEK_PREFECTURE_OPTIONS, normalizePrefecture } from '@/lib/greekPrefectures';

export default function PrefectureSelect({
    value = '',
    onChange,
    className = '',
    disabled = false,
    required = false,
    id = undefined,
    name = 'prefecture',
}) {
    const current = normalizePrefecture(value);
    const hasCurrent =
        current !== '' && GREEK_PREFECTURE_OPTIONS.some((option) => option.value === current);

    return (
        <select
            id={id}
            name={name}
            className={className}
            disabled={disabled}
            required={required}
            value={current}
            onChange={(e) => onChange?.(e.target.value)}
        >
            <option value="">—</option>
            {!hasCurrent && current !== '' ? <option value={current}>{current}</option> : null}
            {GREEK_PREFECTURE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}
