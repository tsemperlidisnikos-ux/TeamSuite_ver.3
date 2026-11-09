import { downloadSimpleXlsx } from '@/utils/downloadSimpleXlsx';

/**
 * @param {Array<{last_name?: string, first_name?: string, email?: string, username?: string, password?: string}>} rows
 * @param {string} filename
 */
export function downloadClubUsersXlsx(rows, filename = 'club-users.xlsx') {
    downloadSimpleXlsx({
        headers: ['ΕΠΩΝΥΜΟ', 'ΟΝΟΜΑ', 'ΡΟΛΟΣ', 'EMAIL', 'USERNAME', 'PASSWORD'],
        rows: (rows || []).map((row) => [
            row.last_name || '',
            row.first_name || '',
            row.role_label || row.role || '',
            row.email || '',
            row.username || row.email || '',
            row.password && String(row.password).trim() !== '' ? String(row.password) : '—',
        ]),
        sheetName: 'Χρήστες',
        filename,
    });
}

/** CSV fallback (Excel Greece often opens with ;). */
export function downloadClubUsersCsv(rows, filename = 'club-users.csv') {
    const headers = ['ΕΠΩΝΥΜΟ', 'ΟΝΟΜΑ', 'ΡΟΛΟΣ', 'EMAIL', 'USERNAME', 'PASSWORD'];
    const escape = (value) => {
        const text = String(value ?? '');
        if (/[;"\n]/.test(text)) {
            return `"${text.replace(/"/g, '""')}"`;
        }
        return text;
    };
    const lines = [
        headers.join(';'),
        ...(rows || []).map((row) =>
            [
                row.last_name || '',
                row.first_name || '',
                row.role_label || row.role || '',
                row.email || '',
                row.username || row.email || '',
                row.password && String(row.password).trim() !== '' ? String(row.password) : '—',
            ]
                .map(escape)
                .join(';'),
        ),
    ];
    const blob = new Blob(['\uFEFF' + lines.join('\r\n')], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
