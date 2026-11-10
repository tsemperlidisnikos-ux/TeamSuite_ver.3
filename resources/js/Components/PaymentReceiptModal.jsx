import { amountToGreekWords } from '@/lib/amountToGreekWords';
import { useEffect, useMemo, useState } from 'react';

function emptyDraft() {
    return {
        date: '',
        series: '',
        number: '',
        amount: '',
        receivedFrom: '',
        address: '',
        amountWords: '',
        reason: '',
    };
}

function formatDisplayAmount(value) {
    const n = Number(String(value).replace(',', '.'));
    if (!Number.isFinite(n)) return String(value || '');
    return n.toLocaleString('el-GR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Print-focused payment receipt modal (Vercel PaymentReceiptModal parity).
 * Uses existing receipt_number on transactions; does not allocate new series numbers.
 */
export default function PaymentReceiptModal({
    open,
    logoUrl = null,
    clubName = '',
    initial = null,
    onClose,
    readOnly = false,
}) {
    const [draft, setDraft] = useState(emptyDraft);

    useEffect(() => {
        if (!open) return;
        const next = { ...emptyDraft(), ...(initial || {}) };
        if (!String(next.amountWords || '').trim() && String(next.amount || '').trim()) {
            next.amountWords = amountToGreekWords(next.amount);
        }
        setDraft(next);
    }, [open, initial]);

    const titleBits = useMemo(() => {
        const series = String(draft.series || '').trim();
        const number = String(draft.number || '').trim();
        if (series && number) return `${series}-${number}`;
        return number || series || '—';
    }, [draft.series, draft.number]);

    if (!open) return null;

    const setField = (key, value) => {
        if (readOnly) return;
        setDraft((prev) => {
            if (key === 'amount') {
                return {
                    ...prev,
                    amount: value,
                    amountWords: amountToGreekWords(value),
                };
            }
            return { ...prev, [key]: value };
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 print:static print:bg-transparent print:p-0">
            <div className="mt-8 w-full max-w-3xl rounded-lg border border-ts-border bg-ts-surface shadow-xl print:mt-0 print:max-w-none print:border-0 print:shadow-none">
                <div className="flex items-center justify-between border-b border-ts-border px-4 py-3 print:hidden">
                    <h3 className="text-base font-semibold text-ts-fg">Απόδειξη είσπραξης</h3>
                    <button
                        type="button"
                        className="rounded-md px-2 py-1 text-sm text-ts-muted hover:bg-ts-hover"
                        onClick={onClose}
                    >
                        ✕
                    </button>
                </div>

                <div className="max-h-[70vh] overflow-y-auto px-4 py-4 print:max-h-none print:overflow-visible" id="payment-receipt-print">
                    <div className="space-y-4 text-ts-fg">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex min-h-16 min-w-16 items-center gap-3">
                                {logoUrl ? (
                                    <img
                                        src={logoUrl}
                                        alt={clubName || 'Λογότυπο συλλόγου'}
                                        className="h-16 w-16 object-contain"
                                    />
                                ) : (
                                    <div className="flex h-16 w-16 flex-col items-center justify-center rounded border border-ts-border bg-ts-surface-2 text-center text-xs">
                                        <span className="font-semibold">
                                            {(clubName || 'Σύλλογος').slice(0, 2).toUpperCase()}
                                        </span>
                                    </div>
                                )}
                                <strong className="text-sm">{clubName || 'Σύλλογος'}</strong>
                            </div>
                            <div className="min-w-[14rem] space-y-2 text-sm">
                                <label className="block">
                                    <span className="text-xs uppercase tracking-wide text-ts-muted">Ημ/νία</span>
                                    <input
                                        className="mt-0.5 w-full rounded border border-ts-border bg-ts-bg px-2 py-1"
                                        value={draft.date}
                                        readOnly={readOnly}
                                        onChange={(e) => setField('date', e.target.value)}
                                        placeholder="ηη/μμ/εεεε"
                                    />
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    <label className="block">
                                        <span className="text-xs uppercase tracking-wide text-ts-muted">Σειρά</span>
                                        <input
                                            className="mt-0.5 w-full rounded border border-ts-border bg-ts-bg px-2 py-1"
                                            value={draft.series}
                                            readOnly
                                        />
                                    </label>
                                    <label className="block">
                                        <span className="text-xs uppercase tracking-wide text-ts-muted">Νο</span>
                                        <input
                                            className="mt-0.5 w-full rounded border border-ts-border bg-ts-bg px-2 py-1"
                                            value={draft.number || titleBits}
                                            readOnly
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div className="text-center">
                            <div className="text-lg font-semibold tracking-wide">ΑΠΟΔΕΙΞΗ ΕΙΣΠΡΑΞΗΣ</div>
                            <div className="mt-2 inline-flex items-center gap-2 rounded border border-ts-border px-3 py-2 text-xl font-semibold tabular-nums">
                                <span>€</span>
                                {readOnly ? (
                                    <span>{formatDisplayAmount(draft.amount)}</span>
                                ) : (
                                    <input
                                        className="w-28 bg-transparent text-center outline-none"
                                        value={draft.amount}
                                        onChange={(e) => setField('amount', e.target.value)}
                                    />
                                )}
                            </div>
                        </div>

                        <label className="block text-sm">
                            <span className="text-ts-muted">Έλαβα από τον</span>
                            <input
                                className="mt-1 w-full border-b border-ts-border bg-transparent px-1 py-1"
                                value={draft.receivedFrom}
                                readOnly={readOnly}
                                onChange={(e) => setField('receivedFrom', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            <span className="text-ts-muted">Διεύθυνση</span>
                            <input
                                className="mt-1 w-full border-b border-ts-border bg-transparent px-1 py-1"
                                value={draft.address}
                                readOnly={readOnly}
                                onChange={(e) => setField('address', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            <span className="text-ts-muted">Το ποσό</span>
                            <textarea
                                rows={2}
                                className="mt-1 w-full rounded border border-ts-border bg-ts-bg px-2 py-1"
                                value={draft.amountWords}
                                readOnly={readOnly}
                                onChange={(e) => setField('amountWords', e.target.value)}
                            />
                        </label>
                        <label className="block text-sm">
                            <span className="text-ts-muted">Αιτιολογία είσπραξης</span>
                            <textarea
                                rows={2}
                                className="mt-1 w-full rounded border border-ts-border bg-ts-bg px-2 py-1"
                                value={draft.reason}
                                readOnly={readOnly}
                                onChange={(e) => setField('reason', e.target.value)}
                            />
                        </label>

                        <div className="grid grid-cols-2 gap-6 pt-6 text-center text-xs">
                            <div>
                                <strong>ΓΙΑ ΤΗΝ ΕΙΣΠΡΑΞΗ</strong>
                                <div className="mt-10 border-b border-ts-border" />
                            </div>
                            <div>
                                <strong>ΓΙΑ ΤΗΝ ΠΛΗΡΩΜΗ</strong>
                                <div className="mt-10 border-b border-ts-border" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap justify-end gap-2 border-t border-ts-border px-4 py-3 print:hidden">
                    <button
                        type="button"
                        className="rounded-md border border-ts-border px-3 py-1.5 text-sm"
                        onClick={onClose}
                    >
                        Κλείσιμο
                    </button>
                    <button
                        type="button"
                        className="rounded-md bg-ts-accent px-3 py-1.5 text-sm text-ts-accent-fg"
                        onClick={() => window.print()}
                    >
                        Εκτύπωση
                    </button>
                </div>
            </div>
        </div>
    );
}

export function draftFromTransaction(tx, party = null) {
    const created = tx?.created_at ? new Date(tx.created_at) : null;
    const date = created
        ? created.toLocaleDateString('el-GR')
        : '';
    const receipt = String(tx?.receipt_number || '').trim();
    let series = String(tx?.receipt_series || '').trim();
    let number = tx?.receipt_seq != null && tx?.receipt_seq !== '' ? String(tx.receipt_seq) : '';
    if (!series && receipt.includes('-')) {
        const idx = receipt.lastIndexOf('-');
        series = receipt.slice(0, idx);
        number = number || receipt.slice(idx + 1);
    }
    if (!number) number = receipt;

    const receivedFrom = party
        ? [party.last_name, party.first_name, party.father_first_name || party.father_name]
              .filter(Boolean)
              .join(' ')
              .trim()
        : '';

    return {
        date,
        series,
        number,
        amount: formatDisplayAmount(tx?.amount ?? ''),
        receivedFrom,
        address: party?.city || party?.address || '',
        amountWords: amountToGreekWords(tx?.amount ?? ''),
        reason: tx?.comments || 'Πληρωμή συνδρομής',
    };
}
