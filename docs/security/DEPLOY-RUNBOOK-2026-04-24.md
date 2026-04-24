# Deploy runbook — 2026-04-24 security sprint

This is the step-by-step to ship the four committed commits + the
uncommitted H-6b fix once you're ready. **Nothing here runs
automatically; it's a copy-paste checklist.**

---

## 0. What's on your disk right now

Committed on `main` (ahead of `origin/main` by 4 commits):

```
abf01b1  docs(sec): record 2026-04-24 security review remediation
e582172  sec(review-2026-04-24): close low findings + roll policy across test fixtures
6f62f89  sec(review-2026-04-24): close medium findings + password-rule foundation
8f9ce4c  sec(review-2026-04-24): close critical + high findings
```

Uncommitted (added after the audit flagged H-6b):

```
?? app/Support/SafeImageUpload.php
 M app/Http/Controllers/WorkOrderPhotoController.php   (refactor to use helper)
 M app/Services/CheckinService.php                     (apply H-6b)
 M tests/Feature/CheckinTest.php                       (new H-6b test)
?? docs/security/PRE-DEPLOYMENT-AUDIT-2026-04-24.md
?? docs/security/DEPLOY-RUNBOOK-2026-04-24.md          (this file)
```

Test state: **433 tests / 1130 assertions / 361 Pint-clean files**, all green.

---

## 1. Before you push — operator actions

### 1a. Revoke the old GitHub PAT

Open https://github.com/settings/tokens, find the token that starts
`ghp_mdht…`, and click **Revoke**. Any future read/write that used this
token now fails. (The remote URL in `.git/config` has already been
sanitised; this step kills the token at the source.)

### 1b. Re-authenticate git

Pick one:

```bash
# Option A — GitHub CLI, stores in macOS Keychain (recommended)
gh auth login -h github.com -p https

# Option B — SSH keys
ssh-keygen -t ed25519 -C "kushtrim.m.arifi@gmail.com" -f ~/.ssh/ihrauto_ed25519
# Add the .pub to https://github.com/settings/keys
cd "/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM"
git remote set-url origin git@github.com:ihrauto/ihrauto-crm.git
```

Verify auth:

```bash
cd "/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM"
git ls-remote origin HEAD
# Should print a SHA and exit 0 without prompting.
```

### 1c. Populate Render `sync: false` secrets

In the Render dashboard for the `ihrauto-crm` web service, Settings →
Environment, set the following. Anything already populated from an
earlier deploy can stay; **the starred ones must be rotated this
deploy.**

- `APP_URL` — `https://your-production-host`
- `CORS_ALLOWED_ORIGINS` — same as `APP_URL`, or a comma-separated list
- ⭐ `RESEND_API_KEY` — the **new** key from resend.com (revoke the old
  one `re_2AsKa…` there)
- `SENTRY_LARAVEL_DSN`, `SENTRY_RELEASE` — values from Sentry dashboard
- ⭐ `SUPERADMIN_PASSWORD` — strong random value; the seeder will create
  the account on first boot if it doesn't exist and aborts if blank
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` —
  from Google Cloud OAuth consent screen
- `BACKUP_FILESYSTEM_*` (KEY, SECRET, REGION, BUCKET, ENDPOINT, URL) —
  S3-compatible credentials for nightly dumps
- `PUBLIC_FILESYSTEM_*` (KEY, SECRET, REGION, BUCKET, ENDPOINT, URL) —
  S3-compatible credentials for tenant uploads
- `ASSET_URL` — optional CDN URL, or leave blank
- `CRM_SUPPORT_PHONE` — displayed in the UI

Recommended additions not in `render.yaml` today:

- `BACKUP_ARCHIVE_PASSWORD` — enables at-rest encryption on the zipped
  backups (see audit §11).
- `TRUSTED_PROXIES` — comma-separated Render edge CIDRs if you want to
  tighten beyond `*` (see audit §M-9). Skippable — defaults to `*`.

### 1d. (Optional, strongly recommended) — decide on `APP_KEY`

Rotating `APP_KEY` invalidates every active session and every signed
URL (pending email-verification links, pending invoice-PDF links). If
you believe the old `.env` on this workstation was ever seen by anyone
else, rotate now:

```bash
# Generates but doesn't print. Copy the output into Render's env.
php artisan key:generate --show
```

Paste into Render env `APP_KEY`. Everyone must re-login after deploy.

---

## 2. Stage the uncommitted H-6b fix (optional but recommended)

If you want H-6b to ship with this deploy, make it commit 5:

```bash
cd "/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM"

