import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const THEMES = ['ocean-slate', 'aegean-light', 'harbor-navy', 'night-pitch'];

function applyAppearanceTheme(theme) {
    const id = THEMES.includes(theme) ? theme : 'ocean-slate';
    document.documentElement.dataset.theme = id;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        applyAppearanceTheme(props.initialPage.props.platformBranding?.appearanceTheme);
        router.on('success', (event) => {
            applyAppearanceTheme(event.detail.page.props.platformBranding?.appearanceTheme);
        });

        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#2a9bb5',
    },
});
