<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DATA-03 (sprint 2026-04-24): PII + payout fields must be encrypted
 * at rest so a DB dump alone does not expose them. Lookup hashes on
 * customers restore deterministic exact-match queries.
 */
class PiiEncryptionAtRestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_email_phone_address_are_encrypted_at_rest(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Q Example',
            'email' => 'jane@example.com',
            'phone' => '+41791234567',
            'address' => '5 Bahnhofstrasse, 8001 Zurich',
        ]);

        $raw = DB::table('customers')->where('id', $customer->id)->first();

        // Raw DB values must not be plaintext.
        $this->assertNotSame('jane@example.com', $raw->email);
        $this->assertNotSame('+41791234567', $raw->phone);
        $this->assertNotSame('5 Bahnhofstrasse, 8001 Zurich', $raw->address);

        // Laravel `encrypted` ciphertext is base64 JSON starting with eyJ.
        $this->assertStringStartsWith('eyJ', $raw->email);
        $this->assertStringStartsWith('eyJ', $raw->phone);
        $this->assertStringStartsWith('eyJ', $raw->address);

        // Through the model the cast round-trips.
        $fresh = Customer::find($customer->id);
        $this->assertSame('jane@example.com', $fresh->email);
        $this->assertSame('+41791234567', $fresh->phone);
        $this->assertSame('5 Bahnhofstrasse, 8001 Zurich', $fresh->address);
    }

    #[Test]
    public function customer_lookup_hashes_are_populated_and_deterministic(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Case Sensitive',
            'email' => 'Case.Sensitive@Example.COM',
            'phone' => '+41 79 123 45 67',
        ]);

        $raw = DB::table('customers')->where('id', $customer->id)->first();

        // Hashes are the SHA-256 of the normalized plaintext.
        $this->assertSame(
            hash('sha256', 'case.sensitive@example.com'),
            $raw->email_hash,
            'email_hash must be lowercased+trimmed plaintext SHA-256',
        );
        $this->assertSame(
            hash('sha256', '41791234567'),
            $raw->phone_hash,
            'phone_hash must be digits-only SHA-256',
        );

        // Exact-match lookups through the hash work across whitespace
        // and case variations; the phone hash is digits-only so
        // `+41 79 …`, `41.79.123.4567`, and `417 912 345 67` all
        // collapse to the same value.
        $this->assertTrue(
            Customer::where('email_hash', Customer::lookupEmailHash('case.sensitive@example.com'))->exists()
        );
        $this->assertTrue(
            Customer::where('phone_hash', Customer::lookupPhoneHash('41.79.123.4567'))->exists()
        );
    }

    #[Test]
    public function tenant_payout_details_are_encrypted_at_rest(): void
    {
        $tenant = Tenant::factory()->create([
            'iban' => 'CH9300762011623852957',
            'bank_name' => 'UBS',
            'account_holder' => 'IHRAUTO GmbH',
            'invoice_email' => 'billing@ihrauto.ch',
            'invoice_phone' => '+41 44 123 45 67',
        ]);

        $raw = DB::table('tenants')->where('id', $tenant->id)->first();

        $this->assertNotSame('CH9300762011623852957', $raw->iban);
        $this->assertNotSame('UBS', $raw->bank_name);
        $this->assertNotSame('IHRAUTO GmbH', $raw->account_holder);
        $this->assertNotSame('billing@ihrauto.ch', $raw->invoice_email);
        $this->assertNotSame('+41 44 123 45 67', $raw->invoice_phone);

        $this->assertStringStartsWith('eyJ', $raw->iban);

        $fresh = Tenant::find($tenant->id);
        $this->assertSame('CH9300762011623852957', $fresh->iban);
        $this->assertSame('UBS', $fresh->bank_name);
        $this->assertSame('IHRAUTO GmbH', $fresh->account_holder);
        $this->assertSame('billing@ihrauto.ch', $fresh->invoice_email);
        $this->assertSame('+41 44 123 45 67', $fresh->invoice_phone);
    }
}
