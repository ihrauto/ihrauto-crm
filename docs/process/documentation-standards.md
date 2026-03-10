# Documentation Standards

## Purpose

These rules keep the repo useful to future engineers instead of relying on tribal knowledge.

## Documentation Principles

- Write docs against the code that exists now, not the system people intend to build later.
- Prefer updating an existing page over creating overlapping pages.
- Keep architecture docs stable and workflow docs concrete.
- Use the changelog for externally meaningful change history, not for design explanation.
- Use the engineering board for work state, not for long-form reasoning.
- Use the decision log when a rule changes and future engineers will ask why.

## When Docs Must Change

Update docs when a change affects any of the following:

- Public routes or API contracts
- Tenant resolution or access control
- Billing and invoice behavior
- Provisioning or tenant lifecycle
- Operational commands or runbooks
- Module workflows
- Required environment variables
- Team process or release workflow

## Required Companions For Non-Trivial Changes

For feature work, bug fixes with behavior change, security work, or architectural changes:

1. Update at least one file under `docs/`
2. Update `CHANGELOG.md`
3. Update `docs/tracking/engineering-board.md`

Also update `docs/tracking/decision-log.md` if the change alters:

- security posture
- authorization boundaries
- data ownership rules
- canonical business states
- operational runbooks

## Recommended File Targets

| Change Type | Update These Files First |
| --- | --- |
| New feature module | `architecture/core-workflows.md`, `reference/code-map.md`, board, changelog |
| Route or auth change | `architecture/request-lifecycle.md`, `tracking/decision-log.md`, changelog |
| Billing change | `architecture/core-workflows.md`, `reference/code-map.md`, changelog |
| New command or runbook | `reference/code-map.md`, README, changelog |
| Team process change | `process/*`, board, changelog if it affects release notes |

## Style Rules

- Use short factual sentences.
- Prefer numbered steps for workflows.
- Prefer tables for component maps.
- Link to local repo paths when a doc references a specific file.
- Keep speculative roadmap statements out of architecture docs.
- If a section is intentionally incomplete, mark it clearly and create a tracked follow-up item.

## Function Index Maintenance

`docs/reference/function-index.md` is a snapshot reference. Update it when:

- public or protected methods are added or removed in `app/`
- a class moves to a new namespace or path
- operational entrypoints materially change

The point of the function index is navigation, not prose. The detailed explanation belongs in architecture or code-map documents.
