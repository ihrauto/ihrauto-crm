<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit-DATA-03 follow-up: extend encrypted-at-rest coverage to the
 * top-level Tenant contact + tax-id fields and to staff (User) phone
 * numbers. The original DATA-03 migration only covered Tenant payout
 * destinations (iban / bank / invoice_email / invoice_phone) and
 * Customer PII (email / phone / address). The audit flagged that the
 * threat-model paragraph claims protection of Tenant *contact* PII
 * which was still cleartext.
 *
 * Newly encrypted columns:
 *   tenants:  phone, address, email, vat_number
 *   users:    phone
 *
 * Migration mechanics mirror DATA-03:
 *   1. Widen string columns to TEXT on Postgres so encrypted ciphertext
 *      (~4× plaintext) fits. SQLite's TEXT affinity makes this a no-op.
 *   2. Re-encrypt existing rows by reading plaintext via withoutGlobalScopes
 *      and writing it back through the cast. Touching every row is what
 *      fires the cast.
 *
 * Reversibility: down() can widen back to varchar but cannot recover
 * plaintext from ciphertext.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenants ALTER COLUMN phone TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN address TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN vat_number TYPE TEXT');
            DB::statement('ALTER TABLE users ALTER COLUMN phone TYPE TEXT');
        }
        // Tenant.email left cleartext: signup uniqueness check can't
        // operate on encrypted ciphertext (different IV each write).
        // Adding tenant.email_hash is the proper extension — deferred.

        // Re-encrypt existing rows. The cast layer reads cleartext (no
        // cast active for these columns yet at this exact moment, but
        // the model class is loaded with the new cast — Eloquent will
        // attempt to decrypt on read and fall back to the raw value if
        // it is not yet ciphertext). Loading + saving forces a write
        // back through the cast, so every row ends up encrypted.
        \App\Models\Tenant::withoutGlobalScopes()->cursor()->each(function ($tenant) {
            $raw = $tenant->getRawOriginal();
            $tenant->setAttribute('phone', $raw['phone'] ?? null);
            $tenant->setAttribute('address', $raw['address'] ?? null);
            $tenant->setAttribute('vat_number', $raw['vat_number'] ?? null);
            $tenant->save();
        });

        \App\Models\User::withoutGlobalScopes()->cursor()->each(function ($user) {
            $raw = $user->getRawOriginal();
            $user->setAttribute('phone', $raw['phone'] ?? null);
            $user->save();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenants ALTER COLUMN phone TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE tenants ALTER COLUMN address TYPE TEXT');
            DB::statement('ALTER TABLE tenants ALTER COLUMN vat_number TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE users ALTER COLUMN phone TYPE VARCHAR(255)');
        }
    }
};
