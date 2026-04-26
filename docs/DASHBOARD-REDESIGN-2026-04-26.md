# IHRAUTO CRM — Dashboard redesign plan

**Date:** 2026-04-26
**Status:** PLAN ONLY — do not implement until user approves
**Inspiration:** Ryvix-style dashboard (mixed-size grid, chart-first, multi-axis analytics)

---

## 1. Reference design — what makes the Ryvix mockup work

Looking at the screenshot you shared, the design DNA is:

| Pattern              | Why it works                                              |
|----------------------|-----------------------------------------------------------|
| **Mixed-size grid**  | Two small KPI cards next to one tall donut card. Page reads as info-dense without monotony — different sizes signal different purposes. |
| **Sparklines on KPIs** | Each metric has a tiny inline trend chart so the number gets immediate context (going up or down) without taking space. |
| **Chart variety**    | Line, donut, horizontal-bar, mountain-bar, sparkline, table — five chart types in one viewport. Eye doesn't get bored. |
| **Per-card filter**  | Each big card has its own "Last Year ▼" or date-range selector, so user drills into individual widgets without page-level controls. |
| **Status badges**    | "In Stock" green / "Out of Stock" coral pills. Two-state colour signal is faster than reading text. |
| **Avatar lists**     | Orders / customers as a compact stream — small avatar + name + amount. Replaces a dense table for "lookup" data. |
| **Action menu (...)** | Each card has its own drilldown — "view details", "export", "remove from dashboard" without crowding the chrome. |

**What we keep from your existing dashboard:** the widget-studio
architecture (`dashboard/widgets/*`, drag-reorder, per-user
customisation, the `DashboardWidgetRegistry` pattern). That work is
solid and should not be thrown out.

**What we replace:** the *visual language* of every widget — clean
flat surfaces, charts where there are currently just numbers, mixed
sizes instead of uniform tiles.

---

## 2. Tech stack — what to install

The existing app has Tailwind, Alpine.js, Vite, Sortable.js. No
charting library yet. We need one.

### Recommended: ApexCharts

**Why ApexCharts over the alternatives:**

| Library     | Pros                            | Cons                          | Verdict        |
|-------------|---------------------------------|-------------------------------|----------------|
| **ApexCharts** | Beautiful out of the box, the line/donut/bar/sparkline aesthetic in your reference is its native style, ~150KB gzipped, no React dependency, plays nicely with Alpine.js, MIT licensed | Slightly larger than Chart.js, opinionated theming requires per-call config | **PICK**       |
| Chart.js    | Smaller (~80KB), more popular   | Plain visual style, would need heavy custom theming to match the reference | Pass            |
| Frappe Charts | Tiny (~20KB)                  | Limited chart types, dated visual style | Pass             |
| Tremor / Recharts | Beautiful                  | React-only — not applicable to a Blade app | N/A             |

**Install:**
```bash
npm install apexcharts
```

ApexCharts ships as ESM. We import it from `resources/js/app.js`,
expose it on `window.ApexCharts`, and each widget mounts its own
chart instance via Alpine `x-init`. No global state.

### Optional: country-flag glyphs (for SalesMap-style widget)

The Ryvix mockup has flag glyphs next to country names. Swiss auto
shops mostly serve customers from CH/DE/IT/FR, so a "customers by
country / language" or "regional service map" widget would be apt.
For flags, the cheapest path is `flag-icons` (CSS-only, ~80KB):

```bash
npm install flag-icons
```

Skip this if we end up not building a regional widget.

### Nothing else

No new PHP packages, no new framework. ApexCharts is a single
JS dependency. Total bundle bloat: ~150 KB gzipped.

---

## 3. Widget catalogue — every box in the new dashboard

Each widget below maps to exactly one Blade file under
`resources/views/dashboard/widgets/*` and one row in
`DashboardWidgetRegistry::WIDGETS`. **Sizes** use the existing
`small | half | full` taxonomy plus a new **xl** (full-width tall)
for hero charts.

### Tier 1 — KPI cards (small, 1×1 in a 4-col row)

| # | Widget               | Big number                  | Sparkline data           | Visual cue           |
|---|----------------------|-----------------------------|--------------------------|----------------------|
| 1 | **Revenue this month**   | CHF current_month_revenue | last 12 months payments  | line, brand-500 |
| 2 | **Active jobs**          | active_jobs / total_bays  | last 30 days work-order count | line, neutral-500 |
| 3 | **Outstanding balance**  | CHF total_outstanding     | last 30 days unpaid trend | line, accent-500 (coral) |
| 4 | **New customers (7d)**   | count                     | last 30 days customer-creation count | line, brand-500 |

