# IHRAUTO CRM - Production Runbook

**Last updated:** April 2, 2026
**Stack:** Laravel 12, PostgreSQL, Redis, Docker on Render (Frankfurt)

---

## 1. Deployment

### Standard Deployment (main branch)
1. Push to `main` branch
2. Render auto-detects the push and starts building
3. Docker build runs: composer install, npm build, migration validation
4. Container starts: waits for PostgreSQL, runs `migrate --force`, caches config/routes/views
5. Supervisord starts Apache + queue worker + scheduler
6. Health check at `/up` confirms service is live

### Staging Deployment
1. Push to `staging` branch
2. Deploys to `ihrauto-crm-staging` service with separate DB/Redis
3. `APP_DEBUG=true`, `AUTO_LOGIN_ENABLED=true` for testing

### Pre-deployment Checklist
- [ ] All tests pass locally: `php artisan test`
- [ ] Blade views compile: `php artisan view:cache`
- [ ] Routes cache: `php artisan route:cache`
- [ ] No `console.log()` in Blade files
- [ ] No hardcoded secrets in code
- [ ] Migration tested against staging DB first

---

## 2. Rollback

### Rollback via Render Dashboard
1. Go to Render Dashboard > ihrauto-crm service
2. Click "Manual Deploy" > select the previous commit SHA
3. Or click "Deploys" tab > find last working deploy > "Redeploy"

### Database Migration Rollback
```bash
# Rollback the last migration batch
php artisan migrate:rollback --step=1

# Rollback N migration batches
php artisan migrate:rollback --step=N

# Check migration status
php artisan migrate:status
```

### Emergency Rollback (database restore)
1. Render provides automatic daily backups for PostgreSQL
2. Go to Render Dashboard > ihrauto-db > Backups
3. Select a point-in-time to restore
4. Render creates a new database instance from the backup
5. Update `DB_HOST` environment variable to point to the restored instance

---

## 3. Database Operations

### Check Migration Status
```bash
php artisan migrate:status
```

### Run Pending Migrations
```bash
php artisan migrate --force
```

### Backup Database
```bash
# Manual backup via app
php artisan backup:run --only-db

# Using pg_dump directly (from Render shell or local with connection string)
pg_dump -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore from Backup
```bash
# From pg_dump file
psql -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE < backup_file.sql
```

---

## 4. Tenant Management

### Create Super Admin (first-time setup)
```bash
php artisan ops:bootstrap-super-admin
```
Uses `SUPERADMIN_EMAIL`, `SUPERADMIN_NAME`, `SUPERADMIN_PASSWORD` from environment.

### Tenant Operations
```bash
# View tenant status
php artisan tinker --execute="App\Models\Tenant::select('id','name','plan','is_active','is_trial')->get()"

# Activate a tenant
php artisan tinker --execute="App\Models\Tenant::find(ID)->update(['is_active' => true])"

# Suspend a tenant
php artisan tinker --execute="App\Models\Tenant::find(ID)->suspend()"

# Rotate API token
php artisan tenant:rotate-api-token {tenant_id}

# Purge tenant data (IRREVERSIBLE)
php artisan tenant:purge {tenant_id}
```

### Upgrade Tenant Plan
Via super-admin dashboard at `/admin/tenants/{id}`, or:
```bash
php artisan tinker --execute="App\Models\Tenant::find(ID)->upgradePlan('standard', now()->addYear())"
```

---

## 5. Cache Management

### Clear All Caches
```bash
php artisan optimize:clear
```

### Clear Specific Caches
```bash
php artisan cache:clear      # Application cache (Redis)
php artisan config:clear     # Config cache
php artisan route:clear      # Route cache
php artisan view:clear       # View cache
```

### Rebuild Caches
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Tenant-specific Cache Keys
- `dashboard_stats_{tenant_id}` — 5 min TTL
- `finance_overview_{tenant_id}` — 5 min TTL
- `tenant_{tenant_id}_user_count` — 60s TTL
- `tenant_{tenant_id}_customer_count` — 60s TTL
- `tenant_{tenant_id}_vehicle_count` — 60s TTL
- `tenant_{tenant_id}_wo_month_YYYY_MM` — 60s TTL
- `api_token_{hash}` — 60s TTL

---

## 6. Queue & Scheduler

### Check Queue Status
Queue and scheduler are managed by Supervisord:
```bash
# View Supervisord status
supervisorctl status

# Restart queue worker
supervisorctl restart queue-worker

# Restart scheduler
supervisorctl restart scheduler
```

### Failed Jobs
```bash
php artisan queue:failed        # List failed jobs
php artisan queue:retry all     # Retry all failed jobs
php artisan queue:flush         # Delete all failed jobs
```

---

## 7. Monitoring

### Health Check
- Endpoint: `GET /up`
- Returns 200 if app is running

### Sentry Error Monitoring
- DSN configured via `SENTRY_LARAVEL_DSN` env var
- Environment tagged via `SENTRY_ENVIRONMENT` (production/staging)
- Trace sample rate: 20% (`SENTRY_TRACES_SAMPLE_RATE=0.2`)

### Application Logs
```bash
# Render: logs stream to stderr (LOG_CHANNEL=stderr)
# View in Render Dashboard > Service > Logs tab

