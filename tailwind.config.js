import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', 'sans-serif', ...defaultTheme.fontFamily.sans],
                display: ['Sora', 'Manrope', 'sans-serif', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ts: {
                    bg: 'var(--ts-bg)',
                    'bg-deep': 'var(--ts-bg-deep)',
                    surface: 'var(--ts-surface)',
                    'surface-2': 'var(--ts-surface-2)',
                    border: 'var(--ts-border)',
                    fg: 'var(--ts-fg)',
                    'fg-soft': 'var(--ts-fg-soft)',
                    muted: 'var(--ts-muted)',
                    hover: 'var(--ts-hover)',
                    accent: 'var(--ts-accent)',
                    'accent-hover': 'var(--ts-accent-hover)',
                    'accent-soft': 'var(--ts-accent-soft)',
                    'accent-fg': 'var(--ts-accent-fg)',
                    chrome: 'var(--ts-chrome)',
                    'chrome-fg': 'var(--ts-chrome-fg)',
                },
            },
        },
    },

    plugins: [forms],
};
