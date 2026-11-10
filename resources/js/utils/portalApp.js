/** @typedef {'parent' | 'coach'} PortalAppKind */

const APPS = {
    parent: {
        path: '/app/parent',
        title: 'TeamSuite Γονείς',
        manifest: '/manifest-parent.webmanifest',
    },
    coach: {
        path: '/app/coach',
        title: 'TeamSuite Προπονητές',
        manifest: '/manifest-coach.webmanifest',
    },
};

/**
 * @param {PortalAppKind} kind
 */
export function applyPortalChrome(kind) {
    if (typeof document === 'undefined') {
        return;
    }
    const spec = APPS[kind];
    if (!spec) {
        return;
    }

    let link = document.querySelector('link[rel="manifest"]');
    if (!link) {
        link = document.createElement('link');
        link.setAttribute('rel', 'manifest');
        document.head.appendChild(link);
    }
    link.setAttribute('href', spec.manifest);

    document.title = spec.title;

    let appleTitle = document.querySelector('meta[name="apple-mobile-web-app-title"]');
    if (!appleTitle) {
        appleTitle = document.createElement('meta');
        appleTitle.setAttribute('name', 'apple-mobile-web-app-title');
        document.head.appendChild(appleTitle);
    }
    appleTitle.setAttribute('content', spec.title);

    let theme = document.querySelector('meta[name="theme-color"]');
    if (!theme) {
        theme = document.createElement('meta');
        theme.setAttribute('name', 'theme-color');
        document.head.appendChild(theme);
    }
    theme.setAttribute('content', '#0f2744');
}

export function isIosDevice() {
    if (typeof navigator === 'undefined') {
        return false;
    }
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}