git add \
  app/Support/SafeImageUpload.php \
  app/Http/Controllers/WorkOrderPhotoController.php \
  app/Services/CheckinService.php \
  tests/Feature/CheckinTest.php

git diff --cached --stat    # sanity-check
```

Draft commit message (HEREDOC form, copy verbatim):

```bash
git commit -m "$(cat <<'EOF'
sec(review-2026-04-24): close H-6b in CheckinService + extract shared helper

Audit docs/security/PRE-DEPLOYMENT-AUDIT-2026-04-24.md §9 flagged that
CheckinService::uploadPhotos was still building stored filenames from
$photo->getClientOriginalExtension(), the same polyglot/double-extension
vector H-6 closed for WorkOrderPhotoController. This commit:

- extracts the IMAGETYPE_* -> safe-extension mapper into
  App\Support\SafeImageUpload::extensionFor() so both upload sites
  share the exact same logic;
- refactors WorkOrderPhotoController::store to call the helper
  (no behaviour change);
- applies the helper to CheckinService::uploadPhotos, which
  additionally now skips images whose type is outside the allowlist
  rather than storing them with a client-chosen extension;
- adds CheckinTest::checkin_upload_extension_is_derived_from_image_type_not_client_name
  which posts a JPEG named "shell.php.jpg" through the /checkin route
  and asserts the saved filename + path both end in .jpg and never
  contain ".php".

Production impact: on S3-backed disks the polyglot was not directly
exploitable (S3 serves arbitrary content-types, not as PHP), but on
any self-hosted FILESYSTEM_DISK=local it would have been one Apache
handler-config mishap away from RCE. Closes the latent risk.

Test suite: 433 tests, 1130 assertions, all green.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Then update CHANGELOG / engineering-board / decision-log in the next
commit if you want to keep the project's working-agreement rhythm, or
skip and leave them as TODO for a later polish PR.

---

## 3. Tag the release (optional)

```bash
cd "/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM"
git tag -a v1.4.0-sec-review-2026-04-24 -m "Security review 2026-04-24 remediation sprint

Wave 1 (critical): C-1 PAT sanitized; C-2 operator rotation pending.
Wave 2 (high):     H-1..H-7 + H-6b.
Wave 3 (medium):   M-1, M-2 (verified), M-6..M-10.
Wave 4 (low):      L-1, L-6, L-9.
Deferred:          M-3 (Spatie teams), M-5 (hashed remember tokens).

Verification: phpunit 433/433 green, pint clean.
Audit:        docs/security/PRE-DEPLOYMENT-AUDIT-2026-04-24.md
"
```

Push the tag AFTER the push to main, not before:

```bash
git push origin main
git push origin v1.4.0-sec-review-2026-04-24
```

---

## 4. The push itself

```bash
cd "/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM"

# Sanity: should list the sprint commits (+ 5 if you landed H-6b)
git log origin/main..HEAD --oneline

# Sanity: working tree clean
git status --porcelain

# Push. Auto-deploys Render.
git push origin main
```

---

## 5. Watch CI

```bash
# from the same working tree
gh run watch --exit-status
```

Expected jobs:

- `tests (PHP 8.4)` — phpunit
- `lint (Pint)` — style
- `security` — `composer audit` + `npm audit --audit-level=moderate`
  (the `|| true` was removed in M-10, so a new JS advisory now fails
  the build)
- `build` — `npm run build` then `test -d public/build`

If `security` fails because of a moderate+ npm advisory, you have two
options: address the advisory (preferred) or temporarily lower the
audit level in `.github/workflows/ci.yml` with a tracked follow-up PR.

---

## 6. Watch the Render deploy

- Dashboard → `ihrauto-crm` service. You should see a new build kicked
  off the moment main updates.
- Each of the 3 web instances will roll one at a time. Healthcheck is
  `GET /up`.
- First instance to come up runs `php artisan migrate --force`. It
  will apply `2026_04_24_100000_extend_invoice_immutability_trigger`
  (Postgres-only, idempotent).

If migrations stall (hung lock), check the Postgres dashboard →
`pg_stat_activity` for blocked queries. The new migration is purely
`CREATE OR REPLACE FUNCTION`, so it can't block on table locks.

---

## 7. Post-deploy smoke checklist

Run these in order. A failure at any step is a candidate for the
Render-dashboard rollback button.

