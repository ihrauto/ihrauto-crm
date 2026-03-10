# Changelog Policy

## Purpose

`CHANGELOG.md` records notable product and platform changes in a form that another engineer, operator, or stakeholder can read quickly.

## Format

The project follows a simple `Keep a Changelog` style:

- `Unreleased`
- dated release headings when a release is cut
- grouped sections such as `Security`, `Multi-tenancy`, `Billing`, `Platform`, `Documentation`, and `Operations`

## What Belongs In The Changelog

Include:

- security changes
- production behavior changes
- API contract changes
- changes to canonical business rules
- new commands or operational behavior
- schema or migration changes with operator impact
- documentation or process additions that future engineers must know about

Do not include:

- file rename noise
- refactors with no behavior impact
- style-only changes
- temporary debugging work

## Writing Rules

- Describe the outcome, not the implementation detail list.
- Write from the perspective of “what changed in the system”.
- Keep each bullet self-contained.
- If a change is risky or deprecated, say so explicitly.

## Release Process

1. Work lands under `## [Unreleased]`.
2. When releasing, copy the unreleased items into a dated version section.
3. Clear `Unreleased` and start the next cycle.
4. If a release contains migrations, auth changes, or operational steps, mention them.

## Example Entry

```md
### Security
- Required bearer tenant API tokens for all public API routes and deprecated header-based tenant selection.
```

## Ownership

The engineer shipping the change updates the changelog. Reviewers should treat missing changelog updates as an incomplete change when the behavior is externally meaningful.
