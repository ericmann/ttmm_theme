# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 2

**Verdict: CHANGES REQUESTED**

Reviewed `aa497b2..a626d69`. Round 1 covered the whole branch through `376c23c`, so this round reads the nine
R1-* task commits in full (`6c0e9ae`..`c7821b8`) and re-checks the branch-wide gates. I checked these myself:

- `foundry_verify`: all 26 constraints clean, no fixture failures. `npm run lint`, `npm run test:unit`,
  `npm run build` and `forbidden-patterns.sh` are green.
- `composer lint` and `composer test:unit`: `foundry_verify` reports both as failed. The cause is a host shim:
  `~/.local/bin/php` routes to another project's container (feedback logged). Re-run with `/usr/bin/php`:
  PHPCS 0 errors, PHPUnit 184/184 OK.
- `npm run test:integration`: 581/581 OK, run locally.
- GitHub CI on `a626d69`: the pull_request run is green. The push run failed only in Playwright's
  `env:start`, with `ERR_SOCKET_CLOSED_BEFORE_CONNECTION` (infrastructure, not a test failure).
- Mutation samples. Each made its test fail and was then restored:
  - `PrimaryCategory::id()` stale check → `PrimaryCategoryTest`.
  - `Hierarchy::route_writing_page()` `posts_per_page` → `HierarchyTest`.
  - `ConvertCommand::footnotes_verified()` exactly-once → `ConvertCommandTest`.
  - `summarize.mjs` `allowTextMismatch` → `convert-classic.test.js`.
  - `check-private-data.sh` top-level `docs/*.sql` pathspec → `private-data.test.js`.
- Probes:
  - I re-ran `scripts/convert-classic.mjs` on the gitignored `docs/fixtures/live/classic.ndjson`, output to the
    scratchpad.
  - I parsed the WXR's category term map against its Yoast meta (counts only).
  - I ran one throwaway politics-only post through `migrate:politics` and `PrimaryCategory::slug()` on the dev
    site, then deleted it.

R1-01..R1-08 all do what they claim, and their tests would catch a regression. But the "proven on real content"
claim still does not hold, for two reasons:
- The conversion that R1-09 touched merges paragraphs in most classic posts.
- The live screenshots committed in R1-09 show an empty Opinion cell that nobody investigated.

## Findings (most severe first)

### 1. Classic conversion merges paragraphs in 553 of 724 posts (tests / spec drift; P3-01/P3-03, R1-09)
- `scripts/convert-classic.mjs:146` (`convertPost`) and `scripts/lib/autop.mjs:27-43`. Classic `post_content` stores
  paragraphs as blank-line-separated text with no `<p>`; WordPress adds the `<p>` at render time with `wpautop`.
  `rawHandler({ HTML })` does not run autop. Its `normaliseBlocks` puts a whole run of text between block-level
  elements into **one** `<p>`.
- R1-09 found this mechanism and added `autoParagraphPlainText()`, but only for posts with *no* block-level tag
  anywhere. Almost every real post has at least one `<h2>`, `<ul>`, `<blockquote>` or `<pre>`, so almost every
  post is still affected.
- Measured on current code against the real export: 553 of 724 converted posts have `core/paragraph` blocks
  with an internal blank line. That is 2,086 of 4,223 paragraph blocks, each holding two or more source
  paragraphs. The committed `docs/feedback/phase-4/live-article-classic.png` shows it: "Code Review" and "Self
  Study" are each one wall of text.
- Swapping in `autop()` from `@wordpress/autop` fixes it:
  - `@wordpress/autop` is already in `package-lock.json`.
  - The block parser itself runs the same `autop()` on `core/freeform` content before an editor "Convert to blocks".
  - After the swap, merged posts go from 553 to 5 and paragraph blocks from 4,223 to 11,916.
  - `textEqual:false` posts go from 7 to 3.
- Why nothing caught it:
  - `textEqual` compares whitespace-collapsed text, so merged paragraphs pass.
  - Every `rawHandler` Jest test is `maybeIt`-skipped (spike Outcome B, "6 skipped"). That includes R1-09's own
    "bare-URL `[audio]` becomes a `core/audio` block" acceptance test, which has never run anywhere.
