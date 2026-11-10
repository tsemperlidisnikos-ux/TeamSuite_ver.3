function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const out = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        out[i] = raw.charCodeAt(i);
    }
    return out;
}

async function readJson(response) {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

function csrfHeaders() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf || '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export function pushSupported() {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export async function registerPortalServiceWorker() {
    if (!pushSupported()) {
        return null;
    }
    return navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

export async function fetchVapidPublicKey() {
    const response = await fetch(route('push.vapid'), {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const json = await readJson(response);
    const key = String(json.publicKey ?? '').trim();
    return response.ok && key ? key : null;
}

export async function subscribePortalPush() {
    if (!pushSupported()) {
        return { ok: false, error: 'Το κινητό δεν υποστηρίζει ειδοποιήσεις εφαρμογής.' };
    }
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return { ok: false, error: 'Δεν δόθηκε άδεια ειδοποιήσεων.' };
    }
    const publicKey = await fetchVapidPublicKey();
    if (!publicKey) {
        return { ok: false, error: 'Οι ειδοποιήσεις δεν είναι διαθέσιμες στον διακομιστή.' };
    }
    const registration = await registerPortalServiceWorker();
    if (!registration) {
        return { ok: false, error: 'Αποτυχία εγκατάστασης υπηρεσίας ειδοποιήσεων.' };
    }
    const existing = await registration.pushManager.getSubscription();
    const subscription =
        existing ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        }));
    const json = subscription.toJSON();
    const response = await fetch(route('push.subscribe'), {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            endpoint: json.endpoint,
            keys: json.keys,
            contentEncoding: 'aes128gcm',
        }),
    });
    const body = await readJson(response);
    if (!response.ok) {
        return { ok: false, error: String(body.message || body.error || 'Αποτυχία εγγραφής ειδοποιήσεων.') };
    }
    return { ok: true };
}

export async function unsubscribePortalPush() {
    if (!pushSupported()) {
        return;
    }
    const registration = await navigator.serviceWorker.getRegistration('/');
    const subscription = await registration?.pushManager.getSubscription();
    const endpoint = subscription?.endpoint;
    if (subscription) {
        await subscription.unsubscribe();
    }
    await fetch(route('push.unsubscribe'), {
        method: 'POST',
        headers: csrfHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ endpoint }),
    }).catch(() => undefined);
}
