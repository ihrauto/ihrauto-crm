<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for the tab-highlight bug: after adding the DRAFT tab, the
 * ISSUED tab's active-class check was a negative match on a hard-coded
 * list that didn't include "draft", so two tabs lit up at once.
 *
 * Assertion: for each possible `?tab=…` value, EXACTLY ONE tab link
 * carries `border-indigo-500`.
 */
class FinanceTabsActiveStateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $this->admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ]);
        $this->admin->assignRole('admin');
    }

    /**
     * Count how many tab links carry the `border-indigo-500` class.
     *
     * We split the HTML on `</a>` so each segment contains at most one
     * anchor's opening tag + inner content, then check each segment
     * individually — a greedy regex across the whole page walked across
     * anchor boundaries and matched the wrong class attribute.
     */
    private function countActiveTabs(string $html): int
    {
        $active = 0;
        $labels = ['ISSUED', 'UNPAID', 'DRAFT', 'PAID', 'ALL'];

        foreach (explode('</a>', $html) as $segment) {
            // Narrow to segments that end with a tab label.
            $isTabSegment = false;
            foreach ($labels as $label) {
                if (str_contains($segment, $label)) {
                    $isTabSegment = true;
                    break;
                }
            }
            if (! $isTabSegment) {
                continue;
            }

            // Pull the LAST `<a …>` in the segment (closest to the label).
            if (preg_match_all('/<a[^>]*class="([^"]*)"/', $segment, $m)
                && str_contains(end($m[1]), 'border-indigo-500')) {
                $active++;
            }
        }

        return $active;
    }

    public function test_exactly_one_tab_is_active_for_each_tab_value(): void
    {
        foreach (['issued', 'unpaid', 'draft', 'paid', 'all'] as $tab) {
            $response = $this->actingAs($this->admin)->get("/finance?tab={$tab}");
            $response->assertOk();

            $active = $this->countActiveTabs($response->getContent());
            $this->assertSame(
                1,
                $active,
                "Expected exactly 1 active tab for ?tab={$tab}, got {$active}"
            );
        }
    }

    public function test_issued_is_active_by_default_when_tab_missing(): void
    {
        $response = $this->actingAs($this->admin)->get('/finance');
        $response->assertOk();

        $this->assertStringContainsString(
            'border-indigo-500',
            $this->extractAnchorContaining($response->getContent(), 'ISSUED'),
        );
    }

    private function extractAnchorContaining(string $html, string $label): string
    {
        if (preg_match('/<a[^>]*>[\s\S]*?'.$label.'[\s\S]*?<\/a>/', $html, $m)) {
            return $m[0];
        }

        return '';
    }
}
