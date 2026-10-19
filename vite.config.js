import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    // Force IPv4 so public/hot is http://127.0.0.1:5173 (not [::1]),
    // matching artisan serve and Windows browsers opened via start.bat.
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
});
