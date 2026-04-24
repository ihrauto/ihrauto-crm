<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DATA-03 (sprint 2026-04-24): encrypt PII + payout-destination columns
 * at rest using Laravel's `encrypted` cast.
 *
 * Target columns:
 *   tenants:   iban, bank_name, account_holder, invoice_email, invoice_phone
 *   customers: email, phone, address
 *
 * Threat model: a database dump or compromised backup archive previously
 * leaked IBANs + customer contact details in cleartext. `encrypted` cast
 * wraps the plaintext in AES-256-CBC with APP_KEY, base64-encoded with an
 * HMAC. An attacker with the dump alone cannot read the values without
 * also having APP_KEY.
 *
 * Migration strategy:
 *   1. Widen the string columns to TEXT. Encrypted values are ~4x the
 *      plaintext length; a 255-char email doesn't fit in a 255-char
 *      varchar after encryption.
 *   2. Add deterministic SHA-256 hash columns on customers for
 *      exact-match lookups (phone_hash, email_hash). Encryption makes
 *      `WHERE email = ?` impossible — the hash column restores it for
 *      the `TireStorageService::where('phone')` path and any future
 *      exact-match callers, while LIKE-style customer search on
 *      email/phone is deliberately dropped (the admin search falls
 *      back to name / city / postal_code — see CustomerController).
 *   3. Re-encrypt existing rows by reading plaintext, writing it back
 *      through the cast via the model. The cast layer handles the
 *      round-trip; we only need to touch every row so the cast fires.
 *
 * Reversibility: down() can widen back to varchar but CANNOT restore
 * plaintext from ciphertext (that's the point). Rolling back this
 * migration on production would orphan the encrypted values — only
 * safe to rollback during the same deploy window.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Widen the columns to TEXT. Postgres accepts ALTER COLUMN TYPE.
            DB::statement('ALTER TABLE customers ALTER COLUMN email TYPE TEXT');
            DB::statement('ALTER TABLE customers ALTER COLUMN phone TYPE TEXT');
            DB::statement('ALTER TABLE customers ALTER COLUMN address TYPE TEXT');

            DB::statement('ALTER TABLE tenants ALTER COLUMN iban TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN bank_name TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN account_holder TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN invoice_email TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN invoice_phone TYPE TEXT');
        }
        // SQLite has TEXT affinity for all string-like columns — the
        // Schema::string varchar declarations don't actually enforce a
        // length. No ALTER needed.

        // Deterministic lookup hashes on customers. `char(64)` fits a
        // SHA-256 hex digest exactly. Nullable because email is
        // nullable (customers can be phone-only).
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'email_hash')) {
                $table->char('email_hash', 64)->nullable()->after('email');
            }
            if (! Schema::hasColumn('customers', 'phone_hash')) {
                $table->char('phone_hash', 64)->nullable()->after('phone');
            }
        });

        // Index the hashes for lookup performance. Named indexes so the
        // down() migration can find them.
        //
        // The `(tenant_id, email)` unique constraint from migration
        // 2025_07_10_114859 is moved to `(tenant_id, email_hash)` —
        // the encrypted `email` column can't enforce uniqueness
        // because random IVs guarantee every ciphertext differs.
        // `phone_hash` gets a non-unique index (phone is not unique
        // in the original schema either — see TireStorageService
        // deduplication, which is advisory not a hard rule).
        Schema::table('customers', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('customers'))->pluck('name')->all();

            if (in_array('customers_tenant_email_unique', $indexes, true)) {
                $table->dropUnique('customers_tenant_email_unique');
            }
            if (! in_array('customers_tenant_email_hash_unique', $indexes, true)) {
                $table->unique(['tenant_id', 'email_hash'], 'customers_tenant_email_hash_unique');
            }
            if (! in_array('customers_phone_hash_index', $indexes, true)) {
                $table->index('phone_hash', 'customers_phone_hash_index');
            }
        });

        // Re-encrypt existing rows. Reading the plaintext then saving
        // the model is the only way to round-trip through Eloquent's
        // `encrypted` cast. We use `saveQuietly` so observers (audit
        // log, cache-forget) don't fire — the content is unchanged,
        // only the storage representation changes.
        //
        // This runs for both Postgres and SQLite so test fixtures
        // created before the cast was in place are brought into sync.
        \App\Models\Customer::withoutGlobalScopes()->chunkById(200, function ($chunk) {
            foreach ($chunk as $customer) {
                // At this point the cast is already applied (the model
                // was updated in the same deploy), so reading a
                // plaintext row returns the plaintext and writing it
                // back will encrypt. Populate the lookup hashes on
                // the same pass.
                $customer->email = $customer->getAttributes()['email'] ?? null;
                $customer->phone = $customer->getAttributes()['phone'] ?? null;
                $customer->address = $customer->getAttributes()['address'] ?? null;
                $customer->saveQuietly();
            }
        });

        \App\Models\Tenant::withTrashed()->chunkById(100, function ($chunk) {
            foreach ($chunk as $tenant) {
                $tenant->iban = $tenant->getAttributes()['iban'] ?? null;
                $tenant->bank_name = $tenant->getAttributes()['bank_name'] ?? null;
                $tenant->account_holder = $tenant->getAttributes()['account_holder'] ?? null;
                $tenant->invoice_email = $tenant->getAttributes()['invoice_email'] ?? null;
                $tenant->invoice_phone = $tenant->getAttributes()['invoice_phone'] ?? null;
                $tenant->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Drop lookup hashes. We cannot restore plaintext from the
        // encrypted columns; operators who need a rollback must treat
        // the encrypted values as opaque and plan accordingly.
        Schema::table('customers', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('customers'))->pluck('name')->all();
            if (in_array('customers_tenant_email_hash_unique', $indexes, true)) {
                $table->dropUnique('customers_tenant_email_hash_unique');
            }
            if (in_array('customers_phone_hash_index', $indexes, true)) {
                $table->dropIndex('customers_phone_hash_index');
            }
            // Restore the pre-DATA-03 unique constraint for rollbacks.
            if (! in_array('customers_tenant_email_unique', $indexes, true)) {
                $table->unique(['tenant_id', 'email'], 'customers_tenant_email_unique');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'email_hash')) {
                $table->dropColumn('email_hash');
            }
            if (Schema::hasColumn('customers', 'phone_hash')) {
                $table->dropColumn('phone_hash');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            // Narrow TEXT back to VARCHAR(255). Any row whose encrypted
            // value exceeds 255 chars will fail — this is acceptable
            // because down() is only for the same-deploy rollback case
            // before any new encrypted writes exist. For a later
            // rollback, operators decrypt-and-rewrite out of band.
            DB::statement('ALTER TABLE customers ALTER COLUMN email TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE customers ALTER COLUMN phone TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE customers ALTER COLUMN address TYPE VARCHAR(255)');

            DB::statement('ALTER TABLE tenants ALTER COLUMN iban TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE tenants ALTER COLUMN bank_name TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE tenants ALTER COLUMN account_holder TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE tenants ALTER COLUMN invoice_email TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE tenants ALTER COLUMN invoice_phone TYPE VARCHAR(255)');
        }
    }
};
