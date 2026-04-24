import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand primary (indigo/navy palette used throughout)
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                    // COLOR-01 (sprint 2026-04-24 UX audit): the CTA palette
                    // used by <x-button> / <x-primary-button> / <x-input>.
                    // Deliberately a different hue from the brand-50..950
                    // indigo scale — documenting the drift as explicit
                    // tokens so call sites stop inlining raw hex values.
                    primary: '#1A53F2',
                    'primary-hover': '#5274E3',
                    focus: '#6A88E8',
                    'subtle-border': '#809AED',
                    tint: '#E3E1FC',
                    accent: '#F1FF30',
                },
                // Status colors for badges and indicators
                status: {
                    success: '#10b981',
                    warning: '#f59e0b',
                    danger: '#ef4444',
                    info: '#3b82f6',
                },
            },
            boxShadow: {
                'card': '0 1px 3px 0 rgb(0 0 0 / 0.05), 0 1px 2px -1px rgb(0 0 0 / 0.05)',
                'card-hover': '0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05)',
                'modal': '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
            },
            // RADIUS-01 (2026-04-24 UX audit): the previous custom
            // `rounded-card` (0.75rem) had zero adoption in Blade, so
            // it was removed. Use the Tailwind default radius scale per
            // docs/ui/design-system.md: rounded-md for form inputs,
            // rounded-lg for buttons/alerts, rounded-xl for cards/modals.
        },
    },

    plugins: [forms],
};
