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
    50:  '#F0F8F5',  // page background — barely-there tint
    100: '#DCEFE8',
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
                    tint: teal[50],
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
            boxShadow: {
                // Bug review / theme-pass 2026-04-26: cards should have NO
                // shadow per the user's brief. We keep these tokens around
                // so existing `shadow-card` / `shadow-modal` classes don't
                // 404 in the build, but the visual weight is dialled down
                // so even where the class is still present the surface
                // reads as flat. Modal keeps a soft elevation because it
                // genuinely needs to break z-order on top of page content.
                'card':       'none',
                'card-hover': 'none',
                'modal':      '0 12px 24px -6px rgb(0 0 0 / 0.08), 0 4px 8px -2px rgb(0 0 0 / 0.04)',
            },
        },
    },

    plugins: [forms],
};
