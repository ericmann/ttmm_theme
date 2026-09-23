# Live triage

Skeleton (P2-06); filled in as `env:live`/`test:live` runs land (P2-08 onward, SPEC §6.10, rule 52).

## Import runs

Both runs (P2-08) against `docs/ericmann039sblog.WordPress.2026-09-23.xml`, 1273 WXR rows.
Every step ran `--dry-run` first where supported; every dry-run matched its real run's plan.
Both runs stop cleanly at `convert-classic.mjs` (see Findings) — everything through
`convert:export` completes.

| date | mode | step | count | seconds |
|---|---|---|---|---|
| 2026-09-23 | skip-attachments | wp site empty | — | 2 |
| 2026-09-23 | skip-attachments | wp ttm stats:flush | 0 transients | 2 |
| 2026-09-23 | skip-attachments | wp ttm seed --starter-only | 8 categories, 4 pages, 1 nav | 2 |
| 2026-09-23 | skip-attachments | wp import (--skip=attachment) | 1273 rows | 118 |
| 2026-09-23 | skip-attachments | wp search-replace | 92 replacements | 3 |
| 2026-09-23 | skip-attachments | migrate:politics | already child of Opinion | 2 |
| 2026-09-23 | skip-attachments | primary:assign --from-yoast | 0 used, 0 skipped | 2 |
| 2026-09-23 | skip-attachments | primary:assign | remainder by nav order | 2 |
| 2026-09-23 | skip-attachments | series:assign boundless-summer-challenge | 23 posts | 2 |
| 2026-09-23 | skip-attachments | series:assign cryptopals | 9 posts | 2 |
| 2026-09-23 | skip-attachments | recount --all | 901 posts | 3 |
| 2026-09-23 | skip-attachments | migrate:excerpts --from=yoast | 103 filled | 5 |
| 2026-09-23 | skip-attachments | migrate:images (no --hosts) | 0 rewritten | 2 |
| 2026-09-23 | skip-attachments | convert:export --all-classic | 724 classic posts | 2 |
| 2026-09-23 | skip-attachments | convert-classic.mjs | 178/724 textEqual mismatches -> abort (expected, P3-01) | — |
| 2026-09-23 | full | wp site empty | — | 2 |
| 2026-09-23 | full | wp ttm stats:flush | 0 transients | 1 |
| 2026-09-23 | full | wp ttm seed --starter-only | 8 categories, 4 pages, 1 nav | 2 |
| 2026-09-23 | full | wp import (266 attachments fetched) | 1273 rows | 371 |
| 2026-09-23 | full | wp search-replace | 81 replacements | 2 |
| 2026-09-23 | full | migrate:politics | already child of Opinion | 1 |
| 2026-09-23 | full | primary:assign --from-yoast | 0 used, 0 skipped | 2 |
| 2026-09-23 | full | primary:assign | remainder by nav order | 2 |
| 2026-09-23 | full | series:assign boundless-summer-challenge | 23 posts | 2 |
| 2026-09-23 | full | series:assign cryptopals | 9 posts | 2 |
| 2026-09-23 | full | recount --all | 901 posts | 3 |
| 2026-09-23 | full | migrate:excerpts --from=yoast | 103 filled | 5 |
| 2026-09-23 | full | migrate:images (no --hosts) | 0 rewritten | 2 |
| 2026-09-23 | full | convert:export --all-classic | 724 classic posts | 2 |
| 2026-09-23 | full | convert-classic.mjs | 178/724 textEqual mismatches -> abort (expected, P3-01) | — |

### Tuning: `excerpt_length` (P2-08, ⚠️ ASSUMPTION)

`migrate:excerpts --from=yoast --words=<n>` (measurement-only override added this task) run
`--dry-run` against every candidate post with a non-empty `_yoast_wpseo_metadesc` and a
non-Journal primary category (589 candidates on this export, a broader set than the 103 the
real run actually filled — most already carry a hand-written excerpt migrate:excerpts never
overwrites) at 40, 55 and 70 words:

