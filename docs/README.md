# Engineering Documentation

This directory is the engineer-facing source of truth for how IHRAUTO CRM is structured, how it behaves, and how the team is expected to maintain it.

## Reading Order

For a new engineer:

1. [architecture/overview.md](architecture/overview.md)
2. [architecture/request-lifecycle.md](architecture/request-lifecycle.md)
3. [architecture/core-workflows.md](architecture/core-workflows.md)
4. [reference/code-map.md](reference/code-map.md)
5. [reference/function-index.md](reference/function-index.md)

For day-to-day delivery work:

1. [process/documentation-standards.md](process/documentation-standards.md)
2. [process/changelog-policy.md](process/changelog-policy.md)
3. [process/task-tracking.md](process/task-tracking.md)
4. [tracking/engineering-board.md](tracking/engineering-board.md)

For architecture or platform changes:

1. [tracking/decision-log.md](tracking/decision-log.md)
2. [templates/task-template.md](templates/task-template.md)

## Documentation Map

| File | Purpose |
| --- | --- |
| `architecture/overview.md` | High-level system design, boundaries, and modules |
| `architecture/request-lifecycle.md` | Step-by-step web/API request flow |
| `architecture/core-workflows.md` | Step-by-step operational and business workflows |
| `reference/code-map.md` | Responsibility map of routes, controllers, services, models, middleware, and commands |
| `reference/function-index.md` | Snapshot inventory of methods by class |
| `process/documentation-standards.md` | Rules for keeping docs current |
| `process/changelog-policy.md` | How changelog entries are written and released |
| `process/task-tracking.md` | How work is tracked, updated, and closed |
| `tracking/engineering-board.md` | Current board for work in flight, queued, blocked, and done |
| `tracking/decision-log.md` | Lightweight ADR-style log of architectural decisions |
| `templates/task-template.md` | Standard task shape for new engineering work |

## Maintenance Contract

Every non-trivial change should leave behind an updated record in the repo. At minimum:

1. Update the affected docs page or add a new one.
2. Add or amend an entry in `CHANGELOG.md`.
3. Move or update the relevant task in `docs/tracking/engineering-board.md`.
4. Add a decision entry if the change alters platform rules, security posture, or architecture.

If a change does not require one of those updates, the engineer making the change should be able to explain why.
