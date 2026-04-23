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
    ): mixed {
        // Apply jitter at store time — followers reading from cache don't
        // do this; only the writer (leader) extends the TTL. This spreads
        // the next expiry event across a small time window so we don't
        // get a synchronised thundering-herd of recomputes across the
        // whole tenant fleet.
        $effectiveTtl = $jitterSeconds > 0
            ? $ttlSeconds + random_int(0, $jitterSeconds)
            : $ttlSeconds;

        $hit = Cache::get($key);
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
            if ($second !== null) {
                return $second;
            }

            $value = $producer();
            Cache::put($key, $value, $effectiveTtl);

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
}