Layout — single row of 4 small cards on desktop, 2×2 on tablet, 1×4
stacked on mobile.

Each card has:
- Tiny title in `neutral-500`, all caps tracking, top-left
- Big number in `neutral-900`, 2.5× line-height
- "+12% vs last month" pill in `brand-700` (positive) or `accent-600`
  (negative), top-right
- Sparkline at the bottom, full-width, 60px tall — same hue family
  as the metric

Behaviourally: hover swaps to a subtle `bg-brand-50` tint, click goes
to the relevant detail view (Finance, Work Orders, etc).

### Tier 2 — Hero analytics row (mixed sizes)

#### 2.1  Monthly Revenue (xl width — half the page)

The big draw. Smooth-line area chart, last 12 months of payment
revenue. Hovering a point shows a tooltip with exact CHF + month
("CHF 52'253.40 — May 2026"). Default grain is monthly; the per-card
filter dropdown lets the user switch to weekly / daily for a deeper
look. Filled area uses `brand-500` at 25% opacity, line at full
brand-500.

Includes a small "Last 12 months ▼" filter top-right.

Data source: existing `FinanceService::getMonthlyRevenue($months)`
(LOG-02 fix already lands portably on PG + SQLite).

Size: `xl` — spans 8 of 12 columns on desktop; full width on tablet.

#### 2.2  Customer Mix (4 cols wide, same height as 2.1)

A **donut chart** with 3 segments:
- **Active customers** (had at least one work order in last 90 days) — brand-500 teal
- **Recent customers** (registered within last 30 days, no WO yet) — accent-500 coral
- **Re-engaged** (returned after 6+ months gap) — brand-700 dark teal

Centre of donut shows the total customer count.

Right of donut: a vertical legend with each segment's count and a
small dot in the segment colour. Visually identical to the
Ryvix Customers card.

Data source: NEW method `DashboardService::getCustomerMix()` —
single SQL aggregate over `customers` + `work_orders`.

Size: `half` — 4 cols wide.

### Tier 3 — Operational row (mixed sizes, replaces the current "list" widgets)

#### 3.1  Service Bay Heatmap (half width)

A 7-day × 24-hour grid (or 7-day × business-hours = 7 × 9 cells = 63
cells), each cell shaded teal in proportion to bay utilisation
(running work orders that hour). Hover a cell shows "Tuesday 14:00
— 4/6 bays in use".

Lets the shop manager see at a glance when they're slammed and when
they're idle — far more actionable than a single "free_bays" number.

Size: `half`.

#### 3.2  Top Mechanics (small, 1×1 in a 3-col stack OR half-width)

Vertical bar chart, last 30 days. X axis = mechanic name, Y axis =
work orders completed. Bars in brand-500, the top performer's bar
gets a coral cap (subtle, 8px) so it stands out without screaming.

Useful for the manager doing weekly 1:1s.

Size: `half`.

#### 3.3  Inspection Reminders (small or half)

Already exists: `getInspectionsDue()`. Modernise the visual — small
card per vehicle with plate, customer, days-until-due. Days < 7 get
a coral dot, days 7–14 get an amber dot (also coral via theme but
slightly desaturated), days 14–30 get a brand-tint dot.

Size: `half`.

### Tier 4 — Activity & status (lower section)

#### 4.1  Recent Work Orders (full-width table)

Replaces today's "All work orders" tile. A clean table:

```
| #    | Customer       | Vehicle              | Status     | Total    |
|------|----------------|----------------------|------------|----------|
| 4321 | Müller GmbH    | VW Golf · ZH 12 345  | In Stock   | CHF 1'250|
| 4322 | Schneider AG   | BMW X3 · BE 99 988   | Out Of Stock | CHF 4'500 |
```

Status pills follow the new theme — `In Stock`-style green badges
become `Done` brand-500/100, `Out of Stock` coral becomes `Stuck`
accent-500/100, etc. Per-row edit/adjust icons.

Size: `full`.

#### 4.2  Customer Activity Stream (small, sticky-right side panel)

Avatar + name + amount, like the Ryvix "Orders" panel. Mix of recent
payments (positive amount, brand-500 text) and refunds / outstanding
(negative, accent-500 text). Last 6 entries.

