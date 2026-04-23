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
wired up automatically.

### Render.com

Render doesn't support sidecars in the same container. Two options:

1. **Use Render's managed pooler** — Render Postgres `standard` and above
   include a built-in connection pooler. Set `DB_PORT` to the pooler port
   (documented in your Render dashboard) instead of running PgBouncer.
2. **Run PgBouncer as a separate web service** — add a `type: pserv` entry
   in `render.yaml`, mount this `pgbouncer.ini`, point app containers at it.

The second option is wired in `render.yaml` under the `pgbouncer` service
(commented out by default — un-comment after adding a Postgres password).

## Environment variables

Point the app at PgBouncer by setting:

```
DB_HOST=pgbouncer
DB_PORT=6432
# Everything else (DB_DATABASE, DB_USERNAME, DB_PASSWORD) stays the same.
```

## User list

PgBouncer needs a separate auth file mapping `username → SCRAM hash`.
Extract from Postgres with:

```sql
SELECT '"' || rolname || '" "' || rolpassword || '"'
FROM pg_authid WHERE rolname = 'ihrauto';
```

Write the result to `docker/pgbouncer/userlist.txt`. Keep this file out
of version control — it's in `.gitignore` already.

## Verifying

After deploy, check pool health:

```bash
# From a worker container
psql -h pgbouncer -p 6432 -U postgres pgbouncer -c 'SHOW POOLS;'
```

You should see `cl_active` (client connections) ≫ `sv_active` (server
connections). That ratio IS the point.
