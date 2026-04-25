<?php

namespace App\Services;

use App\Models\User;
use App\Support\DashboardWidgetCatalog;

/**
 * ENG-009: Dashboard Studio — per-user widget preferences.
 *
 * Reads/writes the `users.dashboard_widgets` JSON column. Returns the
 * full catalog to every user — every widget is visible in the Studio
 * panel with a `locked` flag and a semantic `lock_reason`:
 *
 *   - null            — user can toggle this widget freely
 *   - 'permission'    — user's role doesn't grant the underlying access
 *   - 'plan'          — tenant's plan / feature flag doesn't include it
 *   - 'coming_soon'   — feature on the roadmap, not built yet
 *
 * Locked widgets cannot be persisted to the user's enabled list. The
 * panel shows the appropriate badge per reason so users see what is
 * available, what to upgrade for, and what is coming.
 */
class DashboardStudioService
{
    /**
     * Map of "module key in catalog" → "tenant feature flag". Mirrors
     * CheckModuleAccess::$featureMap intentionally — when one changes,
     * both should change in lockstep.
     */
    private const MODULE_FEATURE_FLAGS = [
        'check-in' => 'vehicle_checkin',
        'tire-hotel' => 'tire_hotel',
    ];

    /**
     * Every widget in the catalog, with locked + lock_reason + lock_label
     * computed for this user. Nothing is filtered out — visibility is
     * the point of the Studio panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function widgetsForUser(User $user): array
    {
        $tenant = $user->tenant;
        $out = [];

        foreach (DashboardWidgetCatalog::all() as $key => $widget) {
            $reason = $this->computeLockReason($user, $tenant, $widget);

            $out[] = array_merge($widget, [
                'key' => $key,
                'locked' => $reason !== null,
                'lock_reason' => $reason,
                'lock_label' => $reason ? $this->lockLabelFor($reason) : null,
            ]);
        }

        return $out;
    }

    /**
     * Effective enabled widget keys for this user. If the user has no
     * stored preference, fall back to role-based defaults from the
     * catalog. In both paths, filter to keys the user can actually see
     * + plan can render — preserves stale enabled keys silently when the
     * tenant downgrades.
     *
     * @return array<int, string>
     */
    public function enabledKeysForUser(User $user): array
    {
        $stored = $user->dashboard_widgets;

        if (is_array($stored) && isset($stored['enabled']) && is_array($stored['enabled'])) {
            $candidate = $stored['enabled'];
        } else {
            $candidate = DashboardWidgetCatalog::defaultsForRoles(
                $user->getRoleNames()->all()
            );
        }

        $tenant = $user->tenant;
        $effective = [];
        foreach ($candidate as $key) {
            $widget = DashboardWidgetCatalog::get($key);
            if ($widget === null) {
                continue;
            }
            if ($this->computeLockReason($user, $tenant, $widget) !== null) {
                continue;
            }
            $effective[] = $key;
        }

        return $effective;
    }

    /**
     * Catalog entries (with `key`) for the widgets currently enabled and
     * renderable for this user. Honors the user's saved drag-reorder
     * (`order` array) when present; falls back to catalog order. Keys
     * present in `enabled` but missing from `order` are appended at the
     * end in catalog order — so newly-enabled widgets land at the bottom
     * until the user re-arranges.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enabledWidgetsForUser(User $user): array
    {
        $enabled = array_flip($this->enabledKeysForUser($user));
        $stored = $user->dashboard_widgets;
        $savedOrder = is_array($stored) && isset($stored['order']) && is_array($stored['order'])
            ? $stored['order']
            : [];

        $out = [];
        $emitted = [];

        // First: walk the user's saved order; emit any that are still enabled.
        foreach ($savedOrder as $key) {
            if (! is_string($key) || isset($emitted[$key]) || ! isset($enabled[$key])) {
                continue;
            }
            $widget = DashboardWidgetCatalog::get($key);
            if ($widget === null) {
                continue;
            }
            $out[] = array_merge($widget, ['key' => $key]);
            $emitted[$key] = true;
        }

        // Then: append any enabled widgets the saved order didn't mention,
        // in catalog order, so newly-enabled widgets show up predictably.
        foreach (DashboardWidgetCatalog::all() as $key => $widget) {
            if (! isset($enabled[$key]) || isset($emitted[$key])) {
                continue;
            }
            $out[] = array_merge($widget, ['key' => $key]);
        }

        return $out;
    }

    /**
     * Persist a new widget order. Unknown keys are dropped silently;
     * the list is capped at MAX_KEYS. The persisted `order` is also
     * intersected with the currently-enabled list so disabled widgets
     * never accumulate in the JSON column.
     *
     * Refreshes the user model first to mitigate a lost-update race
     * with a concurrent setEnabled call from another tab. (Audit B-5.)
     *
     * @param  array<int, string>  $order
     * @return array<int, string>
     */
    public function setOrder(User $user, array $order): array
    {
        $clean = [];
        foreach ($order as $key) {
            if (! is_string($key) || ! DashboardWidgetCatalog::exists($key)) {
                continue;
            }
            $clean[$key] = true;
        }

        $clean = array_keys($clean);
        if (count($clean) > DashboardWidgetCatalog::MAX_KEYS) {
            $clean = array_slice($clean, 0, DashboardWidgetCatalog::MAX_KEYS);
        }

        $user->refresh();
        // Same tenant-freshness rationale as setEnabled.
        $tenant = $user->tenant_id ? \App\Models\Tenant::find($user->tenant_id) : null;
        $user->setRelation('tenant', $tenant);

        $stored = $user->dashboard_widgets ?? [];
        $stored['version'] = DashboardWidgetCatalog::VERSION;

        // Audit B-1: prune order to only widgets the user has enabled.
        // Stops the order list from growing every time the user disables
        // a widget without reordering. If `enabled` isn't stored yet, we
        // intentionally do NOT materialize it here (audit B-4 / B-6) —
        // freezing role-defaults into stored on first reorder breaks
        // future catalog additions for that user.
        $enabledSet = isset($stored['enabled']) && is_array($stored['enabled'])
            ? array_flip($stored['enabled'])
            : null;
        if ($enabledSet !== null) {
            $clean = array_values(array_filter($clean, fn ($k) => isset($enabledSet[$k])));
        }

        $stored['order'] = $clean;

        $user->forceFill(['dashboard_widgets' => $stored])->save();

        return $clean;
    }