- **Fix:**
  - Run a real autop (`@wordpress/autop`) on every classic post. Order: after the shortcode pre-pass (so `[cc]`
    code bodies are already `<pre>`, which autop leaves alone), before footnotes and `rawHandler`.
  - Add a paragraph-merge signal to the report (`report.mergedParagraphs`: paragraph blocks whose content has a
    blank line) and fail on it in `summarizeResults` unless an explicit flag allows it.
  - `live.spec.mjs`: on classic-converted screens, no `.ttm-entry p` whose `innerHTML` contains a blank line.
  - Pure Jest tests that actually run (not `maybeIt`): a heading plus blank-line paragraphs gives two `<p>`, and a
    multi-line `<pre>` gets no `<br>`/`<p>`.

### 2. `migrate:politics` does nothing on the live import, so 24 Politics posts get primary "Politics" and the front-page Opinion cell is empty (spec drift; P2-01/P2-06, rule 52)
- `seed --starter-only` (`Seeder::run_starter()` → `seed_categories()`, `categories.json:9`) creates `politics` as
  a child of `opinion` before the import. The importer then attaches the imported Politics posts to that existing
  term.
- `MigrateCommand::politics_child()` (`src/Cli/MigrateCommand.php:~487`) returns "Politics is already a child of
  Opinion; nothing to do." whenever the parent relation exists. It therefore never adds Opinion to the posts and
  never sets their primary. LIVE-TRIAGE logs exactly that message on both runs.
- `PrimaryCategory::resolve_from_terms()` then resolves a politics-only post to `politics`. Reproduced in wp-env:
  `migrate:politics` → "already a child"; `PrimaryCategory::slug()` = `politics`; categories = `politics`.
- The effect is visible in the committed `live-front.png`:
  - the Opinion cell renders its heading and no rows, because the cells query primary = Opinion;
  - "Community Support · Politics" sits in Faith;
  - Politics singles get a "Politics" kicker and no current masthead item.
- The round 1 reviewer saw the empty Opinion cell and blamed finding 1. It outlived that fix.
- **Fix:** make `politics_child()` idempotent per post, not per term relation. When Politics is already under
  Opinion, still add Opinion to any Politics post lacking it and set its primary to Opinion, and report the count.

### 3. `primary:assign --from-yoast` uses 3 of 175 Yoast values; the "structural limitation" reading is wrong (interpretation / spec drift; R1-09, SPEC §6.7, §9 Q2)
- HANDOFF/LIVE-TRIAGE call the stale Yoast term IDs unfixable "without guessing". But the WXR carries the
  source term map itself: every `<wp:category>` has `<wp:term_id>` and `<wp:category_nicename>`. For example,
  source term 8 is `technology` and 14 is `security`.
- Mapping through it resolves 110 of the 153 non-empty Yoast values to a category the post carries. 26 of those
  are multi-category posts where Yoast's primary **differs** from nav order. Those 26 posts currently get the
  wrong section, kicker, masthead item and front-page cell, and possibly the wrong Journal routing.
- SPEC §1.2 lists Yoast primary as "meta worth using", and Q2 decides "Yoast primary when present and valid".
- **Fix:**
  - `import.sh`/`plan.sh` extracts the WXR's category `term_id → slug` map into a gitignored JSON file under the
    live fixtures dir (container-visible, like the ndjson files).
  - It passes `primary:assign --from-yoast --term-map=<path>`.
  - `PrimaryCommand` translates the Yoast ID through the map to the current term (still only if the post carries
    it). Without `--term-map`, behaviour is unchanged.
  - Correct the MIGRATION.md/LIVE-TRIAGE text.

### 4. `single-journal.html` gained 28px above the body; the new `jr-entry` row enshrines the deviation from mock `2c` (spec drift / tests; R1-09)
- Adding `className: "ttm-entry"` to the Journal `post-content` (`themes/ttm-theme/templates/single-journal.html:25`)
  is right for identity. The axe exclusion and the §6.10 `.ttm-entry` check both need it.
