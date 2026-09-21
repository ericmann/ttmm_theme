# Review — These Things Matter build
Round: 0

Branch `build/2026-09-21` (base `poc` @ `8379c6f`, head `f89f264`), 76 tasks, 0 blocked, 0 skipped.

## Verdict: CHANGES REQUESTED

`foundry_verify` is fully green on the branch as checked out (composer lint 0 errors, 115 unit,
337 integration + 1 skip, 48 e2e, theme.json check, budget 32990/33000), and every mechanical
grep for CLAUDE.md constraints 1, 3, 5–9, 12, 15–19, 22, 23, 29, 31 and the dark-mode rule is
clean. Mutation sampling (Cache\Headers boundary, Newsletter\Handler previous-window token,
Dates::relative_day, Meta\Form::derive, Values::category_count, Newsletter\Providers fallback,
Query\Cells journal exclusion, Query\Lead stale-technology) failed the right tests every time.

Approval is withheld because categories 1–3 are not clean across the branch: the CSS budget
constraint was silently retuned outside its tuning task, SPEC §4.2 module boundaries are crossed
upward in three files, several §5.4 tunables are dead keys with the number hard-coded elsewhere,
and a handful of acceptance tests either bypass the mechanic they name or were bent to match the
code. Beyond that there are real behavioural bugs (F6 off-by-one, `last_update` always "now",
`migrate:politics` leaving Politics posts outside Opinion, HEAD requests uncached, an unstyled
visible honeypot).

## Findings (most severe first)

### 1. Constraints (CLAUDE.md `## Constraints`)

**C1 — CSS budget retuned outside the tuning task.** `scripts/check-budget.mjs:8`
`const cssBudgetBytes = 33000;` vs CLAUDE.md "`ttm.css` ≤ `cssBudgetBytes` (25600)" and SPEC
rule 30 "≤ 25 KB". `ttm.css` is 32990 bytes. The ⚠️ ASSUMPTION is tunable, but PLAN Decisions
assign that tuning to P2-03 only; P5-04 (`cc4125a`) and P6-05 (`d16453d`) each bumped it
(25600→28000→33000) although `scripts/check-budget.mjs` is not in either task's Files touched.
What breaks: the constraint no longer constrains; CLAUDE.md and the code disagree. Fix: a real
tightening pass on `ttm.css` (duplicate button/grid rules, the P8-07 comment blocks, responsive
overrides), then whatever remains gets a `Measurement:` in the log and CLAUDE.md/SPEC brought into
line with the one value in `check-budget.mjs`. Tasks P5-04, P6-05.

**C2 — Rule 24: tunables hard-coded outside `Config.php`, and Config keys that nothing reads.**
- `plugins/ttm-core/blocks/writing-cell/render.php:154` `'posts_per_page' => 3` (F2 plain mode;
  no key exists). Task P3-08.
- `plugins/ttm-core/src/Cache/Cloudflare.php:67` and
  `plugins/ttm-core/src/Newsletter/Provider/CustomUrl.php:101` `'timeout' => 10` — SPEC rule 24
  names timeouts explicitly; `verse.timeout_seconds` shows the pattern. Tasks P7-02, P7-03.
- `plugins/ttm-core/src/Bindings/Sources.php:178,467` and
  `plugins/ttm-core/blocks/lead-story/render.php:35` `'politics' === $category->slug` while
  `sections.politics_slug` exists. Tasks P3-05, P3-06.
- `plugins/ttm-core/src/Query/Lead.php:140` `get_term_by( 'slug', 'technology', 'category' )` —
  no `sections.technology_slug` key (spec issue below). Task P3-04.
- `plugins/ttm-core/src/Query/Cells.php:116` `'journal' === $section` with `$journal_slug` already
  in scope. Task P4-05.
- Dead keys (grep: read only in `Config.php`): `cells.thin_days`, `cells.stale_year_days` (F9
  stale-year branch not implemented — see S4), `journal.rail_count` (the rail count is the
  pattern literal `"perPage":3` in `themes/ttm-theme/patterns/journal-rail.php:28`),
  `writing.shelf_limit` (F1 shelf uses `alsoRunningLimit` = 3, 06 F1 says up to 4),
  `writing.chapters_recent`. Tasks P3-04, P3-08, P3-10, P6-05.