Size: `small`. Pinnable to right column.

#### 4.3  Low Stock Alert (small)

Existing widget, just modernised. Title + count + small list of the
3 most-critical parts. Click → inventory page.

Size: `small`.

### Tier 5 — Side rail (left column under sidebar, optional)

#### 5.1  "Upgrade your plan" card (small)

Lifted directly from the Ryvix mockup. Brand-500 background,
white text, small price tag, "Upgrade!" CTA in white-on-brand-700.
Only visible to tenants on BASIC plan; STANDARD / CUSTOM tenants
don't see it. We already have plan logic in `Tenant::hasTireHotel()`
etc. — same pattern.

Size: `small`. Pinned to bottom of side column.

---

## 4. Layout grid — desktop view

Using Tailwind's 12-column grid:

```
┌─────────────────────────────────────────────────────────────────┐
│ Page header: "Welcome back, $name — current sales overview"    │  ← row 1, 12 cols
└─────────────────────────────────────────────────────────────────┘

┌──────┬──────┬──────┬──────┐
│ KPI1 │ KPI2 │ KPI3 │ KPI4 │  ← row 2, 4× 3-col tiles
└──────┴──────┴──────┴──────┘

┌────────────────────────┬──────────────┐
│                        │              │
│ Monthly Revenue (xl)   │ Customer Mix │  ← row 3, 8 + 4
│   line+area chart      │   donut      │
│                        │              │
└────────────────────────┴──────────────┘

┌──────────────┬──────────────┐
│ Bay Heatmap  │ Top Mechanics│  ← row 4, 6 + 6
│   7×9 grid   │   bar chart  │
└──────────────┴──────────────┘

┌──────────────────────────┬──────────────┐
│ Inspections Due  (half)  │ Customer     │  ← row 5, 8 + 4
│   list w/ status dots    │ Activity     │
└──────────────────────────┴──────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ Recent Work Orders (full)                                       │  ← row 6
│   sortable table                                                │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬──────────────┐
│ Low Stock    │ Idle Techs   │ Upgrade Card │  ← row 7, 4 + 4 + 4
└──────────────┴──────────────┴──────────────┘
```

On tablet (≥768px): everything collapses to 2 columns.
On mobile (<768px): single column, KPIs become a horizontal scroll.

---

## 5. Component breakdown — what code changes

### New Blade widget files (all under `resources/views/dashboard/widgets/`)

- `kpi-revenue.blade.php` — KPI #1 with sparkline
- `kpi-active-jobs.blade.php` — KPI #2
- `kpi-outstanding.blade.php` — KPI #3 with coral sparkline
- `kpi-new-customers.blade.php` — KPI #4
- `revenue-chart.blade.php` — Tier 2.1 hero line/area
- `customer-mix.blade.php` — Tier 2.2 donut
- `bay-heatmap.blade.php` — Tier 3.1 7×9 grid
- `top-mechanics.blade.php` — Tier 3.2 bar chart
- `recent-work-orders.blade.php` — Tier 4.1 table
- `customer-activity.blade.php` — Tier 4.2 avatar stream
- `upgrade-cta.blade.php` — Tier 5.1 plan upsell

### New shared components (under `resources/views/components/dashboard/`)

- `<x-dashboard.kpi-card>` — wrapper for the KPI tile pattern (title + big number + delta pill + sparkline). Used by all four KPI widgets so they look identical.
- `<x-dashboard.chart-card>` — wrapper for any chart widget (title + filter + action menu + chart slot). Shared chrome.
- `<x-dashboard.delta-pill>` — the "+12%" up/down pill, takes a number and renders teal/coral.

### New `DashboardService` methods

- `getRevenueSparkline()` — last 30-day revenue array for KPI #1
- `getActiveJobsSparkline()` — last 30-day active-job count
- `getOutstandingSparkline()` — last 30-day unpaid-balance trend
- `getNewCustomersSparkline()` — last 30-day daily customer-creation count
- `getCustomerMix()` — active / recent / re-engaged segmentation
- `getBayUtilization()` — 7×9 grid of (day, hour) → in-progress count
- `getTopMechanics($days = 30)` — mechanic → completed-WO count

### `DashboardWidgetRegistry` updates

Add the 11 new widget keys, with sizes, default order, and category
metadata so they appear in the Studio panel correctly.

### JS bootstrap (`resources/js/app.js`)

