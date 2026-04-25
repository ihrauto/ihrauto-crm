<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardStudioService;
use App\Support\DashboardWidgetCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardStudioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardStudioService $service;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Prior tests can leave TenantCache entries behind that pin a
        // stale features array on a tenant we then mutate via update().
        // Flush before each studio test so plan-feature checks are honest.
        \Illuminate\Support\Facades\Cache::flush();

        $this->service = new DashboardStudioService;
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function null_preference_falls_back_to_role_defaults()
    {
        $this->assertNull($this->admin->dashboard_widgets);

        $enabled = $this->service->enabledKeysForUser($this->admin);

        $expected = DashboardWidgetCatalog::defaultsForRoles(['admin']);
        // expected is filtered against plan + permission, but admin/full plan should keep everything
        $this->assertNotEmpty($enabled);
        foreach ($enabled as $key) {
            $this->assertContains($key, $expected, "Default '{$key}' should be in admin's role defaults.");
        }
    }

    #[Test]
    public function set_enabled_persists_only_known_keys()
    {
        $effective = $this->service->setEnabled($this->admin, [
            'active_jobs',
            'totally-fake-widget',
            'pending_jobs',
        ]);

        $this->assertContains('active_jobs', $effective);
        $this->assertContains('pending_jobs', $effective);
        $this->assertNotContains('totally-fake-widget', $effective);

        $this->admin->refresh();
        $this->assertSame(['active_jobs', 'pending_jobs'], $this->admin->dashboard_widgets['enabled']);
    }

    #[Test]
    public function set_enabled_drops_widgets_the_user_lacks_permission_for()
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $tech->assignRole('technician');

        // technician lacks `access management` so technician_status must be dropped.
        $effective = $this->service->setEnabled($tech, ['active_jobs', 'technician_status']);

        $this->assertContains('active_jobs', $effective);
        $this->assertNotContains('technician_status', $effective);
    }

    #[Test]
    public function set_enabled_dedupes_keys()
    {
        $effective = $this->service->setEnabled($this->admin, [
            'active_jobs',
            'active_jobs',
            'active_jobs',
        ]);

        $this->assertSame(['active_jobs'], $effective);
    }

    #[Test]
    public function set_enabled_caps_list_at_max_keys()
    {
        $bloat = array_fill(0, 100, 'active_jobs');
        // (after de-dupe this becomes 1, so add real different keys to test the cap)
        $catalogKeys = array_keys(DashboardWidgetCatalog::all());
        $bigList = array_merge($catalogKeys, array_fill(0, 100, 'active_jobs'));

        $effective = $this->service->setEnabled($this->admin, $bigList);

        $this->assertLessThanOrEqual(DashboardWidgetCatalog::MAX_KEYS, count($effective));
    }

    #[Test]
    public function reset_to_default_clears_stored_preference()
    {
        $this->service->setEnabled($this->admin, ['active_jobs']);
        $this->admin->refresh();
        $this->assertNotNull($this->admin->dashboard_widgets);

        $this->service->resetToDefault($this->admin);
        $this->admin->refresh();
        $this->assertNull($this->admin->dashboard_widgets);
    }

    #[Test]
    public function widgets_for_user_marks_plan_locked_widgets_as_locked()
    {
        // The 'tire-hotel' module is gated by a tenant feature flag (tire_hotel).
        // Disable it on this tenant so the widget stays visible but locked.
        $this->tenant->update(['features' => array_filter(
            $this->tenant->features ?? [],
            fn ($f) => $f !== 'tire_hotel'
        )]);
        $this->admin->setRelation('tenant', $this->tenant->fresh());

        $widgets = $this->service->widgetsForUser($this->admin);

        $tireWidget = collect($widgets)->firstWhere('key', 'quick_action_tire_storage');
        $this->assertNotNull($tireWidget, 'tire-storage widget should still appear in catalog (locked).');
        $this->assertTrue($tireWidget['locked'], 'Widget should be marked locked when tenant lacks the feature flag.');
        $this->assertSame('plan', $tireWidget['lock_reason']);
        $this->assertSame('Upgrade', $tireWidget['lock_label']);
    }

    #[Test]
    public function widgets_for_user_marks_permission_blocked_widgets_as_locked_not_hidden()
    {
        // Receptionists lack `access management` permission.
        $reception = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $reception->assignRole('receptionist');

        $widgets = $this->service->widgetsForUser($reception);

        // technician_status requires `access management` — it should be in
        // the catalog response but locked with reason='permission'.
        $techStatus = collect($widgets)->firstWhere('key', 'technician_status');
        $this->assertNotNull($techStatus, 'permission-blocked widgets must still appear in the catalog.');
        $this->assertTrue($techStatus['locked']);
        $this->assertSame('permission', $techStatus['lock_reason']);
    }

    #[Test]
    public function plan_locked_widgets_cannot_be_set_enabled()
    {
        $this->tenant->update(['features' => array_filter(
            $this->tenant->features ?? [],
            fn ($f) => $f !== 'tire_hotel'
        )]);
        $this->admin->setRelation('tenant', $this->tenant->fresh());

        $effective = $this->service->setEnabled($this->admin, ['quick_action_tire_storage', 'active_jobs']);

        $this->assertNotContains('quick_action_tire_storage', $effective);
        $this->assertContains('active_jobs', $effective);
    }

    #[Test]
    public function enabled_widgets_for_user_returns_catalog_entries_in_catalog_order_when_no_saved_order()
    {
        $this->service->setEnabled($this->admin, ['technician_status', 'active_jobs']);

        $widgets = $this->service->enabledWidgetsForUser($this->admin);

        $keys = array_column($widgets, 'key');
        $catalogKeys = array_keys(DashboardWidgetCatalog::all());

        $activePos = array_search('active_jobs', $keys);
        $techPos = array_search('technician_status', $keys);
        $catalogActive = array_search('active_jobs', $catalogKeys);
        $catalogTech = array_search('technician_status', $catalogKeys);

        $this->assertSame(
            $catalogActive < $catalogTech,
            $activePos < $techPos,
            'enabledWidgetsForUser should preserve catalog order when no order is saved.'
        );
    }

    #[Test]
    public function enabled_widgets_for_user_honors_saved_drag_order()
    {
        $this->service->setEnabled($this->admin, ['active_jobs', 'technician_status', 'pending_jobs']);
        // Reverse what catalog order would produce.
        $this->service->setOrder($this->admin, ['technician_status', 'pending_jobs', 'active_jobs']);

        $this->admin->refresh();
        $widgets = $this->service->enabledWidgetsForUser($this->admin);
        $keys = array_column($widgets, 'key');

        $this->assertSame(
            ['technician_status', 'pending_jobs', 'active_jobs'],
            $keys,
            'enabledWidgetsForUser should follow the user-saved drag order.'
        );
    }

    #[Test]
    public function newly_enabled_widget_appended_after_saved_order()
    {
        $this->service->setEnabled($this->admin, ['active_jobs', 'pending_jobs']);
        $this->service->setOrder($this->admin, ['pending_jobs', 'active_jobs']);

        // User flips on a third widget — wasn't in saved order. Should
        // appear at the end (catalog-order tiebreaker).
        $this->service->setEnabled($this->admin, ['active_jobs', 'pending_jobs', 'completed_today']);

        $this->admin->refresh();
        $widgets = $this->service->enabledWidgetsForUser($this->admin);
        $keys = array_column($widgets, 'key');

        $this->assertSame('completed_today', end($keys), 'Newly enabled widget should land at the end.');
        $this->assertSame(['pending_jobs', 'active_jobs', 'completed_today'], $keys);
    }

    #[Test]
    public function set_enabled_preserves_plan_locked_keys_across_redowngrade_resave()
    {
        // Audit B-3: when the tenant loses a feature flag and the user
        // re-saves their preferences (without that locked widget), we
        // should NOT silently drop the locked key. It must round-trip
        // so re-upgrading restores it.
        $this->service->setEnabled($this->admin, ['quick_action_tire_storage', 'active_jobs']);

        // Tenant loses tire_hotel feature flag.
        $this->tenant->update(['features' => array_filter(
            $this->tenant->features ?? [],
            fn ($f) => $f !== 'tire_hotel'
        )]);
        $this->admin->setRelation('tenant', $this->tenant->fresh());

        // User toggles something else — re-saves the visible state which
        // no longer includes quick_action_tire_storage (it's locked now).
        $this->service->setEnabled($this->admin, ['active_jobs', 'pending_jobs']);

        $this->admin->refresh();
        $this->assertContains(
            'quick_action_tire_storage',
            $this->admin->dashboard_widgets['enabled'],
            'Plan-locked previously-enabled keys must survive a re-save during a feature-flag downtime.'
        );
    }

    #[Test]
    public function set_enabled_prunes_disabled_keys_from_saved_order()
    {
        // Audit B-2: after toggling a widget off, its key should drop out
        // of the saved drag-order. Otherwise the order array bloats.
        $this->service->setEnabled($this->admin, ['active_jobs', 'pending_jobs', 'completed_today']);
        $this->service->setOrder($this->admin, ['completed_today', 'pending_jobs', 'active_jobs']);

        $this->service->setEnabled($this->admin, ['active_jobs', 'pending_jobs']);

        $this->admin->refresh();
        $this->assertSame(
            ['pending_jobs', 'active_jobs'],
            $this->admin->dashboard_widgets['order'],
            'setEnabled should remove disabled widgets from the stored order.'
        );
    }

    #[Test]
    public function set_order_intersects_with_currently_enabled_keys()
    {
        // Audit B-1: setOrder shouldn't accept keys that aren't enabled.
        $this->service->setEnabled($this->admin, ['active_jobs', 'pending_jobs']);
        $this->service->setOrder($this->admin, ['pending_jobs', 'active_jobs', 'completed_today']);

        $this->admin->refresh();
        $this->assertSame(
            ['pending_jobs', 'active_jobs'],
            $this->admin->dashboard_widgets['order'],
            'Stored order should only contain currently-enabled keys.'
        );
    }

    #[Test]
    public function set_order_does_not_freeze_role_defaults_into_enabled()
    {
        // Audit B-4 / B-6: the previous implementation, when called on a
        // user with no stored preference, materialized the (filtered) role
        // defaults into stored.enabled — converting "no preference, use
        // defaults" into "preference: this exact set", which broke future
        // catalog additions for that user.
        $this->assertNull($this->admin->dashboard_widgets);

        $this->service->setOrder($this->admin, ['active_jobs', 'pending_jobs']);

        $this->admin->refresh();
        $this->assertArrayNotHasKey(
            'enabled',
            $this->admin->dashboard_widgets,
            'setOrder must not materialize an enabled list when the user is on role defaults.'
        );
    }

    #[Test]
    public function set_order_drops_unknown_keys()
    {
        $this->service->setOrder($this->admin, ['active_jobs', 'totally_fake', 'pending_jobs']);

        $this->admin->refresh();
        $this->assertSame(
            ['active_jobs', 'pending_jobs'],
            $this->admin->dashboard_widgets['order']
        );
    }

    #[Test]
    public function enabled_keys_filters_stale_keys_at_render_time()
    {
        // Manually persist an enabled list including a key the tenant lost
        // access to (simulate: stored 'quick_action_tire_storage' then plan
        // dropped). The renderer should silently skip it without erasing
        // the preference, so re-upgrading restores it.
        $this->admin->forceFill([
            'dashboard_widgets' => [
                'version' => DashboardWidgetCatalog::VERSION,
                'enabled' => ['active_jobs', 'quick_action_tire_storage'],
            ],
        ])->save();

        $this->tenant->update(['features' => array_filter(
            $this->tenant->features ?? [],
            fn ($f) => $f !== 'tire_hotel'
        )]);
        $this->admin->setRelation('tenant', $this->tenant->fresh());

        $effective = $this->service->enabledKeysForUser($this->admin->fresh());

        $this->assertContains('active_jobs', $effective);
        $this->assertNotContains('quick_action_tire_storage', $effective);

        // Stored JSON unchanged — we haven't lost the preference.
        $this->admin->refresh();
        $this->assertContains('quick_action_tire_storage', $this->admin->dashboard_widgets['enabled']);
    }
}
