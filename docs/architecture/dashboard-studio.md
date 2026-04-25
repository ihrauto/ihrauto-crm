# Dashboard Studio

Per-user widget toggles for the tenant dashboard. Shipped 2026-04-25 as ENG-009.

## Goal

Let each user pick which widgets appear on `/dashboard` without affecting other users in the same tenant. Future feature work (TÜV reminders, dunning summary, Stripe events, etc.) ships as additional widgets — the catalog grows, the architecture doesn't.

## High-level Shape

```
User clicks "Customize" pill in header
    ↓
Alpine panel opens, populated from server-rendered config blob
    ↓
User flips a switch
    ↓
Optimistic UI flip → POST /dashboard/studio { keys: [...] }
    ↓
Server filters keys against:
    1. catalog (drop unknown silently)
    2. user permissions (drop disallowed silently)
    3. tenant plan / feature flags (drop locked silently)
    4. MAX_KEYS cap (slice)
    ↓
Persist to users.dashboard_widgets, return effective list
    ↓
Client reloads page → dashboard re-renders with new widget set
```

## Components

### `App\Support\DashboardWidgetCatalog`

Single source of truth. Each widget declares:

| Field | Purpose |
|---|---|
| `label` | shown in the Studio panel |
| `description` | one-line hint under the label |
| `category` | grouping in the panel (`operations`, `customer`, `inventory`, `shortcuts`) |
| `module` | tenant plan gate (e.g. `work-orders`); maps to `access {module}` Spatie permission and to `MODULE_FEATURE_FLAGS` for feature-flag gating |
| `permission` | optional extra Spatie permission (null = none) |
| `default_for_roles` | which roles see this widget ON when no preference is stored |
| `partial` | Blade partial path |
| `size` | `small` (stat-grid), `half` (2-col), or `full` (full-row) |
| `data_provider` | method on `DashboardService` that produces this widget's data; null = static markup |

**Adding a new widget:** 1 catalog entry + 1 Blade partial. No controller, service, or route changes.

### `App\Services\DashboardStudioService`

Methods:

- `widgetsForUser(User)` — catalog filtered to what the user can see (with `locked` flag computed).
- `enabledKeysForUser(User)` — effective enabled keys (uses stored prefs or role defaults), filtered against current plan + permissions.
- `enabledWidgetsForUser(User)` — catalog entries for the enabled keys, in catalog order, ready for rendering.
- `setEnabled(User, array $keys)` — validates against catalog + permissions + plan, caps at `MAX_KEYS`, persists. Returns the server-authoritative enabled list.
- `resetToDefault(User)` — nulls the column.

**Locked vs hidden:** plan-locked widgets stay listed with 🔒 (soft upsell). Permission-blocked widgets are hidden entirely (no tease).

**Stale preferences:** if a tenant downgrades, locked widgets in the user's stored list are filtered out at render but kept in the JSON column. Re-upgrading restores them.

### `App\Http\Controllers\DashboardStudioController`

Three JSON endpoints under `/dashboard/studio`:

- `GET /` — catalog + enabled, hydrates the open panel.
- `POST /` — persist (throttle 30/min).
- `POST /reset` — restore defaults (throttle 10/min).

### `App\Http\Controllers\DashboardController`

Loops the enabled widgets and calls each `data_provider` exactly once via per-request memoization. Two widgets sharing `getStats` produce one DB query. The legacy `DashboardService` methods (`getRecentActivities`, `getSystemStatus`, etc.) that were unused by the old monolithic view are no longer called — adding them back is now a deliberate "register them in the catalog" choice.

### Frontend

- `resources/views/dashboard/studio/trigger.blade.php` — header pill with chevron.
- `resources/views/dashboard/studio/panel.blade.php` — Alpine `dashboardStudio` component, contains both the dropdown markup and the Alpine init script (pushed into `@stack('scripts')`).
- 12 widget partials under `resources/views/dashboard/widgets/`.

The trigger only renders when `request()->routeIs('dashboard')` — keeps every other page free of the chevron.

### Mobile

`<sm`: panel is a full-screen overlay sheet so the table is scrollable. `>=sm`: dropdown anchored to the trigger (`w-[420px]`, `max-h-[70vh]`).

## Data

`users.dashboard_widgets` (JSON, nullable):

```json
{
  "version": 1,
  "enabled": ["active_jobs", "pending_jobs", "todays_schedule"]
}
```

Mass-assignment is **not** enabled on the User model. All writes go through `DashboardStudioService::setEnabled()`.

## Limits

- Max 50 keys per user (`DashboardWidgetCatalog::MAX_KEYS`).
- Key shape enforced: `^[a-z0-9_]+$`, max 64 chars (FormRequest validation).
- Unknown keys silently dropped in the service (forward-compat: a stale tab won't 422 just because we removed a widget).

## Defaults by role

Computed dynamically from the catalog's `default_for_roles` arrays. Multi-role users get a unioned + de-duped list. Empty stored list ≠ null: an empty array is the user's deliberate "show nothing" choice and is honored (renders the empty state).

## Testing

| Test | Coverage |
|---|---|
| `DashboardWidgetCatalogTest` | Every widget has required fields, partials exist, keys unique, defaults union correctly. |
| `DashboardStudioServiceTest` | Defaults, set/reset, unknown-key filter, plan-lock filter, dedupe, MAX_KEYS cap, stale-key behavior. |
| `DashboardStudioControllerTest` | Auth, validation, throttle, store/reset round-trip. |
| `DashboardRenderTest` | Role defaults, only-enabled rendering, empty state, trigger appears only on dashboard. |

## Future Work

**Phase 2** (deferred):
- AJAX swap of dashboard fragments instead of full page reload.
- Drag-to-reorder via Sortable.js (extend JSON to `{enabled: [...], order: [...]}`).
- Multiple saved layouts per user ("Morning view", "End-of-day view").
- Per-widget size (small / medium / large) instead of catalog-fixed size.

**Phase 3**: every feature shipped from the main roadmap (Stripe, TÜV reminders, dunning, etc.) lands as a widget by default. Just register in the catalog.
