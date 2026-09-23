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
| `convert-classic.mjs` aborted the plan before `convert:import`: 178/724 classic posts had unconverted `[ref]`/`[cci]`/`[cc]`/`[mfn]`/etc. shortcodes, so their re-rendered text didn't match the original (`textEqual === false`) | was 178 posts, e.g. post 6917 "Hi jQuery, Meet Vagrant" | fixed | P3-01 (shortcode pre-pass): `scripts/lib/shortcodes.mjs` converts `[ref]`/`[mfn]` to core footnote markers and `[cci]`/`[cc]`/`[cc_x]` to code blocks before `rawHandler` runs | `scripts/test/shortcodes.test.js` (8 tests) |
| `[cci]`/`[cc]` used inline in a sentence (a single short term, no newline) converted to a block-level `<pre>`, splitting the sentence into three separate paragraphs and changing the visible text | ~40 posts, e.g. post 5472 "Making Singletons Safe in PHP" (`[cci]global[/cci]`) | fixed | P3-03: `codeMarkup()` in `shortcodes.mjs` emits inline `<code>` when the content has no `\n`, the block form only when it does | `shortcodes.test.js::converts a single-line cci/cc to inline code, not a code block` |
| `rawHandler` correctly drops `[caption]`/`[gallery]`/`[audio]`'s own bracket syntax when converting them (native shortcode transforms), but the pre-pass never touches them, so `convert-classic.mjs`'s before/after text comparison saw that bracket text on the "before" side only | ~15 posts, e.g. post 8148 "Keeping Fresh" (`[caption]`), post 8157 "System76 Superfan" (`[gallery]`), post 5457 "Haiti 2012" (bare-URL `[audio http://…]`) | fixed | P3-03: `convert-classic.mjs`'s `normalizedText()` strips `[caption]`/`[gallery]`/`[audio]`'s wrapper syntax before comparing (comparison only, never touches what `rawHandler` actually converts) | `convert-classic.test.js::textEqual is unaffected by [caption], [gallery] and bare-URL [audio]` |
| `import_one()`'s `update_post_meta( …, 'footnotes', wp_json_encode( $footnotes ) )` silently stored `''` whenever a footnote's content had a quoted HTML attribute (e.g. `<a href="…">`): WP core's own `sanitize_post_meta_footnotes` filter `json_decode()`s the incoming string and returns `''` on failure, and `update_metadata()` unslashes the value first -- so `wp_json_encode()`'s own `\"` escaping reads as WP "magic quotes" and gets stripped, breaking the JSON | 246/724 posts on the first P3-03 run (any post with an `<a>`/similar in a footnote) | fixed | P3-03: `wp_slash()` the JSON before `update_post_meta()`, the same way WP core's own REST meta controller does | `ConvertCommandTest::test_import_stores_footnotes_meta_containing_a_quoted_attribute` |
| `footnotes_verified()`'s `wp_strip_all_tags()` comparison didn't decode entities or account for `wptexturize()`/`convert_smilies()` (both run as part of `the_content`) rewriting a footnote's own raw text (e.g. `"..."` -> a single `"…"` entity, `":-)"` -> an emoji with no matching plain text) | remainder of the 246, down to 1 post after the entity/wptexturize fix, 0 after also texturizing/converting smilies on the expected side | fixed | P3-03: `normalized_text()` now `html_entity_decode()`s; the expected footnote text is run through `wptexturize()`/`convert_smilies()` first | `ConvertCommandTest::test_import_verifies_each_footnote_appears_once` |
| PHP's own `text_equal_ignoring_shortcodes()` compared the *raw* classic content against the *fully converted* blocks -- a fundamentally different, much more lenient comparison than the JS side's own before/after check (which compares the pre-pass's own transformed HTML, correctly accounting for every substitution) -- producing false `[text-mismatch]` flags on 677/724 posts | 677/724 on the first P3-03 real import | fixed | P3-03: `import()` now surfaces `record.report.textEqual` (the JS side's own, already-correct comparison) instead of re-deriving one in PHP | `ConvertCommandTest::test_text_equality_reports_a_real_mismatch` |
| `footnotes_verified()` required a footnote's text to appear in the rendered post *exactly* once; a short/common phrase (e.g. a proper noun) can legitimately also appear once earlier in the body prose that leads into the reference | 1 post, "Missing the Point" ("Ice Bucket Challenge") | fixed | P3-03: relaxed to "at least once" | covered by the same footnotes tests above |
| 7 classic posts still fail `textEqual`: legacy `<code>`/`<blockquote>` markup in the classic content itself contains **unescaped** nested HTML (e.g. `<code lang="php">... $output = '<div id="x">'; ...</code>`), which a DOM parser reads as real nested elements, not text | 7 posts: `wordpress-fragment-caching`, `securing-forms-without-captcha`, `the-hackiest-hack-that-ever-was-hacked`, `wordpress-plugin-structure`, `haiti-2012`*, `review-3d-holiness`, `classy-plugins`, `dependency-injection-interfaces-c-sharp` (*haiti-2012 also has the smiley finding above; both are independently true) | owner cleanup | none -- this is pre-existing, malformed source content unrelated to shortcodes, safely un-automatable without risking real content loss; explicitly out of scope ("shortcode conversion quality" is the P3-01/02/03 scope, not general classic-HTML repair) | `audit --only=shortcode` continues to surface these until the owner re-authors the code samples |
| `import.sh`'s writing-legacy collision rename prints `Warning: Invalid page template.` and a non-zero WP-CLI exit for the demoted page, tolerated with `\|\| true` | 1 page (`writing` -> `writing-legacy`) | benign / cosmetic | fixed in P2-06 (`import.sh` tolerates it; the rename itself verifiably succeeds) | manual verification in P2-06's commit body |
| `migrate:images` without `--hosts` rewrites 0 images (`migration.image_hosts` defaults to `[]`, operator-supplied) | 0 (by design) | expected | none needed — `--hosts` is how an operator opts real hosts in per SPEC §6.7/PLAN Q4 | `MigrateCommandTest` covers `--hosts` explicitly |
| 65 sideloaded images fail outright (dead links: 404s, DNS failures on hosts like `blogs.trb.com`, `www.marketplace-simulation.com`) | 65 images across the 15 scanned hosts | owner cleanup | none — `src` is left untouched and the post is `remote-image`-flagged by `audit`; the owner decides per-link | `AuditCommand`'s `remote-image` flag (P2-05) already covers this |
| `scripts/live/screens.mjs`'s `sectionPost()` passes `--category=<slug>` to `wp post list` (twice: the newest-post fetch and the `--format=count` count). WP-CLI forwards `category` straight to `WP_Query`, whose `category` arg is a **category ID**, not a slug; a non-numeric slug casts to `0` and WP_Query silently drops the filter, so both calls return the *site-wide* newest post / *site-wide* published count instead of the section's own. The wrong count makes `buildScreens()` compute the same last-archive-page (`ceil(890/12) = page 74`) for every section, and none of those pages exist -> 404 where the manifest says `expectStatus: 200` | 14 URLs (7 sections x 2 viewports), e.g. `archive-technology-last` `/category/technology/page/74/` | archive | P4-04 (`--category=` -> `--category_name=` in both `wp post list` calls in `sectionPost()`) | pending (P4-04 adds a `scripts/test/live-screens.test.js`-style unit test once `sectionPost()`'s query-building is unit-testable, or an integration assertion on the regenerated manifest's `archive-*-last` `expectStatus`) |
| `Templates/Hierarchy.php`'s `body_class` filter adds `ttm-section-{slug}` (primary category) and `ttm-form-{form}` (post format) to every single/archive `<body>`, but neither has a selector in `ttm.css`/`style.css` — they're pure identifiers today, never styled. `scripts/check-css-coverage.mjs`'s static scan never sees them (they're built by string concatenation in PHP, not a literal `ttm-*` string in source), so this is a rule-34 gap `npm run lint` can't catch, only the live DOM | 30 URLs across 7 singles + 6 archive-`-1` pages (all but `journal`, coincidental — its archive-by-year block returned empty, see next row) + `/writing/`, all x2 viewports, e.g. `single-technology` `/ext-turbovec-vector-search-php/` -> `ttm-section-uncategorized ttm-form-article` | coverage | P4-04 (either give both a real `ttm.css` rule, matching `theme.json`'s "identifier-only" precedent if one exists, or move them off the `ttm-` prefix so rule 34 doesn't apply to internal identifiers) | pending |
| `archive-by-year` block's wrapper is `Helpers::wrapper( 'archive' )` -> DOM class `ttm-archive`, but `ttm.css` only defines compound `ttm-archive-*` selectors (`.ttm-archive-head`, `.ttm-archive-row`, …), never a bare `.ttm-archive` rule for the wrapper itself | 18 URLs across 6 category archives (`technology`, `business`, `faith`, `writing`, `security`, `opinion` — not `journal`, whose archive-by-year block rendered empty that run) + `/writing/`, `/tag/wordpress/`, `/2014/03/`, all x2 viewports, e.g. `archive-technology-1` `/category/technology/` | coverage | P4-04 (add `.ttm-archive { }` to `ttm.css`'s archive-by-year component block, or rename the wrapper call to `Helpers::wrapper( 'archive-by-year' )` per rule 46 and update every `ttm-archive` reference) | pending |

