# PgBouncer sidecar

Connection pooler that sits in front of PostgreSQL.

## Why

At 200 tenants, Apache mpm_prefork workers × N web containers easily
exceed Postgres' default 100-connection cap. PgBouncer multiplexes many
"logical" client connections across a small real backend pool.

**Before PgBouncer:** each app worker holds a PG connection for the
duration of the request. Burst traffic exhausts `max_connections`; new
requests get `FATAL: too many connections for role`.

**After PgBouncer:** the app opens connections to pgbouncer on port
6432 (practically unlimited). PgBouncer keeps a small real pool (25
connections by default) to Postgres.

## Deploy

### Docker Compose (self-hosted)

See `docker-compose.yml` at the repo root — the `pgbouncer` service is
wired up automatically. The `edoburu/pgbouncer` image auto-generates
`/etc/pgbouncer/userlist.txt` from the `DB_USER` + `DB_PASSWORD`
environment variables at container start, so no manual user-list
extraction is needed (bug review OPS-02).

All tuning (pool mode, pool size, timeouts) is passed via env vars in
the compose file. The `pgbouncer.ini` in this directory is reference
documentation — a single-source description of every setting, mirrored
in compose env vars.

### Render.com

Render doesn't support sidecars in the same container. Two options:

1. **Use Render's managed pooler** (recommended) — Render Postgres
   `standard` and above include a built-in connection pooler. Set
   `DB_PORT` to the pooler port (documented in your Render dashboard)
   instead of running PgBouncer.
2. **Run PgBouncer as a separate web service** — add a `type: pserv`
   entry in `render.yaml`, set the same env vars as in compose, point
   app containers at it.

## Environment variables

Point the app at PgBouncer by setting:

```
DB_HOST=pgbouncer
DB_PORT=6432
# Everything else (DB_DATABASE, DB_USERNAME, DB_PASSWORD) stays the same.
```

## User list

The compose setup generates `userlist.txt` automatically. If you need
to override (e.g., for a custom pgbouncer deployment), extract SCRAM
hashes from Postgres with:

```sql
SELECT '"' || rolname || '" "' || rolpassword || '"'
FROM pg_authid WHERE rolname = 'ihrauto';
```

Never commit the resulting file to git (it contains credential hashes).

## Verifying

After deploy, check pool health:

```bash
# From a worker container
psql -h pgbouncer -p 6432 -U postgres pgbouncer -c 'SHOW POOLS;'
```

You should see `cl_active` (client connections) ≫ `sv_active` (server
connections). That ratio IS the point.

## Troubleshooting

### `ERROR: SASL authentication failed`
PG user's password is stored as md5 but pgbouncer is configured for
scram-sha-256 (or vice versa). Fix: `ALTER USER ihrauto PASSWORD 'same';`
while PG's `password_encryption` is set to `scram-sha-256` (the PG 16
default), then restart pgbouncer.

### `FATAL: no such user`
`userlist.txt` was not generated. For the edoburu image, check that
`DB_USER` and `DB_PASSWORD` env vars are set on the pgbouncer container
and that the container was started cleanly (not restored from a stale
volume).

### `server conn crashed?`
Typically means PG is down or restarting. Check the postgres
container's health; pgbouncer recovers automatically once PG is back.
