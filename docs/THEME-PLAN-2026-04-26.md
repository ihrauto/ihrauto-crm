# IHRAUTO CRM — Theme overhaul plan (colour pass only)

**Date:** 2026-04-26
**Scope:** Colours, surfaces, shadows, borders. NO layout, spacing, font-size,
or component-structure changes in this pass. UX/layout work is a separate
sprint that follows once the palette lands.

---

## 1. Source colours (from your screenshot)

| Hex        | Role you described                                          |
|------------|-------------------------------------------------------------|
| `#3E9786`  | **Primary brand** — buttons, links, focus rings, accents    |
| `#FE7551`  | **Accent / coral** — secondary CTAs, highlights, attention  |
| `#26282C`  | Body text, strong type, dark UI                             |
| `#7A7C7B`  | Muted text, secondary copy, dividers                        |
| `#FFFFFF`  | Cards, modals, surfaces                                     |

Page backgrounds are a *very light* tint of `#3E9786` (your phrasing:
"more light just a little color"). Computed: `#F0F8F5` — barely-there teal
wash that reads "soft", not "saturated".

---

## 2. Tonal scales (Tailwind 50–950 for each colour)

The app's blade files use Tailwind's full `*-50 / 100 / ... / 950` scale
heavily. To swap one colour without touching 1500+ classes we MUST give the
new palette the full range. I derived these from each base hex with constant
HSL hue, varying saturation + lightness — they're not arbitrary, they're
tuned for WCAG AA contrast against white and `brand-50` backgrounds.

### Brand (teal-green, base = #3E9786)

| Token        | Hex       | Use                                           |
|--------------|-----------|-----------------------------------------------|
| brand-50     | `#F0F8F5` | Page background, hover-tint on white surfaces |
| brand-100    | `#D6ECE5` | Subtle highlight, info badges                 |
| brand-200    | `#B0DACF` | Disabled-button bg, soft dividers             |
| brand-300    | `#87C5B6` | Focus rings on light surfaces                 |
| brand-400    | `#5BAE9D` | Hover state for muted buttons                 |
| **brand-500**| `#3E9786` | **Primary buttons, primary text emphasis**    |
| brand-600    | `#347D6F` | Primary button hover                          |
| brand-700    | `#2A6358` | Primary button active, dark accent text       |
| brand-800    | `#1F4A41` | Strong dark accents (rare)                    |
| brand-900    | `#15302A` | Almost-black brand-tinted, headings on tint   |
| brand-950    | `#0C1F1B` | Deep accent (logo on light bg, etc.)          |

### Coral (accent, base = #FE7551)

| Token        | Hex       | Use                                          |
|--------------|-----------|----------------------------------------------|
| coral-50     | `#FFF1EB` | Soft warning bg                              |
| coral-100    | `#FFD9C8` | Warning badges                               |
| coral-200    | `#FFB39C` | Hover tint                                   |
| coral-300    | `#FF8E70` | Hover for accent buttons                     |
| coral-400    | `#FE7551` | (Lighter on this scale) accent on dark bg    |
| **coral-500**| `#FE5E33` | **Secondary CTAs, "destructive" actions**    |
| coral-600    | `#E64A20` | Hover state                                  |
| coral-700    | `#C13D1B` | Pressed state, error text                    |
| coral-800    | `#8C2C13` | Strong error                                 |
| coral-900    | `#5C1D0D` | (rarely used)                                |

NOTE: I'm intentionally calling `#FE7551` `coral-400` instead of `coral-500`
so there's headroom on either side. The base you provided is bright; the
scale gives us a slightly darker `coral-500/600/700` for hover states that
read clearly on the same surface.

### Neutral (gray + ink, derived from #7A7C7B and #26282C)

| Token         | Hex       | Use                                         |
|---------------|-----------|---------------------------------------------|
| neutral-50    | `#F8F8F9` | Subtle alternating row, hover background    |
| neutral-100   | `#EEEEEF` | Light dividers                              |
| neutral-200   | `#DDDEDF` | Input borders (when we keep any)            |
| neutral-300   | `#C5C6C7` | Disabled state                              |
| neutral-400   | `#A8AAA9` | Placeholder text                            |
| **neutral-500**| `#7A7C7B`| **Secondary / muted body text**             |
| neutral-600   | `#5C5E60` | Body copy                                   |
| neutral-700   | `#44464A` | Strong body                                 |
| neutral-800   | `#34363A` | Headings                                    |
| **neutral-900**| `#26282C`| **Primary text, top-level headings**        |

