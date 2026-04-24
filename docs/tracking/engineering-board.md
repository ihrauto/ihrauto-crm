# Engineering Board

Last updated: 2026-04-24

## Now

- None currently listed. Add active work here when implementation begins.

## Next

- `ENG-005` Team-scoped roles (Spatie `teams=true`) — M-3 from the security review. Add `team_id` to `model_has_roles` / `model_has_permissions` pivots, flush the permission cache, backfill existing rows with each user's `tenant_id`, switch call sites that pass bare role names to pass team context, and add regression tests covering multi-tenant user membership. Not exploitable today (schema enforces one tenant per user) but blocks onboarding of agency/support users that span tenants.
- `ENG-006` Hashed `remember_token` at rest — M-5 from the security review. Subclass Laravel's `SessionGuard` (or register a custom user provider) so `rememberUser` persists `hash('sha256', $plain)` and `viaRemember` compares with `hash_equals`. Requires a rollout plan for existing plain tokens: either invalidate all remember cookies on deploy (forced re-login) or dual-verify for one TTL window. Either option needs comms.

## Blocked

- `ENG-007` Secret rotation — C-2 from the security review. Waiting on operator to: revoke the exposed Resend API key at resend.com, issue a new key, and optionally rotate `APP_KEY` (invalidates all sessions + signed URLs) and Sentry DSN. Code path is ready — once new values land we update `.env` and run `php artisan config:clear`.

## Done

- `ENG-004` Security review remediation sprint (2026-04-24)
  - Owner: engineering
  - Outcome: closed the 2 Critical, 7 High, 8 Medium, and 3 Low findings surfaced by the defensive security review. Notable deltas: `current_password` required on email change, `/subscription/setup` gated by `manage settings`, forgot-password enumeration oracle closed, upload extension derived from sniffed MIME, invoice immutability trigger extended to `discount_total` / `customer_id` / `vehicle_id`, CSP + COOP + CORP added, slow-query binding scrubber, CSV formula injection neutralised, `TRUSTED_PROXIES` env knob, CI `npm audit` gating, hardened password policy with HIBP in prod, `robots.txt` tightened, `SentryScrubber` masks PII before transmission, `invite_token` hidden on User serialisation, `two_factor_required` removed from fillable (unenforced flag). See CHANGELOG `[Unreleased] - 2026-04-24`.
  - Verification: `./vendor/bin/phpunit` (427 tests, 1093 assertions, all green).

- `ENG-003` Production launch hardening remediation
  - Owner: engineering
  - Outcome: locked tenant role assignment to launch-safe roles, removed tenant permission editing from the route surface, hardened mechanic invite/login lifecycle, made tenant/token revocation immediate via shared cache invalidation, fixed payment idempotency and check-in photo rollback behavior, aligned work-order status validation, enforced tenant module toggles at runtime, and replaced unsafe production startup seeding with a minimal bootstrap path.
  - Verification: `php artisan test`, `php artisan route:cache`, `npm run build`, `npx eslint resources/js/**/*.js`, `./vendor/bin/pint --test`

- `ENG-002` Production beta readiness finish pass
  - Owner: engineering
  - Outcome: replaced the placeholder tenant pricing path with a real manual-billing page, added super-admin billing controls, blocked destructive customer deletion when dependencies exist, wired cloud-ready storage/backup/runtime config, and added missing feature coverage for Check-in, Tire Hotel, and Management/Admin flows.
  - Verification: `php artisan test`
- `ENG-000` Documentation system bootstrap
  - Owner: engineering
  - Outcome: added architecture, workflow, code-map, function-index, changelog, decision-log, and task-tracking documentation structure to the repository.
  - Verification: docs added under `docs/`, README linked to the new structure, changelog updated.
- `ENG-001` Public pricing and plan-selection refresh
  - Owner: engineering
  - Outcome: redesigned the public pricing page, added package-specific signup CTAs for every plan, surfaced the selected plan in registration, and aligned the page to the app's established indigo palette.
  - Verification: `php artisan test`

## Parking Lot

- Add future ideas here only if they are acknowledged but intentionally unscheduled.