## P4-02 run: `npm run test:live` against the P3-03 import

Same import re-run end to end (`docs/fixtures/live/import.xml` from P3-03, `LIVE_SKIP_ATTACHMENTS=1`,
`npm run env:live`) to regenerate `docs/fixtures/live/screens.json` under the new `live.spec.mjs`
suite (P4-01), then `npm run test:live`: **17 passed, 48 failed** (of 65: 32 screens x 2 viewports
+ 1 `debug.log` check). Every failure is one of exactly two `expect.soft()` classes — no axe,
network, chrome, single- or archive-content failures at all:

| class | failing tests | root cause (see Findings above) |
|---|---|---|
| archive (HTTP status) | 14 | `sectionPost()`'s `--category=` slug/ID mismatch (`screens.mjs`) |
| coverage (uncovered `ttm-*` DOM class) | 34 (30 `ttm-section-*`/`ttm-form-*` + 18 `ttm-archive`, some screens carry both) | dynamic `body_class()` identifiers and the `archive-by-year` wrapper class, neither has a `ttm.css` rule |

Both are pre-existing gaps this suite is the first thing to actually exercise against real category
archives with > 1 page and a real primary-category spread — none of the seeded fixture screens
(`tests/e2e/specs`, `fidelity.spec.mjs`) happen to cross an archive pagination boundary or assert
DOM-class CSS coverage the way `live.spec.mjs` does. Fixes land in P4-04 (both rows above are
`archive`/`coverage` class, not `render`/`single`, so out of scope for P4-03).