- Low: `plugins/ttm-core/src/Cli/Seeder.php:87` (`120` quiet offset), `:572`, `:575`;
  `plugins/ttm-core/src/Fiction/Books.php:93` (`+ 3` blank rows), `:141` (`< 2`).
- `scripts/forbidden-patterns.sh` runs rule 24 as a warning; it should fail for `src/` and
  `blocks/` with an allow-list for HTTP status codes.

**C3 — Block wrapper contract.** `plugins/ttm-core/blocks/syndicated-to/render.php:63`
`<p <?php echo Helpers::wrapper( 'syndication' ); ?>>` — SPEC §6.1 says every block wrapper is
`<div class="ttm-<name>" data-ttm-block>`; theme `:has()` rules and the separability test assume
`div`. Same file `:66-70` interpolates the translated `'Syndicated to %s'` unescaped. Fix: `div`
wrapper, inner `<p>`, `wp_kses` the sprintf with an `a[href]` allow-list. Task P4-03.

**C4 — Rule 26 (every binding source has a unit test for its normal and empty value).**
`tests/unit/Bindings/ValuesTest.php` has no empty-value case for `kicker` (no section), `meta_line`
(no parts), `short_date`/`relative_date` (no date). `ArticleValuesTest`/`PaginationValuesTest` do
cover theirs. Tasks P3-05.

### 2. Boundaries (SPEC §4.2)

**B1 — `plugins/ttm-core/src/Query/Archive.php:12-13`** `use TTM\Core\Bindings\Values;` and
`use TTM\Core\Blocks\Helpers;` — Query (row 5) importing Bindings (row 9) and Blocks (row 10) is
upward. `Archive::label_next/label_previous` call `Values::pagination_label` (`:247`);
`track_archive_scope/group_by_year` mutate `Helpers::$archive_scope` (`:44,58`). Fix: the archive
scope flag lives in `Query\Archive`; the pagination relabel filters (which need `Values`) move to
`Bindings\Sources` (Bindings may import Query), `Archive` exposing only `year_range()`. Task P5-01,
P5-02.

**B2 — `plugins/ttm-core/src/Bindings/Sources.php:12`** `use TTM\Core\Blocks\Helpers;`
(`series_position`, `date_short`, `reading_time` at `:181,212,216,405,422,554`). Bindings (row 9)
importing Blocks (row 10) is upward. Fix: move those pure helpers to `Support`/`Meta` (e.g.
`Meta\SeriesPosition`, `Support\Dates`), `Blocks\Helpers` delegates. Task P3-05, P4-04.

**B3 — `plugins/ttm-core/src/Taxonomy/SeriesAdmin.php:13`** `use TTM\Core\Query\SeriesIndex;`
(`:179,228` part list and Parts column). Taxonomy may import Support and Config only; Query
imports Taxonomy, so this is a cycle. Fix: move the part list and Parts column into `Editor/`
(WP glue that already reads `SeriesIndex`). Task P1-06.

**B4 — column mismatches that are downward (not cycles, recorded, no task):**
`Editor/Checks.php:13`, `Editor/Columns.php` (FQ `\TTM\Core\Query\SeriesIndex`),
`Templates/Hierarchy.php:15`, `Cache/Purge.php:13-14`, `Rest/SeriesController.php:13`. See spec
issue SI-2; the reviewer accepts these pending a SPEC column update.

No file under `Cache/`, `Verse/` or `Newsletter/` imports `Blocks`. Theme rule 1/3 clean
(`functions.php:81`, `inc/patterns.php:45` guarded).

### 3. Tests

**T1 — `tests/integration/Query/SeriesIndexTest.php`** every test calls `SeriesIndex::rebuild()`
directly (`:30`); nothing exercises `register()`'s hooks or `schedule_rebuild()`. Deleting the
body of `register()` fails no test although the PLAN names `rebuilds_on_publish` and
`delete_series_term_removes_row`. Fix: after `wp_publish_post()`/`wp_delete_term()` assert
`has_action('shutdown', [SeriesIndex::class,'maybe_rebuild'])` and call `maybe_rebuild()`. Task
P1-04.

