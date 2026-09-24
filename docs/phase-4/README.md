# Phase 4 — real content (archived)

The fourth Foundry flight (2026-09-23/24) fixed the four owner-reported defects from the
phase 3 preview (section filter row, footer, "In this series" on non-series posts, "Other
series" ignoring form and section), imported the live WXR export into wp-env through the
plugin's own migration plan, converted 724 classic posts to blocks, added a live Playwright
suite and a backup/restore drill. It was approved at review round 7 (54 tasks, 6 fix rounds)
and merged as PR #14 with signed commits. Everything here is historical. Where it disagrees
with the current `docs/SPEC.md`, the current spec wins.

`CLAUDE.md` is a copy: the root `CLAUDE.md` stays in place (`tests/unit/ScaffoldTest.php`
reads it) until the next flight's planner regenerates it.

`SUMMARY.md` §"Spec issues" lists 17 corrections; the ones that still matter are folded into
the next spec. §10 of `SPEC.md` (deferred items D1–D3) is the owner backlog the next flights
draw from; the beta deployment (D1) is on the owner, not a flight.
