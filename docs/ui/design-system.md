# IHRAUTO CRM — Design System Reference

One-page reference for the visual building blocks. Source of truth: `tailwind.config.js` + `resources/views/components/*.blade.php`.

## Color tokens

### Brand indigo scale (`tailwind.config.js`)

Mirrors Tailwind's indigo palette; named `brand-*` so a future brand-hue swap is one-file.

| Token | Hex | Typical use |
|---|---|---|
| `brand-50` | `#eef2ff` | Page tints, hover states on indigo-light surfaces |
| `brand-100` | `#e0e7ff` | Subtle panel borders |
| `brand-200` | `#c7d2fe` | Badges, secondary chips |
| `brand-300` | `#a5b4fc` | — |
| `brand-400` | `#818cf8` | — |
| `brand-500` | `#6366f1` | Focus ring default (matches `app.css:47`) |
| `brand-600` | `#4f46e5` | Active nav, inline CTAs in utility-class form |
| `brand-700` | `#4338ca` | Hover on `brand-600` utility-class buttons |
| `brand-800` | `#3730a3` | — |
| `brand-900` | `#312e81` | Headings against light backgrounds |
| `brand-950` | `#1e1b4b` | Sidebar background, deepest brand surface |

### CTA palette (used by `<x-button>`)

Deliberately a different hue from the indigo scale — retain both so existing component usage is stable.

| Token | Hex | Typical use |
|---|---|---|
| `brand-primary` | `#1A53F2` | Primary CTA background (`<x-button variant="primary">`) |
| `brand-primary-hover` | `#5274E3` | Primary CTA hover |
| `brand-focus` | `#6A88E8` | Focus ring on CTA buttons |
| `brand-subtle-border` | `#809AED` | Input borders, card borders |
| `brand-tint` | `#E3E1FC` | Page background for non-admin pages |
| `brand-accent` | `#F1FF30` | `inverted-green` button variant |

### Status palette

| Token | Hex | Use |
|---|---|---|
| `status-success` | `#10b981` | Emerald; positive flash messages |
| `status-warning` | `#f59e0b` | Amber; caution states |
| `status-danger` | `#ef4444` | Red; destructive buttons, error flash |
| `status-info` | `#3b82f6` | Blue; informational flash |

## Border-radius scale

The `rounded-card` custom token (previously `0.75rem`) had zero adoption and was removed 2026-04-24. Use default Tailwind radii:

| Class | Size | Apply to |
|---|---|---|
| `rounded-md` | `0.375rem` | Form inputs (`<input>`, `<select>`, `<textarea>`), small badges, small buttons (`<x-button size="sm">`) |
| `rounded-lg` | `0.5rem` | Default buttons, alert/flash banners, dropdown panels |
| `rounded-xl` | `0.75rem` | Cards (`<x-card>`), modals (`<x-modal>`), large panels |
| `rounded-full` | circle | Avatars, icon-only toggle buttons |

Avoid `rounded` (4px) and `rounded-2xl` (1rem) unless you have a specific reason. Consistency beats locally-optimal choices.

## Typography

- **Family:** Inter (weights 300/400/500/600/700) loaded from Google Fonts in `layouts/app.blade.php`.
- **Body default:** inherited from layout. Prefer `text-gray-900` for primary copy, `text-gray-600` for body, `text-gray-500` for secondary/meta. Reserve `text-brand-*` for active states and emphasis — not for default body text.
- **Heading weights:**
  - Page title: `text-2xl font-bold`
  - Section heading: `text-lg font-semibold`
  - Card title: `text-base font-semibold`
- **Microcopy:** `text-xs text-gray-500`

## Components — which one to use

| Need | Use | Location |
|---|---|---|
| Primary form submit with loading state | `<x-primary-button>` | `components/primary-button.blade.php` |
| Multi-variant button (primary / secondary / inverted-blue / inverted-green) | `<x-button variant="…">` | `components/button.blade.php` |
| Outlined / cancel button | `<x-secondary-button>` | `components/secondary-button.blade.php` |
| Destructive button | `<x-danger-button>` | `components/danger-button.blade.php` |
| Text / email / number input with label + error wiring | `<x-input>` | `components/input.blade.php` |
| Bare input for manual layouts | `<x-text-input>` | `components/text-input.blade.php` |
| Card / panel wrapper | `<x-card>` | `components/card.blade.php` |
| Flash banner (success / error / info) | `<x-flash-message>` | `components/flash-message.blade.php` |
| Alpine-powered accessible modal | `<x-modal>` | `components/modal.blade.php` |
| Responsive index table (desktop table / mobile cards) | `<x-responsive-table>` | `components/responsive-table.blade.php` |
| Destructive confirm | SweetAlert2 `Swal.fire({…, icon: 'warning'})` | global import |

**Rule of thumb:** if a component exists, use it. Inline utility classes are a consistency leak — the audit found 106 raw `<button>` tags bypassing components. Components are cheap to extend; drift is expensive to clean up.

## Button types

**Always** set `type` explicitly on `<button>` elements. Implicit `type="submit"` inside a `<form>` is a footgun — any action button in the form will submit it.

- `type="submit"` — the button that completes the form.
- `type="button"` — everything else (cancel, open modal, toggle, print).

Component buttons set sensible defaults (`<x-primary-button>` → submit; `<x-secondary-button>` → button). Raw `<button>` tags do not.

## Accessibility baselines (enforced in `resources/css/app.css`)

- 44 × 44 px minimum touch target on all interactive elements.
- `:focus-visible` outline: `2px solid #6366f1` (= `brand-500`) on keyboard focus. Does not trigger on mouse click.
- `[x-cloak]` hides Alpine-bound elements until the component initializes.
- Text contrast: target **4.5:1** for normal text, **3:1** for large text / UI components. WebAIM contrast checker is your friend.

## What NOT to do

- Don't introduce new hardcoded hex colors in Blade. Add a token to `tailwind.config.js` first.
- Don't write `<button>` without `type=`.
- Don't re-implement flash banners inline — use `<x-flash-message>`.
- Don't use native `<dialog>` when `<x-modal>` already covers the case. (4 legacy instances exist; migrate when next touched.)
- Don't inline form input styling — use `<x-input>`. Three competing input visual languages is the top form-layer drift in the app.
