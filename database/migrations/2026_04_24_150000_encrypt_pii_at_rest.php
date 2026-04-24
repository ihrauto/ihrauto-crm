<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
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

        // Re-encrypt existing rows using raw DB queries + the Crypt
        // facade. We can't use Eloquent here because the `encrypted`
        // cast on the model would attempt to DECRYPT every plaintext
        // read and throw DecryptException. We also make the rewrite
        // idempotent: if decrypt succeeds the row is already encrypted
        // (partial run resumed); if decrypt throws we treat the stored
        // value as plaintext and encrypt it now.
        $reencrypt = function (?string $stored): array {
            // Returns [ciphertext-to-store, plaintext-for-hash].
            if ($stored === null || $stored === '') {
                return [null, null];
            }
            try {
                $plain = Crypt::decryptString($stored);
                // Already encrypted. Keep the existing ciphertext so we
                // don't rotate IVs unnecessarily.
                return [$stored, $plain];
            } catch (DecryptException) {
                // Plaintext row — encrypt now.
                return [Crypt::encryptString($stored), $stored];
            }
        };

        foreach (DB::table('customers')->orderBy('id')->lazy(200) as $row) {
            [$emailCipher, $emailPlain] = $reencrypt($row->email);
            [$phoneCipher, $phonePlain] = $reencrypt($row->phone);
            [$addressCipher, ] = $reencrypt($row->address);
            DB::table('customers')->where('id', $row->id)->update([
                'email' => $emailCipher,
                'phone' => $phoneCipher,
                'address' => $addressCipher,
                'email_hash' => \App\Models\Customer::lookupEmailHash($emailPlain),
                'phone_hash' => \App\Models\Customer::lookupPhoneHash($phonePlain),
            ]);
        }

        foreach (DB::table('tenants')->orderBy('id')->lazy(100) as $row) {
            [$iban, ] = $reencrypt($row->iban);
            [$bank, ] = $reencrypt($row->bank_name);
            [$holder, ] = $reencrypt($row->account_holder);
            [$invEmail, ] = $reencrypt($row->invoice_email);
            [$invPhone, ] = $reencrypt($row->invoice_phone);
            DB::table('tenants')->where('id', $row->id)->update([
                'iban' => $iban,
                'bank_name' => $bank,
                'account_holder' => $holder,
                'invoice_email' => $invEmail,
                'invoice_phone' => $invPhone,
            ]);
        }
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
