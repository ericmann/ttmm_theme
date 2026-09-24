# Phase 4 screenshots

Written by `npm run screenshots` (SPEC §6.14) against the running, seeded wp-env site
(`npx wp-env start && npx wp-env run cli wp ttm seed --reset && npm run screenshots`). Each
phase's push task re-runs it and commits the PNGs here so the owner compares each inner page
against its mock. Every seeded shot is a full-page capture at 1280 unless noted; the `footer`
crop is `.ttm-footer` only. `docs/feedback/phase-3/` stays untouched (history).

| this phase | compare against |
|---|---|
| `article.png` | `../design_article.png` (mock `2b`) |
| `journal.png` | `../design_journal.png` (mock `2c`) |
| `writing.png` | `../design_serial.png` (mock `2d`) |
| `archive-security.png` | mock `1e` (section archive) |
| `series-hub.png` | mock `1f` (series hub) |
| `series-single.png` | mock `1f` (single series, `taxonomy-series.html`), re-taken |
| `search.png` | `docs/02-*.md §H` (search) |
| `404.png` | `docs/02-*.md §H` (404) |
| `article-390.png` | mock `3b` (article on the phone) |
| `journal-390.png` | mock `2c` at 390 |
| `writing-390.png` | mock `2d` at 390 |
| `archive-390.png` | mock `1e` at 390 |
| `front-1920.png` | mock `2a` — the front page should be a centred 1280 column at 1920 (rule 42) |
| `article-1920.png` | mock `2b` — same, for an inner page |
| `article-noseries.png` | mock `2b` line 933 without series chrome (a post outside any series) |
| `archive-business.png` | mock `1e` line 933 (a section with no lead) |
| `footer.png` | mock `2a` line 327 / `2b` line 411 (front footer crop) |

## Live

Written only when `docs/fixtures/live/screens.json` exists (the live import has run; rule 48).
Each PNG shows the owner's own public content and is committed once taken.

| this phase | URL shown |
|---|---|
| `live-front.png` | `/` |
| `live-article-classic.png` | the first converted `[ref]` post found in `docs/fixtures/live/screens.json` |
| `live-archive-technology.png` | `/category/technology/` |
| `live-writing.png` | `/writing/` |
| `live-series.png` | `/series/` |
| `live-journal.png` | `/category/journal/` |
| `live-front-390.png` | `/` at 390 |
