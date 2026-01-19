<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function user_can_view_inventory_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('products-services.index'));

        $response->assertStatus(200);
        $response->assertViewIs('products-services.index');
    }

    /** @test */
    public function user_can_create_product_with_all_fields()
    {
        $productData = [
            'name' => 'Test Brake Pad',
            'sku' => 'BP-TEST-001',
            'price' => 45.50,
            'stock_quantity' => 100,
            'min_stock_quantity' => 10,
            'unit' => 'Pieces',
            'purchase_price' => 25.00,
            'order_number' => 'ORD-12345',
            'supplier' => 'Test Supplier',
            'status' => 'in_stock',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('products.store'), $productData);

        $response->assertRedirect(route('products-services.index', ['tab' => 'parts']));

        $this->assertDatabaseHas('products', [
            'name' => 'Test Brake Pad',
            'sku' => 'BP-TEST-001',
            'price' => 45.50,
            'stock_quantity' => 100,
            'unit' => 'Pieces',
            'purchase_price' => 25.00,
            'order_number' => 'ORD-12345',
            'supplier' => 'Test Supplier',
            'status' => 'in_stock',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function user_can_update_product_with_all_fields()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        $updateData = [
            'name' => 'Updated Brake Pad',
            'sku' => 'BP-UPD-001',
            'price' => 55.00,
            'stock_quantity' => 150,
            'min_stock_quantity' => 15,
            'unit' => 'Sets',
            'purchase_price' => 30.00,
            'order_number' => 'ORD-99999',
            'supplier' => 'New Supplier',
            'status' => 'ordered',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('products.update', $product), $updateData);

        $response->assertRedirect(route('products-services.index', ['tab' => 'parts']));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Brake Pad',
            'unit' => 'Sets',
            'status' => 'ordered',
        ]);
    }

    /** @test */
    public function search_is_case_insensitive()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Glass Window',
            'sku' => 'GLS-001',
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Brake Pad',
            'sku' => 'BRK-001',
        ]);

        // Search lowercase should find uppercase
        $response = $this->actingAs($this->user)
            ->get(route('products-services.index', ['tab' => 'parts', 'search' => 'glass']));

        $response->assertStatus(200);
        $response->assertSee('Glass Window');
        $response->assertDontSee('Brake Pad');
    }

    /** @test */
    public function search_works_with_sku()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oil Filter',
            'sku' => 'OIL-FILTER-001',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products-services.index', ['tab' => 'parts', 'search' => 'oil-filter']));

        $response->assertStatus(200);
        $response->assertSee('Oil Filter');
    }

    /** @test */
    public function user_can_download_import_template()
    {
        $response = $this->actingAs($this->user)
            ->get(route('products.import.template'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="products_template.csv"');
    }

    /** @test */
    public function user_can_import_products_via_csv()
    {
        $csvContent = "Name,SKU,Price,Quantity,Min Stock\n";
        $csvContent .= "Imported Part,IMP-001,99.99,50,5\n";
        $csvContent .= "Another Part,IMP-002,149.99,25,10\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post(route('products.import'), ['file' => $file]);

        $response->assertRedirect(route('products-services.index', ['tab' => 'parts']));

        $this->assertDatabaseHas('products', [
            'name' => 'Imported Part',
            'sku' => 'IMP-001',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Another Part',
            'sku' => 'IMP-002',
        ]);
    }

    /** @test */
    public function product_requires_name_and_price()
    {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'stock_quantity' => 10,
                'min_stock_quantity' => 5,
            ]);

        $response->assertSessionHasErrors(['name', 'price']);
    }

    /** @test */
    public function status_must_be_valid_value()
    {
        $response = $this->actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Test Product',
                'price' => 50.00,
                'stock_quantity' => 10,
                'min_stock_quantity' => 5,
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function products_are_tenant_isolated()
    {
        // Create product for our tenant
        $ourProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Our Product',
        ]);

        // Create product for another tenant
        $otherTenant = Tenant::factory()->create();
        $theirProduct = Product::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Their Product',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products-services.index', ['tab' => 'parts']));

        $response->assertSee('Our Product');
        $response->assertDontSee('Their Product');
    }
}
