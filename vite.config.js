import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Bind the HMR server to `localhost` explicitly (not ::1). Some Node
    // builds default to IPv6 `::1` which Vite emits as `http://[::1]:5173`
    // in the @vite directive — browsers then reject that bracketed form
    // as an invalid CSP source expression. Using `localhost` produces a
    // clean hostname that CSP accepts.
    server: {
        host: 'localhost',
        hmr: {
            host: 'localhost',
        },
    },
    build: {
        // D-08: source maps leak source structure and should never ship to
        // production. Keep them for local/dev builds where devtools are in use.
        sourcemap: mode !== 'production',
    },
}));