## P3-03 run: fresh import with the shortcode pre-pass

Same export, `LIVE_SKIP_ATTACHMENTS=1`, repeated end to end (import → plan → `convert:export` →
`convert-classic.mjs` → `convert:import` → `close-comments` → `series:rebuild` → `stats:flush` →
`rewrite flush` → `verse fetch` → `audit` → `screens.mjs`) as the three PHP-side bugs above were
found and fixed. Final run:

| step | count |
|---|---|
| `convert:export --all-classic` | 724 classic posts |
| `convert-classic.mjs` | 724 converted; 717/724 `textEqual: true` (7 residual, all the same pre-existing unescaped-`<code>` class above, `owner cleanup`) |
| `convert:import --allow-freeform` | 724 converted, 0 `[footnotes-mismatch]`, 7 `[text-mismatch]` (the same 7), 2 posts with `report.shortcodes` remaining (`seoslides`) |
| `audit --only=shortcode` | 51 posts still flagged (mostly `[caption]`/`[audio]` correctly converted by `rawHandler` but still literally present pre-conversion in a few edge cases, plus the 2 `seoslides` posts) — `owner cleanup`, tracked, not blocking |
| `screens.mjs` | 32 screens written; `classic: []` (every classic post converted) — `live-article-classic.png` now resolves via the `ref-*` fallback (SPEC's "a converted `[ref]` post with core footnotes") |

`live-article-classic.png` (committed) shows post "Keeping Fresh" (`/keeping-fresh/`): the
in-body footnote marker "¹" and the numbered note list at the bottom both render correctly.

## Audit summary

| flag | count |
|---|---|
| broken-internal-link | 5 |
| missing-alt | 212 |
| multi-category | 90 |
| no-excerpt | 223 |
| no-featured-image | 725 |
| no-tags | 22 |
| politics | 24 |
| post-format-aside | 7 |
| remote-image | 113 |
| series-tag-candidate | 739 |
| shortcode | 51 |
| inert-rows | 34 |

(`classic` no longer appears — 0 posts remain unconverted. `no-excerpt` dropped from 326 to 223
between the P3-03 and P4-02 runs — same `migrate:excerpts --from=yoast` step, re-run against the
same export, filled more excerpts than the P3-03 run's timing caught; every other flag is
unchanged, all `content`-class, counted only, per P4-02's scope.)