**T2 — `tests/integration/Rest/SeriesRestTest.php:62-71`** keeps the PLAN's test name
`test_form_filter_fiction_matches_non_nonfiction` but sends `form=novel`, because
`plugins/ttm-core/src/Rest/SeriesController.php:46` enumerates `Series::FORMS` only and
`?form=fiction` (05 §3 `any|nonfiction|fiction`) returns 400. The test was bent to the code. Fix:
add `fiction` to the enum, filter `form !== 'nonfiction'`, restore the test. Task P1-11.

**T3 — `tests/integration/Separability/PluginAloneTest.php:188-189`** `most-read` and
`tag-filter` pass `expect_non_empty=false`, so under the default theme they are only asserted to
return a string; the task required every block to render its wrapper. Fix: seed a
`ttm_featured_in_section` post and a tag in the fixture and flip both to `true`. Task P7-08.

**T4 — `tests/integration/Theme/ChromePartsTest.php:39-40`** `markTestSkipped` whenever
`Blocks\Registrar` exists — the suite's permanent skip. P7-08's `ThemeAloneTest` now covers the
intent; delete or implement. Task P2-05.

**T5 — `tests/integration/Blocks/VerseOfTheDayTest.php`** F6 test sets `ttm_verse` to `[]`, so
the branch in S1 is never exercised; the F6 test asserts `'Jan 1'`, which cannot distinguish `M j`
from `Dates::short_month`. Task P3-01.

### 4. Performance (SPEC §3.2)

**P1 — `plugins/ttm-core/src/Cli/MigrateCommand.php:129` (`close_comments`)** `wp_update_post()`
per post fires `transition_post_status` publish→publish and therefore `Cache\Purge::on_transition`
→ one `ttm_purge_urls` (and Cloudflare POST when configured) per post — 888 purges on the live
migration, plus WordCount/Form/PrimaryCategory `on_save` per post. Fix: detach the Purge listener
for the batch and fire one purge at the end (or `$wpdb->update` the two columns). Task P8-04.

**P2 — rule 12 letter:** `plugins/ttm-core/src/Cli/AuditCommand.php:191-196,214-232` and
`plugins/ttm-core/src/Query/SeriesIndex.php:84` call `get_terms()` without `number`. Low; see SI-5.

### 5. Spec drift / behavioural bugs

**S1 — F6 off-by-one.** `plugins/ttm-core/blocks/verse-of-the-day/render.php:23-33`: when
`ttm_verse['date'] !== today` the stored verse is discarded and `ttm_verse_history[0]` shown.
`Fetcher::store()` only pushes the *previous* verse into history (`Fetcher.php:246-252`), so after a
failed fetch the block shows the day before yesterday, not "the last good verse with its date".
Seed data masks it (item[0] in both). Fix: stored `ttm_verse` is the first candidate when its date
≤ today. Task P3-01.

**S2 — `last_update` is always "now".** `plugins/ttm-core/src/Query/SeriesIndex.php:134`
`'last_update' => Clock::now()->format(...)` on every row on every rebuild, so all rows tie.
Consumers silently degrade to term order: `blocks/series-list/render.php:100-104` (`orderby=updated`
on the hub), `src/Fiction/Serials.php:29-34` (`active()` "newest in-progress"),
`blocks/serial-hero/render.php:31` (F3 "most recently completed"), `SeriesIndex::sorted_by_update()`.
`SeriesListTest:79-81` and `SerialsTest:66-67` write `last_update` straight into the option, bypassing
the mechanic; P6-02 noticed and worked around it locally. Fix: `last_update` = newest published
part's date, stable across rebuilds. Tasks P1-04 (root), P3-07, P3-08, P6-03 (tests).

**S3 — `migrate:politics --to=child` leaves Politics posts outside Opinion.**
`plugins/ttm-core/src/Cli/MigrateCommand.php:187` only `wp_update_term(... ['parent' => $opinion_id])`.
No post gains the `opinion` term, so `PrimaryCategory::resolve()` (nav order, else first assigned)
yields `politics` for the live site's 24 Politics-only posts — excluded from the Opinion cell/archive
`ttmPrimaryOnly` queries, body class `ttm-section-politics`, kicker "Politics · Politics"
(`Sources.php:178`). The seed files such posts under both terms; the migration does not produce
that state. Fix: add `opinion` to every Politics post's categories (dry-run counts), then recompute
`ttm_primary_category`. Task P8-04.

