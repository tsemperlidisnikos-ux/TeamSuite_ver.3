import { buildHealthCardPdf } from './pdf';

/**
 * Build athlete payload for the health-card PDF from form/athlete snake_case fields.
 */
export function athletePayloadForHealthCard(source, clubName = '') {
    const sports = Array.isArray(source.sports)
        ? source.sports
        : source.sport
          ? [source.sport]
          : [];

    return {
        sport: source.sport || sports[0] || '',
        sports,
        amka: source.amka && source.amka !== '********' ? source.amka : '',
        gender: source.gender || '',
        lastName: source.last_name || source.lastName || '',
        firstName: source.first_name || source.firstName || '',
        email: source.email || '',
        birthDate: source.birth_date || source.birthDate || '',
        registrationNumber: source.registration_number || source.registrationNumber || '',
        clubName: source.club_name || source.clubName || clubName || '',
        fatherFirstName: source.father_first_name || source.fatherFirstName || '',
        motherFirstName: source.mother_first_name || source.motherFirstName || '',
        guardianPhone: source.guardian_phone || source.guardianPhone || '',
        motherPhone: source.mother_phone || source.motherPhone || '',
        fatherEmail: source.father_email || source.fatherEmail || '',
        motherEmail: source.mother_email || source.motherEmail || '',
        photoUrl: source.photo_url || source.photoUrl || null,
        medicalCertExpires:
            source.health_card_expires || source.medicalCertExpires || source.medical_cert_expires || '',
        heightCm: source.height_cm ?? source.heightCm ?? null,
        weightKg: source.weight_kg ?? source.weightKg ?? null,
    };
}

/**
 * @param {object} athleteInput  payload from athletePayloadForHealthCard or raw fields
 * @param {Record<string, { pdfUrl?: string, layout?: string }>|null|undefined} templatesBySport
 */
export async function openAthleteHealthCardPreview(athleteInput, templatesBySport = null) {
    const result = await buildHealthCardPdf(athleteInput, templatesBySport);

    if (!result.success || !result.data) {
        return {
            success: false,
            error: result.error ?? 'Αποτυχία δημιουργίας PDF κάρτας υγείας',
        };
    }

    const pdfUrl = URL.createObjectURL(result.data);
    const features = [
        'popup=yes',
        'noopener=no',
        'noreferrer=no',
        `width=${screen.availWidth}`,
        `height=${screen.availHeight}`,
        'left=0',
        'top=0',
    ].join(',');

    const previewWindow = window.open('', 'healthCardPreview', features);
    if (!previewWindow) {
        URL.revokeObjectURL(pdfUrl);
        return {
            success: false,
            error: 'Επίτρεψε τα pop-up για την προεπισκόπηση κάρτας υγείας',
        };
    }

    previewWindow.opener = null;
    previewWindow.document.write(`<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="utf-8" />
  <title>Προεπισκόπηση κάρτας υγείας</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; font-family: "Segoe UI", system-ui, sans-serif; background: #525659; }
    header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 20px; background: #fff; border-bottom: 1px solid #cfe0db; }
    header strong { font-size: 14px; color: #0f172a; }
    header .actions { display: flex; gap: 8px; }
    header button { border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; border-radius: 6px; padding: 6px 12px; font-size: 13px; cursor: pointer; }
    header button.primary { background: #0f766e; border-color: #0f766e; color: #fff; }
    iframe { width: 100%; height: calc(100% - 64px); border: 0; }
  </style>
</head>
<body>
  <header>
    <strong>Κάρτα υγείας — προεπισκόπηση / εκτύπωση</strong>
    <div class="actions">
      <button type="button" class="primary" onclick="document.querySelector('iframe').contentWindow.print()">Εκτύπωση</button>
      <button type="button" onclick="window.close()">Κλείσιμο</button>
    </div>
  </header>
  <iframe title="Κάρτα υγείας" src="${pdfUrl}"></iframe>
</body>
</html>`);
    previewWindow.document.close();
    previewWindow.addEventListener('unload', () => URL.revokeObjectURL(pdfUrl));

    try {
        previewWindow.focus();
    } catch {
        // ignore
    }

    return { success: true };
}