```bash
export APP_URL=https://your-production-host

# 7a. Liveness + readiness
curl -sSfI "$APP_URL/up"     | head -1   # expect 200
curl -sSfI "$APP_URL/health" | head -1   # expect 200 (DB + cache verified)

# 7b. Security headers on the public homepage
curl -sI "$APP_URL/" | \
  grep -i -E 'content-security-policy|cross-origin|strict-transport-security|x-frame-options|referrer-policy|permissions-policy'
# Expect every one of those six to be present.

# 7c. robots.txt
curl -s "$APP_URL/robots.txt" | head -10
# Expect "Disallow: /admin", "Disallow: /login", etc.

# 7d. Login flow
# Go to $APP_URL/login, log in as the super-admin (credentials from
# Render env), visit /admin/tenants, visit /dashboard.

# 7e. Forgot-password flow (H-4)
# Submit both a real email and a random one at /forgot-password.
# Both should return the same success banner — no distinction.
```

### In the browser

- [ ] Create a customer → update name → save. Confirm no console errors.
- [ ] Start a check-in → upload a photo → finish. Verify the photo
      shows in S3 with a UUID.jpg filename (not .jpeg, not .php.jpg).
- [ ] Open an existing issued invoice → download PDF → confirm the
      link includes `token=` and `signature=` query params.
- [ ] Open an email invoice via the public `/i/{token}/{invoice}` URL —
      confirm it renders outside auth.
- [ ] Visit /profile and attempt to change email without password —
      confirm the "current password" input appears and blocks submit.
- [ ] Run `/management/export` as admin → confirm the downloaded CSV
      contains leading `'` in front of any cell starting with `=`, `+`,
      `-`, `@`.

### In Sentry (first 30 min)

- [ ] No spike in new error groups.
- [ ] No `Content-Security-Policy-Violation` storm. A trickle is
      expected from browser extensions.
- [ ] Any captured event's `request.data` / `headers` must not contain
      raw `password`, `iban`, or `token` values — it should show
      `"[filtered]"` (SentryScrubber, L-9).

### In the DB (psql or Render's data explorer)

- [ ] `SELECT prosrc FROM pg_proc WHERE proname = 'prevent_issued_invoice_modification';`
      — the function body must reference `discount_total`, `customer_id`,
      and `vehicle_id` along with the original fields.
- [ ] `SELECT application_name, count(*) FROM pg_stat_activity GROUP BY 1;`
      — expect `ihrauto-crm-web` and `ihrauto-crm-backup` to show up.

### In the backup runner

- [ ] Wait for 02:00 UTC (or trigger manually: Render backup-runner
      Shell → `php artisan backup:run --only-db`). Confirm it uploads
      to the S3 bucket.
- [ ] At 04:15 UTC `backup:verify` runs. If it logs a Sentry warning,
      chase the upload-side creds/config.

---

## 8. Rollback triggers

Roll back via the Render dashboard (one click) if any of these hit
within the first hour:

- `/health` returning 5xx across 2+ instances.
- `pg_stat_activity` shows long-running queries not attributable to the
  backup runner.
- Sentry error rate > 10× baseline.
- Users report being logged out en masse (only expected if you
  rotated `APP_KEY` — if unexpected, roll back).
- CSP breakage on a page users need (e.g. work-order board) that we
  didn't catch in testing. Rollback + schedule a CSP tweak.

---

## 9. Post-deploy cleanups (not urgent)

- [ ] Delete `/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM copy/`
      (it contains a pre-rotation `.env`).
- [ ] Move `/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/aws-laravel-key.pem`
      into `~/.ssh/` or a secrets vault.
- [ ] Fix the typo in `/Users/kushtrim/package.json` line 19 (missing
      comma between `"vite"` and `"type"`). Outside this project, but
      blocks local `npm run build` from this directory. 1-char fix.
- [ ] Drop `AUTO_LOGIN_ENABLED="true"` from the staging block in
      `render.yaml` (it is a no-op today — `AutoLoginGuard::resolve`
      gates on `APP_ENV=local`, not `staging` — but it's misleading
      env-var drift).
- [ ] Optional: flip `APP_DEBUG="false"` + `LOG_LEVEL=info` on the
      staging block too, unless staging is strictly internal.

---

## 10. When to call it done

- All smoke-check boxes green.
- 24 h with no new Sentry error groups that can be traced to this deploy.
- Tomorrow's `backup:verify` passes (04:15 UTC).
- Monday morning's `audit-logs:archive` completes (weekly, next Sunday
  04:00 UTC).

Update `docs/tracking/engineering-board.md`:

- Move `ENG-004 Security review remediation sprint` from "Done (just
  added)" to "Done (shipped on YYYY-MM-DD)".
- If C-2 secrets rotated — mark `ENG-007` done.
