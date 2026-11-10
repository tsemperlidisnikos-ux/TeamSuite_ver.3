import { isIosDevice } from '@/utils/portalApp';
import {
    pushSupported,
    registerPortalServiceWorker,
    subscribePortalPush,
    unsubscribePortalPush,
} from '@/lib/portalPush';
import { useEffect, useState } from 'react';

/** @typedef {Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: string }> }} InstallEvent */

/** @type {InstallEvent | null} */
let deferredInstall = null;

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstall = /** @type {InstallEvent} */ (event);
        window.dispatchEvent(new CustomEvent('teamsuite-portal-install-ready'));
    });
}

/**
 * @param {{ kind?: 'parent' | 'coach' }} props
 */
export default function PortalPushOptIn({ kind = 'parent' }) {
    const [pushState, setPushState] = useState(/** @type {'idle' | 'on' | 'off' | 'busy'} */ ('idle'));
    const [message, setMessage] = useState('');
    const [canInstall, setCanInstall] = useState(Boolean(deferredInstall));
    const ios = isIosDevice();

    useEffect(() => {
        const bump = () => setCanInstall(Boolean(deferredInstall));
        window.addEventListener('teamsuite-portal-install-ready', bump);
        return () => window.removeEventListener('teamsuite-portal-install-ready', bump);
    }, []);

    useEffect(() => {
        if (!pushSupported()) {
            setPushState('off');
            return;
        }
        void (async () => {
            await registerPortalServiceWorker();
            const registration = await navigator.serviceWorker.getRegistration('/');
            const subscription = await registration?.pushManager.getSubscription();
            setPushState(Notification.permission === 'granted' && subscription ? 'on' : 'off');
        })();
    }, []);

    async function handleEnable() {
        setPushState('busy');
        setMessage('');
        const result = await subscribePortalPush();
        setPushState(result.ok ? 'on' : 'off');
        setMessage(result.ok ? 'Οι ειδοποιήσεις ενεργοποιήθηκαν.' : result.error || 'Αποτυχία');
    }

    async function handleDisable() {
        setPushState('busy');
        await unsubscribePortalPush();
        setPushState('off');
        setMessage('Οι ειδοποιήσεις απενεργοποιήθηκαν.');
    }

    async function handleInstall() {
        if (!deferredInstall) {
            return;
        }
        await deferredInstall.prompt();
        await deferredInstall.userChoice;
        deferredInstall = null;
        setCanInstall(false);
    }

    return (
        <section className="rounded-lg border border-ts-border bg-ts-surface p-4">
            <h3 className="text-sm font-semibold text-ts-fg">Ειδοποιήσεις &amp; εφαρμογή</h3>
            <div className="mt-3 flex flex-wrap gap-2">
                {canInstall ? (
                    <button
                        type="button"
                        onClick={() => void handleInstall()}
                        className="rounded-md border border-ts-border bg-ts-surface-2 px-3 py-1.5 text-xs font-medium text-ts-fg-soft hover:bg-ts-hover"
                    >
                        Εγκατάσταση στην αρχική οθόνη
                    </button>
                ) : null}
                {pushSupported() ? (
                    pushState === 'on' ? (
                        <button
                            type="button"
                            onClick={() => void handleDisable()}
                            className="rounded-md border border-ts-border bg-ts-surface-2 px-3 py-1.5 text-xs font-medium text-ts-fg-soft hover:bg-ts-hover"
                        >
                            Απενεργοποίηση ειδοποιήσεων
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => void handleEnable()}
                            disabled={pushState === 'busy'}
                            className="rounded-md bg-ts-accent px-3 py-1.5 text-xs font-medium text-ts-accent-fg disabled:opacity-60"
                        >
                            {pushState === 'busy' ? 'Ενεργοποίηση…' : 'Ενεργοποίηση ειδοποιήσεων'}
                        </button>
                    )
                ) : (
                    <p className="text-xs text-ts-muted">
                        Οι ειδοποιήσεις κινητού χρειάζονται Chrome / εγκατεστημένη εφαρμογή.
                    </p>
                )}
            </div>
            {ios && !canInstall ? (
                <p className="mt-2 text-xs text-ts-muted">
                    iPhone: Safari → Κοινοποίηση → «Προσθήκη στην οθόνη Αφετηρίας»
                    {kind === 'coach' ? ' (εφαρμογή προπονητή).' : '.'}
                </p>
            ) : null}
            {message ? <p className="mt-2 text-xs text-emerald-700">{message}</p> : null}
        </section>
    );
}