**S4 — F9 stale-year branch missing.** 06 F9 "if < 1 post in a year, drop the dek and show the 2
most recent regardless of age with full dates". Only the full-date part exists;
`cells.thin_days`/`cells.stale_year_days` are unread. `FrontPageStatesTest` asserts only a year
regex. Fix in `Query\Cells` (`posts_per_page => 2`, `is-stale` class) + CSS dek hide. Tasks P3-04,
P3-10, P3-11.

**S5 — `series:assign --form=<fiction>` never derives `ttm_form=chapter`.**
`plugins/ttm-core/src/Cli/SeriesCommand.php:91-99` writes the term meta and part numbers with
`wp_set_object_terms`/`update_post_meta`, which do not fire `save_post_post`, so `Meta\Form::on_save`
never runs (same bug class P7-08 fixed in the Seeder). Migrating `boundless`/`cryptopals` is
nonfiction so it is masked today. Fix: call `Form::on_save()` per assigned post. Task P1-12.

**S6 — `Nav\CurrentSection` has no series-page branch.**
`plugins/ttm-core/src/Nav/CurrentSection.php:65,80` handles `is_singular('post')` and
`is_category()` only; PLAN P2-06 requires `current-section` on the Series item "on series pages"
(`/series/` hub, `series` taxonomy archives). Task P2-06.

**S7 — HEAD requests get no `Cache-Control`.** `plugins/ttm-core/src/Cache/Headers.php:102-104`
returns `null` for anything but `GET`. The Phase 7 manual check is literally `curl -I` (HEAD) and
will show nothing. Fix: treat `HEAD` as `GET`. Task P7-01.

**S8 — Visible honeypot.** `plugins/ttm-core/src/Newsletter/Provider/CustomUrl.php:70` emits
`<input class="ttm-hp" …>`; PLAN P7-03 says the theme hides `.ttm-hp`, but `ttm.css` has no such rule
(grep). A human who fills the visible unlabeled box is silently dropped. Fix: `.ttm-hp` rule in
ttm.css §4.32. Tasks P7-03, P2-03.

**S9 — Verse date "Sep" not "Sept".** `blocks/verse-of-the-day/render.php:40` `format( 'M j' )`;
03 §13/F6 require `Dates::short_month`. Task P3-01. Related, low: `:59` `esc_html(reference)` after
rule 21's `wp_kses` (allowed `em`/`strong` would print escaped); `Verse/Fetcher.php:parse_item`
uses `Clock::at(published_at)->format('Y-m-d')`, which keeps the payload's own offset rather than
converting to site timezone (Appendix A). Task P3-02.

**S10 — `wp ttm audit` correctness.** `plugins/ttm-core/src/Cli/AuditCommand.php:152-156`
`trim( $alt_match[0], "alt=\"' " )` strips the letters a/l/t from the whole `alt="…"` match, so
`alt="tall"` is reported missing; `:280-296` (`has_broken_internal_link`) flags every same-host href
that is not a post/page/term permalink — uploads, `/feed/`, `/page/N/`, date archives — so the check
is mostly false positives on 888 posts. Task P8-03.

**S11 — Nested landmarks.** `themes/ttm-theme/templates/404.html:1,36`, `index.html:1,27`,
`page.html:1,18` invoke `header-inner`/`footer` parts with `tagName` while the parts already render
`<header>`/`<footer>` (P3-10 log noticed for index and left it). `page.html` serves `/about/` and
`/newsletter/`, which the e2e axe scan never visits. Task P2-06.

**S12 — Writing cell.** `blocks/writing-cell/render.php:149-158` F2 query ignores
`ttm_primary_category` and the lead exclusion every other cell applies (03 §8) and keeps the fiction
"All serials →" heading link; `:20-23,80` F1 shelf capped by `alsoRunningLimit` (3) instead of
`writing.shelf_limit` (4). Task P3-08.