# Local: check storage/logs/laravel.log
tail -f storage/logs/laravel.log
```

---

## 8. Emergency Procedures

### App Down / 500 Errors
1. Check Render Dashboard > Service status
2. Check logs for error messages
3. Verify database is accessible: health check endpoint
4. If migration issue: rollback to previous deployment
5. If data issue: restore from database backup

### Data Leak Suspected
1. Immediately verify tenant isolation: check if raw queries bypass `TenantScope`
2. Check audit_logs for unusual access patterns
3. Rotate all API tokens: `php artisan tenant:rotate-api-token`
4. Contact affected tenants

### High Load / Slow Performance
1. Check Redis: is it connected? Memory usage?
2. Check PostgreSQL: active connections, slow queries
3. Clear and rebuild caches: `php artisan optimize:clear && php artisan optimize`
4. Check if dashboard cache is working (should reduce query count)

---

## 9. Environment Variables Reference

### Required (must be set in Render Dashboard)
| Variable | Purpose |
|----------|---------|
| `APP_KEY` | Auto-generated encryption key |
| `APP_URL` | Full URL of the application |
| `SUPERADMIN_PASSWORD` | Initial super-admin password |
| `RESEND_API_KEY` | Email delivery service key |

### Database (auto-linked from Render)
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### Optional
| Variable | Default | Purpose |
|----------|---------|---------|
| `CRM_TAX_RATE` | 8.1 | Swiss VAT rate (application-wide) |
| `CRM_SUPPORT_EMAIL` | info@ihrauto.ch | Support email |
| `AUTO_LOGIN_ENABLED` | false | Dev-only: auto-login as first tenant |
| `SENTRY_LARAVEL_DSN` | - | Error monitoring |

### Storage (S3-compatible)
`PUBLIC_FILESYSTEM_*` — for file uploads
`BACKUP_FILESYSTEM_*` — for database backups

---

## 10. Backup Strategy

| Type | Frequency | Retention |
|------|-----------|-----------|
| Database (Render automatic) | Daily | Per Render plan |
| Database (app-level backup) | Daily via scheduler | 7 daily, 4 weekly, 12 monthly |
| File uploads | Stored in S3 | Indefinite |

### Verify Backups
```bash
# Check last backup timestamp (via dashboard system status panel)
# Or directly:
php artisan backup:list
```

---

## 11. Incident Response

### 11.1 Service-level objectives

| Metric | Target | Notes |
|--------|--------|-------|
| **RPO** (data loss tolerated)   | **1 hour**   | Daily DB snapshot + hourly app-level backup → worst case one extra hour of transactions replayed from `audit_logs` |
| **RTO** (time to restore)       | **≤ 30 min** | Render dashboard one-click redeploy + migrate-forward scripts |
| **Backup restore drill cadence**| **Quarterly** | See §11.3 |
| **Error-budget alert threshold**| 1 × 5xx / min for 5 min | Sentry issue → paged oncall |

### 11.2 Incident procedure (service degraded / down)

1. **Triage (≤ 2 min):** confirm the issue in two signals
   - Render dashboard service status
   - Sentry issue feed (`https://sentry.io/organizations/ihrauto`)
   - `/up` health endpoint from an external host
2. **Contain (≤ 5 min):** decide rollback vs forward-fix
   - **Rollback:** Render → Deploys → previous working commit → Redeploy
   - **Scale up:** if DB CPU pegged, bump plan temporarily (Render → Database → Change Plan)
   - **Disable background work:** Render env `APP_MAINTENANCE=true` then restart (see §11.4)
3. **Investigate:**
   - `php artisan pail --tail=1000` from a shell on the container
   - Sentry breadcrumbs for the affected request
   - `SELECT * FROM audit_logs WHERE created_at > now() - interval '1 hour' ORDER BY created_at DESC LIMIT 50;`
4. **Communicate:** post in `#ops` with status, scope (tenant count affected), and ETA.
5. **Postmortem (within 48 h):** fill the template in `docs/templates/postmortem.md`.

### 11.3 Backup restore drill

Run on the first business day of each quarter.

```bash
# 1. Provision a throwaway staging DB on Render or locally
createdb ihrauto_restore_drill

# 2. Pull the latest backup
php artisan backup:list  # confirm what's there
aws s3 cp s3://ihrauto-backups/latest.sql.gz ./restore.sql.gz
gunzip restore.sql.gz

# 3. Restore
psql ihrauto_restore_drill < restore.sql

# 4. Validate (sanity checks)
psql ihrauto_restore_drill -c "SELECT COUNT(*) FROM customers;"
psql ihrauto_restore_drill -c "SELECT COUNT(*) FROM invoices WHERE status = 'issued';"

# 5. Record the drill result in docs/tracking/backup-drills.md
```

### 11.4 Emergency switches

| Scenario | Action |
|----------|--------|
| DB overloaded | Put Render plan on `standard-1gb`, then investigate slow queries via `pg_stat_statements` |
| Rogue tenant API client | Revoke token: `php artisan tinker` → `App\Models\TenantApiToken::find($id)->revoke()` |
| Suspected credential leak | `php artisan cache:clear`, rotate `APP_KEY` (forces session invalidation), rotate `SUPERADMIN_PASSWORD` |
| Data-corruption suspected | Put app in maintenance mode (`php artisan down`), snapshot DB, restore from pre-incident backup |
