<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C2 (sprint 2026-04-24): drop every existing plaintext `remember_token`
 * now that HashedEloquentUserProvider stores SHA-256 digests instead.
 *
 * Context:
 *   Before this migration, `users.remember_token` held the 60-char
 *   random cookie value verbatim. A DB dump leaked live credentials.
 *   The new provider hashes on write + `hash_equals` on read, but
 *   pre-existing rows still contain plaintext.
 *
 * Strategy (fail closed, no dual-verify):
 *   Zero the column. Any remember-me cookie currently in a browser
 *   stops matching, and the user simply re-logs — their active
 *   session cookie is unaffected because that lives in Redis, not
 *   the column we're nulling.
 *
 * Why not a dual-verify window:
 *   We would have to keep the unhashed compare path around for
 *   SESSION_LIFETIME + remember_lifetime. The code complexity is
 *   not worth the one login it saves per remember-me user.
 *
 * Down:
 *   Rolling back the migration does not un-null the column. That's
 *   fine — if the deploy is reverted, users re-log again. The only
 *   one-way aspect is "the old plaintext values are gone", which is
 *   precisely the security goal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['remember_token' => null]);
    }

    public function down(): void
    {
        // Nothing to restore: the original plaintext values are
        // intentionally unrecoverable.
    }
};
