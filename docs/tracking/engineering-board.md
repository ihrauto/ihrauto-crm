# Engineering Board

Last updated: 2026-03-12

## Now

- None currently listed. Add active work here when implementation begins.

## Next

- None currently listed. Move refined work here before it starts.

## Blocked

- None currently listed. Add a blocker owner and unblock condition when needed.

## Done

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