| words | sentence-boundary cut | word-cut with "…" |
|---|---|---|
| 40 | 589 | 0 |
| 55 | 589 | 0 |
| 70 | 589 | 0 |

Every candidate's Yoast description already lands on a sentence boundary well inside 40 words;
none is ever hard-cut at any of the three values. **Kept `excerpt_length = 55`** (Config.php
unchanged) — no value comes close to halving the word-cut count because the word-cut count is
already zero throughout the tested range.

### Tuning: `migration.image_timeout` (P2-08, ⚠️ ASSUMPTION)

The export's `<img src>` hosts other than Photon/`eric.mann.blog` (found by scanning every
published post's content): `ttmm.io`, `eamann.com`, `ttmm.wpengine.com`,
`groundedchristianity.com`, `mindsharestrategy.com`, `imgs.xkcd.com`, `pbs.twimg.com`,
`github.com`, `media3.giphy.com`, `blogs.trb.com`, `www.paypalobjects.com`,
`i.stack.imgur.com`, `www.marketplace-simulation.com`, `upload.wikimedia.org`,
`www.assoc-amazon.com`. `migrate:images --hosts=<those 15 hosts>` (real run, not `--dry-run` --
sideloading needs a real fetch to measure) at the default 20s timeout:

| timeout | attempts | successes | timeouts | other failures |
|---|---|---|---|---|
| 20s | 157 | 92 | 0 | 65 |

0/157 = 0% timeouts, well under the 10% threshold — no 40s retry pass was needed. The 65 other
failures are dead links on genuinely old posts (404s, DNS failures on defunct hosts), not
timeouts; that's `remote-image`/owner-cleanup territory, not a timeout tuning question.
**Kept `migration.image_timeout = 20`** (Config.php unchanged).

## Findings

| finding | URLs (count, one example) | class | fix (commit) or "owner cleanup" | test |
|---|---|---|---|---|
| `convert-classic.mjs` aborts the plan before `convert:import`: 178/724 classic posts still have unconverted `[ref]`/`[cci]`/`[cc]`/`[mfn]`/etc. shortcodes, so their re-rendered text doesn't match the original (`textEqual === false`) | 178 posts (all classic posts containing a known shortcode), e.g. post 6917 "Hi jQuery, Meet Vagrant" | expected / not-yet-implemented | P3-01 (shortcode pre-pass), explicitly out of scope for P2-06/P2-07/P2-08 | `scripts/test/convert-classic.test.js` gains fixtures once the pre-pass lands |
| `import.sh`'s writing-legacy collision rename prints `Warning: Invalid page template.` and a non-zero WP-CLI exit for the demoted page, tolerated with `\|\| true` | 1 page (`writing` -> `writing-legacy`) | benign / cosmetic | fixed in P2-06 (`import.sh` tolerates it; the rename itself verifiably succeeds) | manual verification in P2-06's commit body |
| `migrate:images` without `--hosts` rewrites 0 images (`migration.image_hosts` defaults to `[]`, operator-supplied) | 0 (by design) | expected | none needed — `--hosts` is how an operator opts real hosts in per SPEC §6.7/PLAN Q4 | `MigrateCommandTest` covers `--hosts` explicitly |
| 65 sideloaded images fail outright (dead links: 404s, DNS failures on hosts like `blogs.trb.com`, `www.marketplace-simulation.com`) | 65 images across the 15 scanned hosts | owner cleanup | none — `src` is left untouched and the post is `remote-image`-flagged by `audit`; the owner decides per-link | `AuditCommand`'s `remote-image` flag (P2-05) already covers this |

## Audit summary

Not yet reached on either P2-08 run: `plan.sh` aborts at `convert-classic.mjs` (see Findings)
before its `wp ttm audit --format=csv` / `screens.mjs` steps run. Appended here once P3-01's
shortcode pre-pass lets a run reach the end.
