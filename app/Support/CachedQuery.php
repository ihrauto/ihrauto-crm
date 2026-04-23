<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * D-14: cache stampede protection.
 *
 * `Cache::remember()` has a well-known thundering-herd problem: when an
 * expensive key expires, every concurrent request recomputes it. For
 * dashboard stats and finance aggregations this can cascade into dozens
 * of duplicate heavy queries.
 *
 * This helper serializes the recompute path with a short-lived cache lock
 * so only the first caller runs the expensive closure; everyone else
 * either waits for the lock or falls through to one more cache read.
 * Behaviour is identical to `Cache::remember()` in the happy path.
 *
 * Scalability C-5: default TTL includes a random jitter so 200 tenants'
 * cache entries don't all expire at the same wall-clock second.
 */
class CachedQuery
{
    /**
     * Return the cached value, recomputing under a lock if missing.
     *
     * @param  positive-int  $ttlSeconds  how long the result stays in cache
     * @param  positive-int  $lockSeconds  max time the lock is held
     * @param  positive-int  $waitSeconds  max time a follower waits for the leader
     * @param  int  $jitterSeconds  random TTL extension (0 = deterministic)
     */
    public static function remember(
        string $key,
        int $ttlSeconds,
        Closure $producer,
        int $lockSeconds = 10,
        int $waitSeconds = 5,
        int $jitterSeconds = 30,
        int $nullTtlSeconds = 60,
    ): mixed {
        // Apply jitter at store time — followers reading from cache don't
        // do this; only the writer (leader) extends the TTL. This spreads
        // the next expiry event across a small time window so we don't
        // get a synchronised thundering-herd of recomputes across the
        // whole tenant fleet.
        $effectiveTtl = $jitterSeconds > 0
            ? $ttlSeconds + random_int(0, $jitterSeconds)
            : $ttlSeconds;

        /*
         * Bug review DATA-07: cache null results under a short TTL so
         * lookups for non-existent keys don't re-hit the DB on every
         * request. This is the classic tenant-middleware case — an
         * attacker hitting evil.example.com with no matching tenant
         * forces a full `SELECT * FROM tenants WHERE domain = ?` every
         * time because `Cache::get` returning null treats the entry as
         * "not cached". We distinguish "genuinely absent from cache"
         * from "cached null" by using a sentinel value that's never a
         * valid producer return.
         *
         * The nullTtlSeconds default (60s) is deliberately short — if
         * the tenant IS later created (or the lookup key becomes
         * meaningful), we don't want to keep returning the stale null
         * for the full ttlSeconds. 60s is long enough to absorb a brute
         * force, short enough to recover quickly.
         */
        $sentinel = self::nullSentinel();

        $hit = Cache::get($key);
        if ($hit === $sentinel) {
            return null;
        }
        if ($hit !== null) {
            return $hit;
        }

        $lock = Cache::lock("lock:$key", $lockSeconds);

        try {
            // block() waits up to $waitSeconds for the lock. If the leader
            // finishes and writes the cache entry within that window,
            // followers read it on the second Cache::get() below without
            // re-running the expensive closure.
            $lock->block($waitSeconds);

            $second = Cache::get($key);
            if ($second === $sentinel) {
                return null;
            }
            if ($second !== null) {
                return $second;
            }

            $value = $producer();
            if ($value === null) {
                Cache::put($key, $sentinel, $nullTtlSeconds);
            } else {
                Cache::put($key, $value, $effectiveTtl);
            }

            return $value;
        } catch (LockTimeoutException) {
            // Couldn't acquire the lock within the wait window — return a
            // fresh (uncached) result rather than block the request further.
            // Future requests will hit the cache once the leader finishes.
            return $producer();
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Sentinel object used to distinguish "producer returned null" from
     * "cache miss". Cache drivers serialize arrays faithfully, so an
     * array with a fixed marker is stable across Redis / file / database
     * stores.
     */
    private static function nullSentinel(): array
    {
        return ['__ihrauto_cached_null__' => true];
    }
}
