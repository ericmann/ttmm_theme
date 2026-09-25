# Phase 5 — demo content, Playground, open source (archived)

The fifth Foundry flight (2026-09-24/25) made the repository publishable. It ships 13
Openverse CC0/PDM photographs in the seed with provable credits, a committed demo WXR,
options file and WordPress Playground blueprint generated offline by `demo:build` and
checked headlessly in CI by `demo:check`, eight generated README screenshots, a tag-driven
release workflow, and GPL licence and identity hygiene. It was approved at review round 3
(35 tasks: 30 planned plus R1-01…R1-04 and R2-01), merged as PR #15 with signed commits, and
released as `v0.2.0`. Everything here is historical. Where it disagrees with the current
`docs/SPEC.md`, the current spec wins.

`CLAUDE.md` is a copy: the root `CLAUDE.md` stays in place (`tests/unit/ScaffoldTest.php`
reads it) until the next flight's planner regenerates it.

`SUMMARY.md` §"Spec issues" lists 23 corrections to fold into the next spec; items 19–23 were
carried through every review round. The round 3 review notes on `demo:check`'s server
lifecycle (no interrupt handler, untested wiring, test fixtures left running on failure) were
fixed after the merge on `chore/phase-5-housekeeping`. The beta deployment stays with the
owner, not a flight.