### Status (kept separate from brand for clarity)

| Token            | Hex       | Use                              |
|------------------|-----------|----------------------------------|
| success          | `#3E9786` | === brand-500 (Swiss-green vibe) |
| warning          | `#FE7551` | === coral accent                 |
| danger           | `#C13D1B` | === coral-700 (saturated red)    |
| info             | `#5BAE9D` | === brand-400                    |

This keeps the palette tight (only 4 hues total) while still giving
operations clear semantic affordance.

---

## 3. Surfaces & elevation policy

Per your direction: **clean and professional, no shadows, no outlines.**

| Surface           | Background     | Border         | Shadow |
|-------------------|----------------|----------------|--------|
| Page body         | `brand-50`     | none           | none   |
| Card / panel      | `white`        | none           | none   |
| Modal / drawer    | `white`        | none           | `shadow-modal` (soft, only at the elevation that breaks z-order) |
| Input field       | `white`        | `neutral-200` (1px, ONLY on focus turns to `brand-500`) | none |
| Button (primary)  | `brand-500`    | none           | none   |
| Button (secondary)| `white`        | none           | none   |

The audit found 299 `shadow-sm` + 67 `shadow-lg` + 38 `shadow-md` usages
across views. We'll strip ALL of them from cards, list rows, table rows,
and panel-style containers. The only place shadows survive is **modal
overlays** (because they need to break z-order against the page) and the
**dropdown menus** (same reason). Both keep a single very soft shadow.

---

## 4. Component conventions (so call-sites stay consistent)

These are the rules every Blade view will follow after the cleanup pass:

### Buttons

```blade
{{-- Primary CTA --}}
<button class="px-4 py-2 rounded-lg bg-brand-500 text-white hover:bg-brand-600 active:bg-brand-700">

{{-- Secondary --}}
<button class="px-4 py-2 rounded-lg bg-white text-brand-700 hover:bg-brand-50">

{{-- Ghost / text-only --}}
<button class="px-4 py-2 rounded-lg text-brand-600 hover:text-brand-700 hover:bg-brand-50">

{{-- Destructive (delete, void, etc.) --}}
<button class="px-4 py-2 rounded-lg bg-coral-500 text-white hover:bg-coral-600">

{{-- Disabled (state-mod) --}}
{{-- add: disabled:bg-neutral-200 disabled:text-neutral-400 disabled:cursor-not-allowed --}}
```

### Text

```
Page title (h1)              text-neutral-900
Section title (h2/h3)        text-neutral-800
Body copy                    text-neutral-700
Secondary / metadata         text-neutral-500
Brand emphasis ("CHF 2,300") text-brand-700
Link inline in text          text-brand-600 hover:text-brand-700 underline
```

### Cards

```blade
<div class="bg-white rounded-xl p-6">
    {{-- ... --}}
</div>
```

That's it — no `border`, no `shadow-*`, no `ring-*` on cards.

### Inputs

```blade
<input class="w-full rounded-lg bg-white text-neutral-900 placeholder:text-neutral-400
              border border-neutral-200
              focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20
              focus:outline-none">
```

The 1px border-on-focus is the only "outline" we keep, because removing it
makes form fields invisible against the white card. The focus ring
(`brand-500/20`) is the *only* `ring-*` class allowed in the whole app.

### Status badges

```
status / class
draft       bg-neutral-100 text-neutral-700
issued      bg-brand-100   text-brand-700
partial     bg-coral-100   text-coral-700
paid        bg-brand-500   text-white
overdue     bg-coral-500   text-white
void        bg-neutral-200 text-neutral-500
```

### Navigation (top bar)

```
Bar background        white
Active link bg        brand-50
Active link text      brand-700
Inactive link text    neutral-700, hover → neutral-900
```

---

## 5. Implementation strategy — two phases

### Phase A: tailwind.config.js (single-file change, instant visual swap)

**Why first?** Because `tailwind.config.js` already exposes a `brand` token,
and the views use both `bg-indigo-*` and `bg-brand-*`. We make TWO changes
to the config:

1. **Replace the `brand` palette** with the teal scale above.
2. **Override `indigo`** in `theme.extend.colors.indigo` to point at the
   same teal scale.