**S13 — Low.** `Verse/Cron.php:43` `wp_schedule_event( $next->getTimestamp(), 'daily', …)` drifts
one hour across DST (fetch at 04:00/06:00 instead of `verse.fetch_hour`) — task P3-03.
`blocks/serial-hero/render.php:55` `is_page() ? 'h1' : 'h2'` leaves `/category/writing/` with no
`<h1>` — task P6-03. `scripts/convert-classic.mjs:129` `html` report field duplicates `blocks` instead
of the core/html count in PLAN — task P8-01. `Blocks/Helpers::kicker()` unused and would print
"part N of N" for open-ended series; `Sources::previous_part()` duplicates `lead-story/render.php:44-60`.

### 6. Interpretation choices (from commit bodies and the PROGRESS log)

Accepted as the reading most consistent with SPEC: Q4 option repeater; `Admin/` shell; integration
config location; fixtures mapping; `sanitize_part` int cast (rejects negatives); `newsletter.fallback_email`
in the overlay and `newsletter.api_key` excluded; no `exit` after `wp_safe_redirect` + `headers_sent()`
guard; `Fetcher::endpoint()` host pin; Registrar/Sources re-registration guards; `query_loop_block_query_vars`
reading the child block's context; `relative-date` always adding the year beyond the rail window; F2
requiring `ttm_form_locked`; front-page parts without `tagName`; section-cell template outside the scanned
patterns dir; Cloudflare listener always attached with `available()` at call time; Purge on
publish→publish; P7-04 Jetpack Outcome B (live-verified, seed falls back to `mailto` as SPEC's own clause
anticipates); P8-01 Outcome A; P8-05 `_publicize_done_external` only; `partsLimit:0` explicit vs
defaulted; P0-03 weekday correction (2026-09-18 is a Friday).

Not accepted: the CSS budget bumps (C1); `?form=novel` in place of `fiction` (T2); `last_update = now`
(S2); GET-only cache headers (S7).

### 7. Blocked and skipped tasks

None. Nothing to unblock.

### 8. Readability / i18n (rule 32)

- `themes/ttm-theme/patterns/masthead-front.php:13-21`, `masthead-inner.php:13-21` hard-code the
  seven section labels untranslated and ignore the real term name. Task P2-05.
- `plugins/ttm-core/src/Fiction/Books.php:127` `sprintf( 'Book %d', … )` untranslated. Task P1-10.
- `plugins/ttm-core/blocks/book-grid/render.php:29` `ucfirst( str_replace( '-', ' ', form ) )`
  visible caption untranslated. Task P6-04.
- `themes/ttm-theme/functions.php:99` `title="%s RSS"` literal. Task P5-01.
- `docs/DEPLOYMENT.md`: `Handler::client_ip()` reads `REMOTE_ADDR` only; behind the Cloudflare
  Tunnel every visitor shares one address unless the ingress restores `CF-Connecting-IP`, turning the
  per-IP limit into a site-wide 5/10 min for `custom-url`. Document. Task P8-06.
- CLAUDE.md is stale in three places: rule 16 allow-list names `Newsletter/Handler.php` (SPEC and the
  code say `Newsletter/Provider/CustomUrl.php`); the CSS budget constraint says 25600; the theme module
  map lists `inc/template-hierarchy.php`, which does not exist (SI-4).

## Spec issues

- **SI-1** Rule 15 says `ttm_purchase_links`/`ttm_syndication`/`ttm_books` are stored as JSON via
  `wp_json_encode` with `rest_validate_value_from_schema`; rule 19 requires typed `show_in_rest`
  schemas, which need native array/object meta. The code stores PHP arrays with strict hand-written
  sanitizers and REST-path schema validation. Recommend rewording rule 15 to "schema-validated
  arrays"; not a code defect.
- **SI-2** §4.2 "May import" column is narrower than the design needs: `Editor` needs `Query\SeriesIndex`
  (duplicate-part check), `Templates` needs `Query\SeriesIndex` (`ttm-in-series`), `Cache\Purge` needs
  `Meta\PrimaryCategory`/`Query\SeriesIndex` (section + series URLs), `Rest` needs `Taxonomy\Series`
  (enum). All are downward. Recommend widening the column; the upward cases (B1–B3) are real.
