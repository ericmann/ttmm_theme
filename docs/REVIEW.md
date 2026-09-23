# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 1

**Verdict: CHANGES REQUESTED**

Reviewed `aa497b2..376c23c` (34 task commits plus the owner's mid-flight SPEC commit `376c23c`).
What I checked myself: `foundry_verify` (all 26 constraints clean, fixtures OK; every verify command green);
the full `npm run test:integration` (573/573 OK, run locally); GitHub CI on the head commit (all six jobs
green, including the `integration` job's new `env:drill` step); and mutation samples in six modules. Each
of these made its test fail and was then restored: `Text::truncate_sentences` terminator, `Html::photon_origin_url`
host check, `shortcodes.mjs` `[mfn]` alternation, `series-toc` series-variant fallback, `Stats` slug tiebreak,
`series-list` same-form filter, and `Form::on_save` story gate. The seeded-site work in phases 0, 1 and 5 is
solid and well tested. The live-content work in phases 2 to 4 has a real defect that the live suite was
loosened to let through, and `env:live` does not run end to end.

## Findings (most severe first)

### 1. Imported posts all get primary category "Uncategorized"; `--from-yoast` does nothing; the live suite was loosened to hide it (Tests / spec drift, P2-02, P4-04)
- `plugins/ttm-core/src/Meta/PrimaryCategory.php:117-137` (`on_save` on `save_post_post`) and `:146-150`
  (`id()` returns any stored value without checking it). The WordPress importer calls `wp_insert_post()` without
  categories, so core assigns the default category and `on_save` stores **Uncategorized**. The importer then
  replaces the categories with `wp_set_post_terms()`, which fires no `save_post`, so the stale meta stays.
  `PrimaryCommand::run()` (`src/Cli/PrimaryCommand.php:46-49`) skips every post that already has the meta, so
  `primary:assign --from-yoast` and the plain `primary:assign` both do nothing. LIVE-TRIAGE records this as
  "0 used, 0 skipped" on an export where 424 posts carry `_yoast_wpseo_primary_category`.
- Reproduced in wp-env with an importer-order probe (insert, then `wp_set_post_terms([security])`, then Yoast
  meta). Result: stored primary `uncategorized`, current categories `security`, `PrimaryCategory::slug()` =
  `uncategorized`, `--from-yoast --dry-run` → "Would use 0 post(s) from Yoast, skipped 0."
- The committed `docs/feedback/phase-4/live-front.png` shows the effect:
  - the lead's kicker reads "UNCATEGORIZED";
  - the Security and Opinion cells are empty;
  - the Journal rail shows 2014–2017 entries;
  - Cryptopals reads "Uncategorized · 9 parts".
  LIVE-TRIAGE's own P4-02 example (`/ext-turbovec-vector-search-php/` → `ttm-section-uncategorized`) is the
  same bug. Anything keyed on the primary category is wrong across the whole live import: masthead current
  item, "More in", prev/next labels, `single-journal` routing, front-page cells, and the Journal exclusion in
  `migrate:excerpts`.
- P4-04 relabelled the resulting live failures as "not a defect — test-only". It weakened
  `tests/e2e/live.spec.mjs:326-343` to check only that the kicker is non-empty, and dropped the masthead check
  on singles (`:268-288`), against SPEC §6.10.
- The unit/integration tests missed it because `PrimaryCommandTest` calls `delete_post_meta(
  'ttm_primary_category' )` before every case, so it never exercises the importer's order.
- **Fix:**
  - The primary category must not be persisted from an import-time save: gate `on_save` on `WP_IMPORTING`, with
    a filter as `Form::is_editor_save()` does.
  - `id()` and `on_save` must treat a stored term the post no longer has as empty.
  - `primary:assign` (both modes) must treat a stale stored value as missing.
  - Then restore the SPEC §6.10 live checks, compared against each post's *actual* primary category (from
    `screens.json`), not the section that picked it.

### 2. `npm run env:live` aborts at `convert-classic.mjs` on the real export (spec drift, P2-06/P3-03)
- `scripts/convert-classic.mjs:213-231` exits 1 when any post has `textEqual: false`. The final state has 7 such
  posts (LIVE-TRIAGE, owner cleanup).
- `scripts/live/plan.sh:63` runs it under `set -euo pipefail`, so the steps after it never run from the
  command: `convert:import`, `close-comments`, `series:rebuild`, `stats:flush`, `rewrite flush`, `verse fetch`,
  `audit` and `screens.mjs`.
- SPEC §1.3 "Done" and §6.6 require the whole plan "without manual intervention". The P3-03 counts
  ("724 converted, 7 [text-mismatch]") could only have come from running those steps by hand.
- **Fix:** mismatches are reported but do not abort the plan (a flag `plan.sh` passes). `convert:import` leaves
  a `textEqual:false` record unconverted (the post stays classic and is listed; no backup is written) so the
  7 posts cannot lose text.
- Same area: `ConvertCommand::footnotes_verified()` (`src/Cli/ConvertCommand.php:266-310`) relaxed SPEC §6.8's
  "exactly once" to "at least once", so a doubled footnotes list would pass. Keep the relaxation's reason (a
  note's text can also appear in the body) by counting exactly once inside the rendered `core/footnotes` list
  only.

### 3. `migration.image_hosts` defaults to `[]` instead of the SPEC §5 host list, so `migrate:images` in `plan.sh` rewrites nothing (spec drift, P2-04)
- `plugins/ttm-core/src/Config.php:104` has `'migration.image_hosts' => []`. SPEC §5 fixes the default as
  `[eamann.com, www.eamann.com, ttmm.io, www.ttmm.io, ttmm.wpengine.com, i0.wp.com, i1.wp.com, i2.wp.com]`.
- `plan.sh` runs `wp ttm migrate:images` with no `--hosts`, so every run logs "0 rewritten". The audit's
  `remote-image` count stays at 113, exactly the export's profile. SPEC §6.10 expects cross-origin images to be
  "zero after `migrate:images`", apart from dead links.
- `docs/MIGRATION.md §2.5a` and `docs/05-plugin-spec.md §10` document `--hosts` as *required*, which
  contradicts SPEC.
- `docs/MIGRATION.md §2.1`'s audit flag list also lacks the five new flags.

### 4. SPEC §6.1.1 (owner request, commit `376c23c`) is not implemented (spec drift; new goal of this flight)
- `plugins/ttm-core/blocks/verse-of-the-day/render.php:68` still prints "Meditation for %1$s from %2$s".
- `docs/03-content-model.md:69` and `docs/06-fallbacks.md` F6 still describe the dated string.
- The `verse-attr` fidelity row (`tests/e2e/fidelity.spec.mjs:269`) asserts no text.
- `VerseOfTheDayTest.php:99,118-119` assert the dated text.

### 5. F28 `/writing/` archive ignores `archive.per_page` (spec drift, PLAN Decision "F28 routing", P1-04)
- `Templates\Hierarchy::route_writing_page()` (`src/Templates/Hierarchy.php:62-87`) never sets
  `posts_per_page`.
- `Query\Archive::shape()` is registered earlier (`Plugin::modules()`), so it runs first while the query is
  still a page query. `/writing/` therefore pages at the site's `posts_per_page` option (10), while
  `/category/writing/` pages at 12.
- PLAN names `posts_per_page = archive.per_page` explicitly. `HierarchyTest` does not assert it.

### 6. The rule 47 private-data check misses files directly under `docs/` (constraint, P0-01)
- `scripts/forbidden-patterns.sh:118` uses `git ls-files -- 'docs/**/*.sql' 'docs/**/*.sql.gz'
  'docs/**/*.tar.gz' 'docs/**/*.csv'`. In non-glob pathspecs, `docs/**/x` needs a `/` after `docs/`.
- Tested in a scratch repo: `docs/dump.sql`, `docs/x.csv`, `docs/b.tar.gz` and `docs/e.sql.gz` are **not**
  reported; `docs/a/c.sql` is. Only `*.xml` has a top-level pattern.
- The rule is enforced by the script, not a `foundry.json` constraint, so the fix adds a fixture test of its
  own. The tree is currently clean.

### 7. `Seeder::reset()` deletes only `post`/`page`/`attachment` (spec drift, low; PLAN Decision "Seeder::reset() from a live state", P2-01)
- `src/Cli/Seeder.php:~875` loops over `[ 'post', 'page', 'attachment' ]`. PLAN says "every remaining post of
  every post type".
- After a live import, `feedback`, `custom_css`, `wp_block`, `wp_navigation`, `nav_menu_item`, `wp_template`
  and `wp_global_styles` rows survive `seed --reset`, so rule 48's "back to the seed" is not met.
- They render nothing today, which is why this is low severity.
- `SeedStatesTest::test_reset_removes_foreign_posts_terms_and_attachments_when_present` covers only a post and
  an attachment.

### 8. LIVE-TRIAGE misclassifies two findings and leaves one count unexplained (tests / rule 52, P4-04/P4-05/P3-03)
- The "kicker/masthead … not a defect — test-only" row is finding 1.
- "`audit --only=shortcode`: 51 posts … correctly converted but still literally present" contradicts itself.
  51 is exactly the export's `[caption]` 41 + `[audio]` 8 + `[seoslides]` 2. I ran `convert-classic.mjs` on
  synthetic `[caption]`/`[audio src]` input and both convert correctly, so the 51 need a per-name breakdown
  from the audit's `detail.shortcodes`, then real classification.
- The live screenshots show the bug from finding 1 and must be retaken after the fix.

## Spec issues
- SPEC §6.11 `single-other` expects count 4, but the seed has only 3 other nonfiction series. PLAN spec issue 1
  (asserts 3) is the right reading; SPEC should say 3, or the seed needs a fifth nonfiction series.
- SPEC §5 calls `excerpt_length` "existing", but it did not exist before this flight (PLAN spec issue 2).
- SPEC §6.7 Photon rewrite: `import.sh` runs `search-replace` before `migrate:images`, so on the rehearsal and
  on `beta.mann.blog` Photon URLs are rewritten to `https://eric.mann.blog/...`. That is still cross-origin
  until cut-over. SPEC may want the rewrite target to be the current `home_url()` host.
- SPEC §6.10's "kicker text equals the primary category name" does not hold for multi-category posts, because
  the kicker is `core/post-terms` (all categories, primary first). The check should read "the kicker's first
  term".

## Manual checks still owed (from HANDOFF.md)
1. (P0-07) NOT VERIFIED (human): compare `docs/feedback/phase-4/archive-business.png` with mock `1e` line 933.
2. (P1-06) NOT VERIFIED (human): open `/transients-object-caches-and-fast-enough/`,
   `/series/hardening-wordpress/` and `/` on the seeded site; compare with mocks `2b`, `1f`, `2a` line 327.
3. (P4-05) NOT VERIFIED (human): the owner reads LIVE-TRIAGE.md and opens `/`, `/keeping-fresh/`,
   `/category/technology/`, `/writing/`, `/series/` on the live import. Re-owed after R1 fixes: the current
   live PNGs show finding 1.
4. (P5-04) NOT VERIFIED (human): compare every `docs/feedback/phase-4/*.png` with its mock; CI green including
   the drill (verified green on `8d11f74` by this review); `git stash list` (empty at review time).

## Notes
- F27 leaves an empty `.ttm-series-single__other` group (24px/48px padding) when the block returns `''`. No
  seeded or live screen reaches it, and S6 forbids `:has()`. Worth a look if blank space ever shows.
- `CLAUDE.md` Commands still says `ALLOW_TAGGED = true` during the flight; the code is `false`, which is correct.
- The P3-03 CI run's only failure was `editor-customizer` (all 19 blocks listed as unregistered once). It passed
  on the next run, so it looks flaky rather than a regression.
- `Seeder::reset()`'s `get_terms()` has no `number`, so it is unbounded (CLI-only, never on a request).
- `ConvertCommand::footnotes_verified()` redeclares `$post` as a global after using it as a local
  (the PHPCS warning at line 324); readable, but confusing.
- The axe `.ttm-entry` exclusion and the main-frame-only network rule in `live.spec.mjs` are reasonable readings
  of §6.10: the content residue is listed in LIVE-TRIAGE rather than hidden.
