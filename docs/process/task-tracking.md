# Task Tracking

## Purpose

The repository should show what engineering work is active, queued, blocked, or complete without requiring a separate private spreadsheet or tribal memory.

The board lives in [../tracking/engineering-board.md](../tracking/engineering-board.md).

## Task Shape

Every meaningful work item should have:

- an ID such as `ENG-012`
- a short title
- status
- owner
- summary of the problem or outcome
- links to relevant docs, PRs, or files when they exist

Use [../templates/task-template.md](../templates/task-template.md) when creating a new task entry.

## Status Model

Use these states consistently:

- `Backlog`: acknowledged but not planned for the current cycle
- `Ready`: refined enough to start
- `In Progress`: actively being worked
- `Blocked`: cannot progress without a dependency or decision
- `Done`: completed and documented
- `Dropped`: intentionally not pursued

## Update Rules

Update the board when:

- work starts
- scope changes materially
- a blocker appears or clears
- work ships
- follow-up work is spun out

## Definition Of Done

A task should not move to `Done` until:

1. Code or documentation changes are complete
2. Relevant docs are updated
3. `CHANGELOG.md` is updated when needed
4. Follow-up items are captured if anything was deferred
5. Verification notes are recorded

## Board Layout

The board is intentionally simple:

- `Now`
- `Next`
- `Blocked`
- `Done`
- `Parking Lot`

This keeps it readable in Git and easy to update in code review.