- **SI-3** §5.4 has no `sections.technology_slug`; the lead rule (03 §7) hard-wires Technology. Add
  the key (R-task below adds it as a default).
- **SI-4** §4.3 and 04 §1 list `themes/ttm-theme/inc/template-hierarchy.php` ("compat, guarded");
  §6.4 puts all routing in the plugin's `Templates\Hierarchy`, and `ThemeAloneTest` passes without a
  theme-side file. Recommend dropping it from SPEC/04 §1 and CLAUDE.md.
- **SI-5** Rule 12 ("every front-end query is bounded … under `src/`") is applied to CLI-only code
  (`AuditCommand`, `SeriesIndex::rebuild` term listing) where `get_terms()` without `number` is the
  only sane way to enumerate every term. Recommend scoping rule 12 to request-time code.
- **SI-6** Rule 30's 25 KB budget could not hold the design's CSS (33 KB after Phase 6 with no
  minification allowed). The owner should decide the number once; until then it is retuned per C1.
- **SI-7** §6.10 says "anonymous GET requests"; HEAD must be included or `curl -I` checks are
  meaningless.

## Manual checks still owed (copied from HANDOFF.md §2)

**Phase 0**
- Open the site and confirm fonts render with zero requests to `fonts.googleapis.com`/`fonts.gstatic.com`.
- `http://localhost:8888/` after `npx wp-env start`: page renders in Archivo on `#f3f2f2` with no Google
  Fonts request; wp-admin shows no ttm-core/ttm-theme version-mismatch notice.

**Phase 1**
- Open a series term's edit screen in wp-admin and confirm fields/part list.
- Open a post in the block editor and confirm the sidebar panel renders with all fields.
- Publish a post missing a dek/alt text/series part and confirm the pre-publish panel lists warnings
  without blocking publish; check the Posts list columns.
- Settings → These Things Matter: save the General tab, confirm it round-trips; Books tab: add a row, save.
- After `npm run env:seed`: a seeded chapter's editor shows series, part "12 of 31", form Chapter;
  `term.php?taxonomy=series&tag_ID=<the-quiet-ledger>` shows all fields and the ordered part list;
  Posts list shows Primary section / Series / Words columns.

**Phase 2**
- Front page and an inner page at ≤720px: nav overlay opens/closes and closes on link tap; inner masthead
  nav is not overlaid at ≥721px.
- Deactivate/reactivate the theme on a fresh site: 7 categories/4 pages (with templates)/Sections nav
  appear; editor shows the 3 variations.
- Skip link/header landmarks/nav render; the 7 chrome patterns appear in the inserter.
- A post, its category archive, a 404 and search results: current-section highlighting and shells render.
- `/about/` at 1280 and 390 per the P2-07 log.

**Phase 3**
- `/` vs prototype badge 2a at 1280 and 3a at 390 (lead, verse box with `dailymedtoday.com` link,
  Technology span 2, Writing cell "The Quiet Ledger — Ch. 12", series strip, red poster, phone stacking).

**Phase 4**
- A seeded `hardening-wordpress` part vs 2b/3b; a non-series Technology post shows "← Previously in
  Technology"; a seeded journal post vs 2c.

**Phase 5**
- `/category/security/` vs 1e incl. `?tag=` narrowing; `/category/journal/` stream; `/tag/<seeded>/` and
  `/?s=cache`.

**Phase 6**
- `/series/` vs 1f; `/series/the-quiet-ledger/`; `/writing/` vs 2d (cover shadow only there);
  `/category/writing/` same layout.

**Phase 7**
- `curl -I http://localhost:8888/` shows the computed `Cache-Control` (note S7: this needs the HEAD fix
  first); `wp ttm verse fetch --force` logs a purge; poster shows the mailto button; switch provider to
  `mailto` and `none` and confirm F26.

**Phase 8**
- CI green on the branch including the e2e job and `playwright-report` artifact; run
  `docs/MIGRATION.md` §1–§2 against a real archive/WXR export in wp-env (audit CSV, migrate:politics,
  primary:assign, series:assign for boundless/cryptopals, recount, convert:export → convert-classic →
  convert:import, close-comments), opening the front page, `/category/technology/` and a journal post
  after each step.