Import ApexCharts once, expose globally. Each chart widget calls
`new ApexCharts(el, config).render()` from its own Alpine init.

---

## 6. Phased rollout — five small commits, each verifiable in isolation

### Phase 1 — Foundation (no visual change yet)

- `npm install apexcharts`
- Wire ApexCharts into `resources/js/app.js`
- Build `<x-dashboard.kpi-card>`, `<x-dashboard.chart-card>`, `<x-dashboard.delta-pill>` shell components
- Add the 7 new `DashboardService` methods (with returned-shape unit tests)

**Effort: ~3 hours.** Ship-able as one commit. Browser shows no
visual change until phase 2.

### Phase 2 — KPI row (4 small widgets)

- 4 new Blade widgets using `<x-dashboard.kpi-card>` + sparkline ApexCharts
- Add to `DashboardWidgetRegistry` with `small` size and default-enabled
- Remove the old equivalent widgets (active-jobs, monthly-revenue,
  outstanding-balance, recent-customers) from the default-enabled set

**Effort: ~2 hours.** Visible payoff: the 4-card hero row.

### Phase 3 — Hero analytics row

- `revenue-chart.blade.php` (large area chart, xl width)
- `customer-mix.blade.php` (donut, half width)

**Effort: ~3 hours.** This is where the dashboard starts feeling
like the Ryvix mockup.

### Phase 4 — Operational row

- `bay-heatmap.blade.php` (7×9 grid, half width)
- `top-mechanics.blade.php` (vertical bar chart, half width)

**Effort: ~3 hours.** Auto-shop-specific analytics live here.

### Phase 5 — Activity + polish

- `recent-work-orders.blade.php` (modernised table, full width)
- `customer-activity.blade.php` (Ryvix-style avatar stream)
- `upgrade-cta.blade.php` (sidebar plan upsell)
- Walk-through every viewport size and tweak responsive breakpoints
- Remove now-redundant old widgets from registry

**Effort: ~2 hours.**

**Total: ~13 hours of focused work, 5 commits.**

---

## 7. What we keep / change / remove

### Keep
- The widget-studio architecture (`DashboardController::studio*`, registry, drag-reorder)
- Per-user widget preferences (`users.dashboard_widgets`)
- The `getStats()` -> cached aggregates pattern in `DashboardService`
- The `inspections-due.blade.php` widget (just modernise visual)
- `quick-action-checkin.blade.php`, `quick-action-tire-storage.blade.php` (utility shortcuts, still useful)

### Change
- 12 of the existing 20 widgets get a fresh visual under `<x-dashboard.kpi-card>` or `<x-dashboard.chart-card>` chrome
- Layout shifts from "uniform small/half/full" to a deliberate mixed-size grid
- Default widget set re-curated for first-time experience

### Remove (or move to "advanced" disabled-by-default)
- `all-work-orders.blade.php` — info now lives in the KPI row + recent-work-orders table
- `pending-jobs.blade.php` — folded into active-jobs KPI delta pill
- `completed-today.blade.php` — folded into active-jobs KPI sparkline
- `pending-checkins.blade.php` — folded into a single "Today's appointments" widget if needed
- `technician-status.blade.php` — replaced by `top-mechanics.blade.php`

---

## 8. Decisions I need from you before I start

1. **ApexCharts as the chart library** — OK, or do you want me to evaluate Chart.js / something else first? (My strong recommendation: ApexCharts. Matches the reference image's aesthetic out of the box.)

2. **Phased rollout** — start phase 1 alone (foundation, no visual change yet), or do phase 1+2 in one go so you see the new KPI row immediately?

3. **Do you want me to keep the existing widgets disabled-by-default** (so power users can re-enable them via the Studio panel), or **delete them outright**? My pick: keep them disabled. Lower risk.

4. **Auto-shop relevance** — does the proposed widget mix (bay heatmap, top mechanics, inspections due, customer mix) match what your tenants actually want to see? Or is there a metric I'm missing? (Examples: average ticket value, today's appointments, parts margin, tire-hotel utilisation %.)

5. **Customer Activity panel** — do you want the right-rail "recent payments / refunds" stream the Ryvix mockup has? Or skip it for the first pass?

6. **Side rail "Upgrade your plan" card** — you're on a multi-tenant plan model with BASIC / STANDARD / CUSTOM. Show the upgrade card only to BASIC tenants, or skip the upsell entirely for now?

Once you answer those, I'll start phase 1.
