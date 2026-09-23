# Phase 3 — inner-template fidelity (archived)

The third Foundry flight (2026-09-22) made the article, journal post, Writing page,
section archive, series hub, single series, journal/tag/date archives, search, 404 and
static page match their mocks. It was approved at review round 4 (59 tasks) and merged
as PR #12, with one post-review fix: the Seeder now applies fixture copy to the theme's
starter-content categories and About page, which a fresh CI install exposed. Everything
here is historical. Where it disagrees with the current `docs/SPEC.md`, the current spec wins.

`CLAUDE.md` is a copy: the root `CLAUDE.md` stays in place (`tests/unit/ScaffoldTest.php`
reads it) until the next flight's planner regenerates it.

`SUMMARY.md` §"Spec issues" lists the corrections to fold into the next spec. One
correction to the summary itself: its budget row says `CLAUDE.md` still reads 62464, but
R4-01 had already updated it to 63488.