Result: every existing `bg-indigo-500`, `text-indigo-900`, `ring-indigo-200`
etc. in 85 files renders in teal **without changing any Blade file**. We
also add the new `coral` and `neutral` tokens so future code uses them.

This is a ~30-line config file change. After Phase A, the app is already
~80% the right colour — just with the wrong borders/shadows still showing.

### Phase B: Blade cleanup pass (view-by-view)

Now the surgical work:

1. **Strip card/panel chrome** — remove `border`, `shadow-sm`, `shadow-md`,
   `ring-1` from container `<div>`s that act as cards.
2. **Page body backgrounds** — set `bg-brand-50` on `<body>` (in
   `layouts/app.blade.php`) so every page is on the soft tint.
3. **Status badges** — find the 4–6 places where status pills are rendered,
   replace ad-hoc colour combinations with the table above.
4. **Buttons** — find the components in `resources/views/components/*.blade.php`
   and rewrite their default classes to the new conventions.
5. **Inputs** — same thing for `<x-text-input>`, `<x-input-error>`, etc.

**Files that drive the most reuse** (touching these = wide visual impact):
- `resources/views/components/primary-button.blade.php`
- `resources/views/components/secondary-button.blade.php`
- `resources/views/components/text-input.blade.php`
- `resources/views/components/input-label.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/navigation.blade.php`

Each of these has 50+ call sites; getting them right is a force multiplier.

After those, we walk through page-level views (`finance/index.blade.php`,
`work-orders/board.blade.php`, dashboard views, etc.) and fix the
container chrome.

---

## 6. What we are NOT changing in this pass

(Per your "only colors for now" direction.)

- **Spacing** — `p-*`, `m-*`, `gap-*`, `space-y-*` stay as-is
- **Font sizes** — `text-sm/base/lg/xl/2xl` stay
- **Font family** — Inter / Figtree stays
- **Border radius** — `rounded-md/lg/xl` stays (you didn't say change shapes)
- **Layout** — grids, flex, positioning, z-index — untouched
- **Component structure** — no Blade refactors, no `<x-component>` extraction
- **Icons** — heroicons stay where they are
- **Animations / transitions** — `hover:*`, `transition-*` stay

UX and layout improvements get their own dedicated sprint after the colour
pass lands and you've lived with it for a few days.

---

## 7. Risk & rollback

**Risk:** very low for Phase A (one file change, instant rollback by
reverting the commit).

**Risk:** moderate for Phase B (lots of small changes; the surface area
is wide). Mitigation: do it in 4–5 commits, one logical group at a time
(buttons commit, inputs commit, page-bg commit, status-badge commit, etc.)
so any regression is easy to bisect.

**Visual regression test:** I'll take screenshots of 6–8 key pages
(dashboard, finance, work-orders board, customer detail, invoice detail,
settings) before and after Phase A so you can compare without scrubbing
through the whole app yourself.

---

## 8. Estimated effort

| Phase  | What                                                | Time      |
|--------|-----------------------------------------------------|-----------|
| A      | tailwind.config.js (new palette + indigo override)  | 30 min    |
| B.1    | Strip card chrome (border / shadow) site-wide       | 1.5 h     |
| B.2    | Page body background to `brand-50`                  | 15 min    |
| B.3    | Button + input components rewritten                 | 1 h       |
| B.4    | Status badges normalized                            | 45 min    |
| B.5    | Walk-through of top 10 page views (finance, dash, WO board, customers, invoices, quotes, products, mechanics, login, settings) | 2 h |
| QA     | Manual sweep of remaining pages, fix outliers       | 1 h       |
| **Total** |                                                   | **~7 h**  |

If you approve, I'll do Phase A first as a single commit — you reload the
browser, confirm the new colours feel right, and only then I move into
Phase B.

---

## 9. Decisions I need from you before starting

1. **Is the page background tint (`#F0F8F5`) light enough?** Or do you
   want it even subtler — say `#F7FBF9` (almost white)?
2. **Coral as "destructive" or as "secondary CTA"?** Both work; my
   recommendation is destructive (delete / void buttons) so the orange
   reads as "attention-required" not "default".
3. **Status colours** — fine with `success = brand-500` (teal-green), or
   should `success` stay a more traditional Swiss-green like `#10b981`?
4. **Phase B chunking** — one big commit per logical group (my preference)
   or one commit per file (more granular, more push churn)?

Once you answer, I do Phase A and ship.
