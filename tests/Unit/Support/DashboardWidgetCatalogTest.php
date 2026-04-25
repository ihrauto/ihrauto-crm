<?php

namespace Tests\Unit\Support;

use App\Support\DashboardWidgetCatalog;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardWidgetCatalogTest extends TestCase
{
    #[Test]
    public function every_widget_has_the_required_keys()
    {
        $required = ['label', 'description', 'category', 'module', 'permission', 'default_for_roles', 'partial', 'size', 'data_provider'];

        foreach (DashboardWidgetCatalog::all() as $key => $widget) {
            foreach ($required as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $widget,
                    "Widget '{$key}' is missing required field '{$field}'."
                );
            }

            $this->assertContains(
                $widget['size'],
                ['small', 'half', 'full'],
                "Widget '{$key}' declares invalid size '{$widget['size']}'."
            );

            $this->assertIsArray(
                $widget['default_for_roles'],
                "Widget '{$key}' default_for_roles must be an array."
            );

            $this->assertArrayHasKey(
                $widget['category'],
                DashboardWidgetCatalog::categories(),
                "Widget '{$key}' references unknown category '{$widget['category']}'."
            );
        }
    }

    #[Test]
    public function every_buildable_widget_partial_resolves_to_a_real_blade_file()
    {
        // Coming-soon widgets have null partial — they're roadmap previews,
        // not renderable. Skip them; they get a partial when they ship.
        foreach (DashboardWidgetCatalog::all() as $key => $widget) {
            if (! empty($widget['coming_soon'])) {
                $this->assertNull(
                    $widget['partial'],
                    "Coming-soon widget '{$key}' must have null partial."
                );

                continue;
            }
            $this->assertTrue(
                View::exists($widget['partial']),
                "Widget '{$key}' points at missing partial '{$widget['partial']}'."
            );
        }
    }

    #[Test]
    public function widget_keys_are_unique()
    {
        $keys = array_keys(DashboardWidgetCatalog::all());

        $this->assertSame(
            count($keys),
            count(array_unique($keys)),
            'Duplicate widget keys detected in catalog.'
        );
    }

    #[Test]
    public function get_returns_null_for_unknown_keys()
    {
        $this->assertNull(DashboardWidgetCatalog::get('definitely-not-a-real-widget'));
        $this->assertFalse(DashboardWidgetCatalog::exists('definitely-not-a-real-widget'));
    }

    #[Test]
    public function defaults_for_roles_unions_across_multiple_roles()
    {
        $admin = DashboardWidgetCatalog::defaultsForRoles(['admin']);
        $tech = DashboardWidgetCatalog::defaultsForRoles(['technician']);
        $both = DashboardWidgetCatalog::defaultsForRoles(['admin', 'technician']);

        $this->assertGreaterThanOrEqual(count($admin), count($both));
        $this->assertGreaterThanOrEqual(count($tech), count($both));

        // De-dupe — every key in $both is unique.
        $this->assertSame(count($both), count(array_unique($both)));
    }

    #[Test]
    public function defaults_for_unknown_role_is_empty()
    {
        $this->assertSame([], DashboardWidgetCatalog::defaultsForRoles(['totally-fake-role']));
    }

    #[Test]
    public function every_data_provider_resolves_to_a_real_method_on_dashboard_service()
    {
        // Audit gap #19: a typo in the catalog (e.g. 'getStat' instead of
        // 'getStats') would only crash at render time, when that widget is
        // enabled. Catch it here.
        foreach (DashboardWidgetCatalog::all() as $key => $widget) {
            $provider = $widget['data_provider'] ?? null;
            if ($provider === null) {
                continue;
            }
            $this->assertTrue(
                method_exists(\App\Services\DashboardService::class, $provider),
                "Widget '{$key}' declares data_provider '{$provider}' which is not a method on DashboardService."
            );
        }
    }

    #[Test]
    public function every_widget_module_is_in_the_known_module_set()
    {
        // Audit gap #20: a typo in `module` (e.g. 'check-out' vs 'check-in')
        // silently locks out everyone — no permission `access check-out` exists.
        $known = ['check-in', 'tire-hotel', 'work-orders', 'finance', 'customers', 'management'];

        foreach (DashboardWidgetCatalog::all() as $key => $widget) {
            $module = $widget['module'] ?? null;
            if ($module === null) {
                continue;
            }
            $this->assertContains(
                $module,
                $known,
                "Widget '{$key}' uses unknown module '{$module}'. Update the assertion's known list if this is intentional."
            );
        }
    }
}