- But `.ttm-entry { padding-top: 28px }` (`ttm.css:536`) is F12's *article* byline-to-body gap. Mock `2c`
  (`Eric Mann Newspaper.dc.html:431-432`) has `h1 margin 0 0 18px` followed directly by the body, with no padding.
  Journal posts now show a 46px title-to-body gap.
- `tests/e2e/fidelity.spec.mjs` `jr-entry` asserts `padding-top: 28px`, which locks in the wrong value.
- **Fix:** add `.ttm-journal-head .ttm-entry { padding-top: 0 }` (one comment-headed rule in the Journal component
  section). `jr-entry` asserts count 1 and `padding-top: 0px`, plus the title-to-body gap = 18px (bounding boxes).

## Spec issues
- Carried from round 1, still open:
  - §6.11 `single-other` count 4 vs the seed's 3 (PLAN spec issue 1).
  - §5 calls `excerpt_length` "existing".
  - §6.7 Photon target is `eric.mann.blog` rather than the current host during rehearsal.
  - §6.10's "kicker equals the primary category" should read "the kicker's first term"; R1-02 implemented that
    reading.
- §6.8 "footnote texts appear … exactly once each" is ambiguous when two notes have identical text (e.g. two
  "Ibid." notes). R1-03's exactly-once check flags those as `[footnotes-mismatch]`, which is a report note, not an
  abort. SPEC could say "each note appears once in the list".
- `docs/01-design-language.md:166` still describes the dated verse attribution. SPEC §6.1.1 named only 03 and
  06, so this is not a finding, but the design doc now contradicts SPEC.

## Manual checks still owed (from HANDOFF.md)
1. (P0-07) NOT VERIFIED (human): compare `docs/feedback/phase-4/archive-business.png` with mock `1e` line 933.
2. (P1-06) NOT VERIFIED (human): open `/transients-object-caches-and-fast-enough/`, `/series/hardening-wordpress/`
   and `/` on the seeded site; compare with mocks `2b`, `1f`, `2a` line 327.
3. (P4-05) NOT VERIFIED (human): the owner reads LIVE-TRIAGE.md and opens `/`, `/keeping-fresh/`,
   `/category/technology/`, `/writing/` and `/series/` on the live import. Re-owed after the round 2 fixes:
   `live-article-classic.png` shows finding 1, and `live-front.png` shows finding 2.
4. (P5-04) NOT VERIFIED (human): compare every `docs/feedback/phase-4/*.png` with its mock; CI green including the
   drill; `git stash list` (empty at review time).
5. (R1-09) NOT VERIFIED (human): open `live-front.png` and `/`, a Journal post and `/category/security/` on the live
   import. Kickers and section cells should show real categories, never Uncategorized. Uncategorized is fixed;
   the empty Opinion cell is finding 2.
6. (R1-09) `character-quest-service` and `hyper-vvv-windows`: owner content pass. Finding 1's fix should resolve
   the first; re-check it.

## Notes
- `tests/unit/Meta/PrimaryCategoryTest.php:110` `define( 'WP_IMPORTING', true )` leaks into every later unit test
  in the process. Nothing else in the unit suite reads it today, but `Form::is_editor_save()` does. Forcing the
  answer through the `ttm_primary_on_import` filter would be cleaner.
- `Config.php:104-105`'s comment still says `migration.image_hosts` is "empty until then".
- `docs/03-content-model.md:69` gives its own rationale for the undated attribution ("the source never reliably
  surfaces the date"). SPEC's reason is that the date belongs to the cache boundary. The text is harmless, but it
  is not SPEC's reason.
- `Seeder::reset()` misses spam/trash comments (the `get_comments()` default status). Its post loop has no
  progress guard if `wp_delete_post()` is ever vetoed. It is CLI-only and dev-only.
- `scripts/test/private-data.test.js` leaves its temp repos in `os.tmpdir()`.
- Round 1's note about the empty `.ttm-series-single__other` group under F27 still stands. No seeded or live
  screen reaches it.