    /**
     * Persist a new enabled list. Locked widgets are dropped silently
     * (forward-compat: a stale tab won't 422 just because we added a
     * lock). Unknown keys are dropped. Capped at MAX_KEYS.
     *
     * Audit B-3 fix: keys that the input dropped purely because they
     * are currently plan-locked are merged back from the previously-
     * stored enabled list. So a tenant downgrade → re-save → re-upgrade
     * cycle preserves the user's preference instead of silently wiping
     * it. Permission-blocked keys are NOT preserved: those reflect a
     * deliberate role change, not a transient plan state.
     *
     * Refreshes the user model first to mitigate concurrent-tab races.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public function setEnabled(User $user, array $keys): array
    {
        $user->refresh();
        // Audit-B-3 fix relies on a fresh tenant feature-flag set —
        // refresh() reloads attributes but not loaded relations, so
        // explicitly fetch a fresh tenant to honor a just-flipped flag.
        $tenant = $user->tenant_id ? \App\Models\Tenant::find($user->tenant_id) : null;
        $user->setRelation('tenant', $tenant);

        $clean = [];
        foreach ($keys as $key) {
            if (! is_string($key) || ! DashboardWidgetCatalog::exists($key)) {
                continue;
            }
            $widget = DashboardWidgetCatalog::get($key);
            if ($this->computeLockReason($user, $tenant, $widget) !== null) {
                continue;
            }
            $clean[$key] = true;
        }

        // Preserve previously-enabled keys that are currently plan-locked
        // so a re-save during a feature-flag downtime doesn't wipe them.
        $previous = is_array($user->dashboard_widgets['enabled'] ?? null)
            ? $user->dashboard_widgets['enabled']
            : [];
        foreach ($previous as $key) {
            if (! is_string($key) || isset($clean[$key]) || ! DashboardWidgetCatalog::exists($key)) {
                continue;
            }
            $widget = DashboardWidgetCatalog::get($key);
            if ($this->computeLockReason($user, $tenant, $widget) === 'plan') {
                $clean[$key] = true;
            }
        }

        $clean = array_keys($clean);
        if (count($clean) > DashboardWidgetCatalog::MAX_KEYS) {
            $clean = array_slice($clean, 0, DashboardWidgetCatalog::MAX_KEYS);
        }

        $stored = $user->dashboard_widgets ?? [];
        $stored['version'] = DashboardWidgetCatalog::VERSION;
        $stored['enabled'] = $clean;

        // Audit B-2: keep stored `order` in sync with the new enabled
        // set so disabled widgets don't linger and re-enabling appends.
        if (isset($stored['order']) && is_array($stored['order'])) {
            $enabledSet = array_flip($clean);
            $stored['order'] = array_values(array_filter(
                $stored['order'],
                fn ($k) => is_string($k) && isset($enabledSet[$k]),
            ));
        }

        $user->forceFill(['dashboard_widgets' => $stored])->save();

        return $clean;
    }

    /**
     * @return array<int, string>
     */
    public function resetToDefault(User $user): array
    {
        $user->forceFill(['dashboard_widgets' => null])->save();

        return $this->enabledKeysForUser($user);
    }

    /**
     * Returns the lock reason (or null) for this user × widget.
     * Order of precedence:
     *   1. coming_soon (always wins; no point gating an unbuilt feature)
     *   2. permission  (user's role)
     *   3. plan        (tenant feature flag)
     */
    private function computeLockReason(User $user, $tenant, array $widget): ?string
    {
        if (! empty($widget['coming_soon'])) {
            return 'coming_soon';
        }

        if (! empty($widget['permission']) && ! $user->can($widget['permission'])) {
            return 'permission';
        }

        if (! empty($widget['module'])) {
            $modulePermission = 'access '.$widget['module'];
            if (! $user->can($modulePermission)) {
                return 'permission';
            }
        }

        $module = $widget['module'] ?? null;
        if ($tenant && $module !== null) {
            $featureFlag = self::MODULE_FEATURE_FLAGS[$module] ?? null;
            if ($featureFlag !== null && ! $tenant->hasFeature($featureFlag)) {
                return 'plan';
            }
        }

        return null;
    }

    private function lockLabelFor(string $reason): string
    {
        return match ($reason) {
            'coming_soon' => 'Soon',
            'plan' => 'Upgrade',
            'permission' => 'No access',
            default => 'Locked',
        };
    }
}
