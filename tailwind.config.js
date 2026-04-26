import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * IHRAUTO CRM theme — colour pass 2026-04-26.
 *
 * Per the user's brief, the app uses only four base colours plus white:
 *
 *   #3E9786  teal-green   PRIMARY brand (sidebar bg, brand emphasis, success)
 *   #FE7551  coral        BUTTONS / CTAs (primary action colour)
 *   #26282C  near-black   Primary text, headings
 *   #7A7C7B  cool-gray    Secondary / muted text
 *   #FFFFFF  white        Cards, panels, modal surfaces
 *
 * Page backgrounds are a barely-tinted teal (#F0F8F5).
 *
 * The codebase has 1500+ class usages of `indigo-*`, `slate-*`, `gray-*`,
 * `red-*`, `rose-*`, `green-*`, `emerald-*` etc. across 85 view files. To
 * change the entire app's palette without touching every Blade file, we
 * REMAP each of those Tailwind palettes to one of our four hues. The class
 * names stay the same; the rendered colours change.
 *
 * Mapping rule:
 *   indigo / blue / sky / cyan / purple / violet  →  teal scale (brand)
 *   green / emerald / lime / teal                 →  teal scale (brand)
 *   red / rose / pink / orange                    →  coral scale (accent)
 *   amber / yellow                                →  coral scale (accent)
 *   slate / gray / zinc / stone / neutral         →  neutral scale
 *
 * Why one shared scale per hue family? Because views use mid-shades like
 * `text-indigo-700` and dark shades like `text-indigo-950` for the same
 * "brand emphasis" intent. Mapping all of them onto the same teal tonal
 * scale preserves contrast hierarchy without inventing new tokens.
 *
 * NOTE on coral: the user's `#FE7551` is anchored at scale-500 (the base).
 * Hover states use 600 (darker), pressed states use 700.
 */

// ---------------------------------------------------------------------------
// Tonal scales (50–950) for the four base hues.
// ---------------------------------------------------------------------------

const teal = {
    // Note: scale-50 is for tinted card surfaces. Page background uses
    // a SEPARATE token (`brand.tint`, set further down) that's even
    // lighter than 50, so a `bg-indigo-50` card stands out subtly from
    // the body. If we set both to the same hex, indigo-50 cards
    // (Add New Customer, info panels, etc.) disappear into the page.
    50:  '#E5F2EC',  // tinted-card surface — distinct from page bg
    100: '#D6ECE5',
    200: '#B7DDD0',
    300: '#8AC6B5',
    400: '#5BAE9D',
    500: '#3E9786',  // BASE — sidebar, brand emphasis
    600: '#347D6F',
    700: '#2A6358',
    800: '#214B43',
    900: '#15302A',
    950: '#0C1F1B',
};

const coral = {
    50:  '#FFF3EE',
    100: '#FFE0D4',
    200: '#FFC2A8',
    300: '#FF9D78',
    400: '#FE8869',
    500: '#FE7551',  // BASE — primary CTA button
    600: '#E55D38',
    700: '#C04826',
    800: '#963718',
    900: '#62240F',
    950: '#371206',
};

const neutral = {
    50:  '#F8F8F9',
    100: '#EFEFF0',
    200: '#DFE0E1',
    300: '#C4C5C6',
    400: '#A6A7A8',
    500: '#7A7C7B',  // BASE — secondary text
    600: '#5C5E5F',
    700: '#44464A',
    800: '#34363A',
    900: '#26282C',  // BASE — primary text
    950: '#15171A',
};

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
                // ---------------------------------------------------------
                // BRAND — teal #3E9786 scale + semantic tokens used by the
                // existing button / input components.
                // ---------------------------------------------------------
                brand: {
                    ...teal,
                    // CTA primary token (used by <x-primary-button>) — the
                    // user wants COLOR coral here per the 2026-04-26 brief
                    // ("use #FE7551 for buttons"). The brand-* scale itself
                    // stays teal because brand-* shades are everywhere as
                    // "brand emphasis" colours.
                    primary: coral[500],
                    'primary-hover': coral[600],
                    focus: teal[400],
                    'subtle-border': teal[200],
                    // Page-body background — barely-there teal wash. Distinctly
                    // LIGHTER than teal-50 so cards using bg-indigo-50 / bg-brand-50
                    // stand out against it as a subtle tinted surface.
                    tint: '#F4FAF7',
                    accent: coral[500],
                },

                // ---------------------------------------------------------
                // ACCENT — coral #FE7551 scale, exposed under its own token
                // for new code that wants explicit coral classes
                // (`bg-accent-500`, etc.).
                // ---------------------------------------------------------
                accent: { ...coral },

                // ---------------------------------------------------------
                // STATUS — semantic shortcuts. Limited to our four hues:
                // success uses teal, warning/danger use coral, info is a
                // lighter teal so it's visually distinct from success.
                // ---------------------------------------------------------
                status: {
                    success: teal[500],
                    warning: coral[500],
                    danger:  coral[700],
                    info:    teal[400],
                },

                // ---------------------------------------------------------
                // PALETTE OVERRIDES — every Tailwind hue currently used in
                // the views is remapped to one of our three tonal scales.
                // After this, `bg-indigo-500` renders teal, `bg-red-500`
                // renders coral, `text-slate-700` renders dark gray — with
                // ZERO Blade-file changes.
                // ---------------------------------------------------------

                // Brand-aligned hues → teal
                indigo:   { ...teal },
                blue:     { ...teal },
                sky:      { ...teal },
                cyan:     { ...teal },
                purple:   { ...teal },
                violet:   { ...teal },
                fuchsia:  { ...teal },

                // Success-ish hues → also teal (success = brand)
                green:    { ...teal },
                emerald:  { ...teal },
                lime:     { ...teal },
                teal:     { ...teal },

                // Warning / danger / attention hues → coral
                red:      { ...coral },
                rose:     { ...coral },
                pink:     { ...coral },
                orange:   { ...coral },
                amber:    { ...coral },
                yellow:   { ...coral },

                // Neutrals — all flavours of grey converge on our neutral
                slate:    { ...neutral },
                gray:     { ...neutral },
                zinc:     { ...neutral },
                stone:    { ...neutral },
                neutral:  { ...neutral },
            },
            // ---------------------------------------------------------
            // Theme pass 2026-04-26: every shadow utility nuked to
            // `none`. The user wants a flat, professional surface — no
            // visual weight from elevation. Modals get a single soft
            // shadow because they genuinely need to break z-order
            // (otherwise content beneath shows through visually).
            //
            // We override `boxShadow` rather than `extend.boxShadow` so
            // Tailwind's defaults (sm/md/lg/xl/2xl/inner) all flatten
            // to `none`, killing every existing `shadow-*` class in the
            // codebase without touching a Blade file.
            // ---------------------------------------------------------
        },
        boxShadow: {
            none:        'none',
            sm:          'none',
            DEFAULT:     'none',
            md:          'none',
            lg:          'none',
            xl:          'none',
            '2xl':       'none',
            inner:       'none',
            card:        'none',
            'card-hover':'none',
            // The ONLY shadow we keep — only modals/dropdowns need it
            // to break z-order. Used as `shadow-modal`.
            modal:       '0 12px 24px -6px rgb(0 0 0 / 0.08), 0 4px 8px -2px rgb(0 0 0 / 0.04)',
        },

        // ---------------------------------------------------------
        // Theme pass 2026-04-26: corner radius unified to 10px.
        // Every `rounded`, `rounded-sm/md/lg/xl/2xl/3xl` resolves
        // to 10px so the app reads with consistent corner geometry.
        // `rounded-full` stays at 9999px so circles (avatars,
        // status dots) keep their shape. `rounded-none` = 0 so
        // square corners can still be opted into explicitly.
        // ---------------------------------------------------------
        borderRadius: {
            none:    '0',
            sm:      '10px',
            DEFAULT: '10px',
            md:      '10px',
            lg:      '10px',
            xl:      '10px',
            '2xl':   '10px',
            '3xl':   '10px',
            full:    '9999px',
        },

        // ---------------------------------------------------------
        // Theme pass 2026-04-26: bare `border` produces a
        // TRANSPARENT line so cards that currently say `border` (no
        // colour shade) lose their outline globally. Explicit
        // `border-{color}-{shade}` still works — we want input
        // fields to stay outlined so users can see them on a white
        // card. The transparent default just kills the silent
        // hairline borders on every panel.
        //
        // Tailwind merges this with the colors object, so
        // `border-brand-500`, `border-accent-500`, etc. continue
        // to resolve to their hex values.
        // ---------------------------------------------------------
        borderColor: ({ theme }) => ({
            DEFAULT: 'transparent',
            ...theme('colors'),
        }),
    },

    plugins: [forms],
};
