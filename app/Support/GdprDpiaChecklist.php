<?php

namespace App\Support;

final class GdprDpiaChecklist
{
    /**
     * @return list<array{id: string, label: string}>
     */
    public static function items(): array
    {
        return [
            ['id' => 'consent', 'label' => 'Συγκατάθεση χρήστη/γονέα'],
            ['id' => 'privacy_cookies', 'label' => 'Privacy Policy & Cookie Policy δημοσιευμένες'],
            ['id' => 'consent_manager', 'label' => 'Consent Manager (Essential / Analytics / Marketing)'],
            ['id' => 'encryption', 'label' => 'Κρυπτογράφηση AES-256 (μόνο ΑΜΚΑ στο cloud mirror)'],
            ['id' => 'rbac', 'label' => 'RBAC (admin + γραμματεία + ιατρός) για ΑΜΚΑ· ιατρικά μόνο admin + ιατρός'],
            ['id' => 'audit_logs', 'label' => 'Audit logs ΑΜΚΑ + GDPR DSAR logs'],
            ['id' => 'gdpr_endpoints', 'label' => 'GDPR endpoints (export / erase / correct / consent)'],
            ['id' => 'https', 'label' => 'HTTPS παντού'],
            ['id' => 'amka_deletion', 'label' => 'Διαγραφή ΑΜΚΑ μετά τη χρήση'],
            ['id' => 'dpia_ropa', 'label' => 'DPIA / RoPA / Data Breach Plan'],
            ['id' => 'dpa_roles', 'label' => 'DPA / ρόλοι ελεγκτή–εκτελούντος'],
            ['id' => 'retention', 'label' => 'Πολιτική διατήρησης + cron retention'],
            ['id' => 'photo_consent', 'label' => 'Photo consent gate στο upload'],
            ['id' => 'email_safe', 'label' => 'Email: χωρίς ΑΜΚΑ, List-Unsubscribe, communication consent'],
            ['id' => 'viva_webhook', 'label' => 'Viva webhook authentication'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_column(self::items(), 'id');
    }

    /**
     * @return array<string, bool>
     */
    public static function normalize(mixed $stored): array
    {
        $checked = [];
        if (is_array($stored)) {
            if (array_is_list($stored)) {
                foreach ($stored as $id) {
                    if (is_string($id) && $id !== '') {
                        $checked[$id] = true;
                    }
                }
            } else {
                foreach ($stored as $id => $value) {
                    if (is_string($id) && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $checked[$id] = true;
                    }
                }
            }
        }

        $out = [];
        foreach (self::ids() as $id) {
            $out[$id] = (bool) ($checked[$id] ?? false);
        }

        return $out;
    }
}
