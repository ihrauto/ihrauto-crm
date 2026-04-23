import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // D-08: source maps leak source structure and should never ship to
        // production. Keep them for local/dev builds where devtools are in use.
        sourcemap: mode !== 'production',
    },
}));
