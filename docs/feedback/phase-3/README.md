# Phase 3 screenshots

Written by `npm run screenshots` (SPEC §6.11) against the running, seeded wp-env site
(`npx wp-env start && npx wp-env run cli wp ttm seed --reset && npm run screenshots`). Each
phase's push task re-runs it and commits the PNGs here so the owner compares each inner page
against its mock. Every file is a full-page capture; the viewport is in the file name (none =
1280×900, `-390` = 390×844, `-1920` = 1920×900).

| this phase | compare against |
|---|---|
| `article.png` | `../design_article.png` (mock `2b`) |
| `journal.png` | `../design_journal.png` (mock `2c`) |
| `writing.png` | `../design_serial.png` (mock `2d`) |
| `archive-security.png` | mock `1e` (section archive) |
| `series-hub.png` | mock `1f` (series hub) |
| `series-single.png` | mock `1f` (single series, `taxonomy-series.html`) |
| `search.png` | `docs/02-*.md §H` (search) |
| `404.png` | `docs/02-*.md §H` (404) |
| `article-390.png` | mock `3b` (article on the phone) |
| `journal-390.png` | mock `2c` at 390 |
| `writing-390.png` | mock `2d` at 390 |
| `archive-390.png` | mock `1e` at 390 |
| `front-1920.png` | mock `2a` — the front page should be a centred 1280 column at 1920 (rule 42) |
| `article-1920.png` | mock `2b` — same, for an inner page |

Phase 0's set is the baseline: shared chrome only (page container, inner masthead and nav,
footer). Each later phase's push task overwrites the files its components change.
