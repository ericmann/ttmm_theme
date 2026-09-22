# Review — phase 3 (inner-template fidelity)

Round: 1

Branch `refine/2026-09-22`, base `main` (`8c2b228`), head `5498214`. 41 tasks, all `[x]`;
0 blocked, 0 skipped. Reviewed every task commit against `docs/PLAN.md` and the SPEC/design
sections it cites, plus the one out-of-sequence commit `dd4dfbe`.

## Verdict

**CHANGES REQUESTED** — 10 fix tasks.

Category 1 (constraints) is not clean: `themes/ttm-theme/templates/page-writing.html` carries two
`layout: constrained` groups inside an `is-style-grid-*` grid group, which SPEC §3.1 rule 36
(extended this flight), CLAUDE.md `## Constraints` and PLAN Conventions all forbid, and which
SPEC §1 named as one of the six defects this flight exists to fix. Category 3 (tests) is not
clean either: the search template ships a user-visible functional regression that no test covers,
and three of the flight's own headline fixes — the `wpautop` `<p>` fix at e2e level, the
archive-aside label case, the most-read numbering — are not guarded by any assertion that would
fail if they were reverted.

This is otherwise a strong flight. Everything I could check mechanically and independently held
up, and much of the work is better than the log claims.

### What I verified myself

- **Suites, run by me, not read from the log.** `foundry_verify` green on all six commands
  (`composer lint`, `composer test:unit` 166/166, `npm run lint` — budget 61439/61440, coverage
  195 markup / 195 css / 0 pending, `check-fixme: clean` — `npm run test:unit` 38 passed,
  `npm run build`, `forbidden-patterns: clean`). `npm run test:integration`: **480/480, 1926
  assertions**. `npm run test:e2e`: **432 passed, 0 skipped, 0 flaky.** All match P5-05's claims.
- **Mechanical constraint sweep**: theme data APIs, plugin inline styles, nonces in cacheable
  output, per-visitor markup, clock reads outside `Support/Clock.php`, unbounded queries,
  dangerous PHP, front-end network calls, `wp_safe_remote_*` call sites, hex literals in
  `ttm.css`, `prefers-color-scheme`, `dependencies: {}`, `npm audit --audit-level=high`
  (0 vulnerabilities), rule 45 (`210, 48, 19` absent), rule 39 (no lorem), `FOUNDRY_FEEDBACK.md`
  untouched, commits unsigned as intended — **all clean**. `functions.php:32`'s
  `wp_enqueue_style` on `wp_enqueue_scripts` is **not** a violation: phase-1 rule 2 scopes that
  prohibition to `plugins/ttm-core/`. CLAUDE.md's abbreviation drops the scope (see Spec issues).
- **§6.9 transcription completeness**: I diffed all 187 SPEC §6.9 row ids against the test names
  in `tests/e2e/fidelity.spec.mjs`. **Every row has a real test.** Only `a11y`, `network` and
  `selectors` are absent as single rows, and those are per-screen loops
  (`fidelity.spec.mjs:2817-2900`) plus `selectors.spec.mjs`, exactly as PLAN specifies. No
  `test.skip`, `test.only` or `test.fail` anywhere in the e2e suites.
- **Mutation sampling, one per module** (each restored with `git checkout --` afterwards; working
  tree confirmed clean and `foundry_verify` re-run green):
  - *Integration* — removed the `'raw'` argument from
    `plugins/ttm-core/blocks/series-featured/render.php:134`. Output became
    `<p class="ttm-series-featured__dek">&lt;p&gt;Six parts on hardening a WordPress
    install.&lt;/p&gt;</p>` and `SeriesFeaturedTest::test_dek_is_plain_text_not_wpautop_wrapped`
    **failed** (480 tests, 1 failure). Real guard.
  - *PHP unit* — made `Helpers::link_rows()` return early.
    `HelpersTest::test_link_rows_turns_row_group_into_anchor` **failed**. Real guard.
  - *JS / scripts* — inverted `taggedFixmeHits()`'s condition in `scripts/lib/fixme.mjs`.
    `check-fixme.test.js` **failed** on two cases. Real guard.

### Operator-flagged items — both confirmed, with one caveat

**(1) Term descriptions no longer render literal `<p>`.** Confirmed on all three pages, from the
committed screenshots rendered against the seeded site:
`docs/feedback/phase-3/writing.png` (synopsis), `series-hub.png` (featured dek) and
`series-single.png` (`/series/hardening-wordpress/`) all render clean prose. Both blocks read the
`'raw'` context (`serial-hero/render.php:47`, `series-featured/render.php:134`); I swept the whole
plugin and there is no other `get_term_field()` call, no `wpautop`/`term_description` use, and the
only other description read (`series-list/render.php:128`) uses the raw `$term->description`
property. **Caveat on the guard**: it is an *integration* test, not a fidelity row — and it is a
genuine one (mutation-proven above). But `wr-synopsis` (`fidelity.spec.mjs:1746`) and `hub-dek`
(`:2550`) assert only font-size, colour and max-width; neither reads text, so **deleting `'raw'`
leaves both e2e rows green**. SPEC §6.9's own rows specify only style properties, so there is
nothing to "tighten" — an assertion must be added. That is task R1-05.

**(2) The archive aside headings match mock 1e.** Confirmed. `dd4dfbe` fully reverted the scoped
`.ttm-archive-body aside .ttm-cell-heading__label { text-transform: none }` override, and nothing
else in `ttm.css` overrides that label. Both headings render through the shared
`.ttm-cell-heading.is-rail > .ttm-cell-heading__label` rule (`ttm.css:918-924`: 12px / 800 /
uppercase / .08em). `docs/feedback/phase-3/archive-security.png` shows "SERIES IN SECURITY" and
"MOST READ" uppercase, with plain `1` / `2` / `3`. **Mock 1e agrees**: both headings are `<h6>`
(`docs/Eric Mann Newspaper.dc.html:958,964`) and the mock's design system sets
`h6 { letter-spacing: 0.08em; text-transform: uppercase }`
(`docs/_ds/modernist-.../styles.css:92-93`); its most-read numbers are literal `1`/`2`/`3`. So
P3-04's original "reads as a sentence here" justification was wrong and `dd4dfbe` is right.
**Caveat**: neither half of `dd4dfbe` is tested. `ar-aside-series` asserts `textContent`, which is
unaffected by `text-transform`, so re-adding the override would be green; and no assertion
anywhere covers the numbering — `MostReadTest` counts rows only, `ar-mostread-num` asserts colour
and weight only. Task R1-05.

---

## Findings

Most severe first. Category in brackets; task ID is the task that owns the fix.

### 1. [Tests / spec drift] Search result rows are not links at all — R1-01 (owner: P3-05)

`themes/ttm-theme/templates/search.html:34` places
`<!-- wp:post-terms {"term":"category","className":"is-style-kicker"} /-->` inside the row group,
**before** the title. `core/post-terms` always renders a linked term, so the row content contains
an `<a>`, which trips the rule-33 nested-anchor guard at
`plugins/ttm-core/src/Blocks/Helpers.php:80`:

```php
if ( false !== stripos( $block_content, '<a ' ) ) {
    return $block_content;
}
```

`link_rows()` bails, and the row's `core/post-title` is `"isLink":false`. I confirmed this against
the running seeded site — `curl http://localhost:8888/?s=ledger` returns:

```html
<div class="wp-block-group ttm-archive-row is-layout-flow wp-block-group-is-layout-flow">
    <div class="taxonomy-category is-style-kicker wp-block-post-terms"><a href="…/category/writing/" rel="tag">Writing</a></div>
    <h3 class="ttm-archive-row__title wp-block-post-title">The Quiet Ledger, Chapter 12: Reconciliation</h3>
```

**What breaks:** on `/?s=…` the headline and the row go nowhere; the only clickable thing in a
search result is the category chip. The same fetch against `/category/security/`,
`/category/journal/` and `/tag/wordpress/` returns `<a href="…" class="wp-block-group
ttm-archive-row …">`, so this is specific to search. It violates SPEC §6.6 "Search" ("rows as
archive rows plus the matched section as a kicker line") and CLAUDE.md's "every whole-row link is
one `<a>` with the headline first in its text" — even if the anchor fired, the kicker precedes the
headline, so the accessible name would begin "Writing".

**Minimal fix:** render the search kicker as a bound plain-text paragraph (a `ttm/*` binding, as
`ttm/archive-kind` and `ttm/section-label` already do) rather than `core/post-terms`, and move it
after the title in the DOM — `ttm.css` already places it with `grid-row: 1`.

**No test catches it:** `search-row-kicker` asserts font-size and colour only; `ar-row`'s
`tagName === 'A'` assertion runs on the security archive only.

### 2. [Constraint — rule 36 extended] `layout: constrained` inside a grid group — R1-02 (owner: P4-06)

`themes/ttm-theme/templates/page-writing.html:41` and `:59`:

```
<!-- wp:group {"className":"ttm-writing-body__stories","layout":{"type":"constrained"}} -->
<!-- wp:group {"className":"ttm-writing-body__books","layout":{"type":"constrained"}} -->
```

Both are nested inside the `is-style-grid-7-5 ttm-writing-body` grid group at `:9`. CLAUDE.md
`## Constraints`: *"no `core/group` with `is-style-grid-*` uses `constrained`/`flow`, and no
`layout: constrained` group sits inside one (rule 36 extended)."* SPEC §3.1 rule 36 and PLAN
Conventions (line 52) say the same. SPEC §1 lists "Constrained wrappers inside grids" as one of
the six diagnosed defects this flight exists to fix, so these are in scope even though they
pre-date the branch; P4-06 edited this exact file and added `__serials`/`__chapters` at
`layout: default` correctly, but left these two.

I swept every template, part and pattern with a nesting-aware parser; these are the only two
`is-style-grid-*` cases. `themes/ttm-theme/patterns/stat-row.php:16,27,38` has the same shape
against a core `layout: grid` group (`.ttm-stats`) — a weaker reading of the rule's literal text,
but the same hazard, and it should be fixed in the same pass.

**What breaks:** core emits `.is-layout-constrained > * { max-width: <content-size>;
margin-inline: auto }` on the group's children. At ≤1024 `ttm.css:3130-3133` makes the `aside`
`display: contents`, so these two become direct grid items still carrying core's constrained
rules, competing with the `ttm.css`-owned track sizing — exactly the class of bug rule 36 exists
to prevent.

**Minimal fix:** `"layout":{"type":"default"}` on both (and the matching `<div class=…>` lines).

**No test catches it:** `art-row-layout` (`fidelity.spec.mjs:1089`) checks only
`.ttm-article > *`, on the article screen only. No equivalent row exists for `.ttm-writing-body`.

### 3. [Spec drift / dead code] The ≤720 series part-row override is dead — R1-03 (owner: P4-01, missed by P5-01)

`themes/ttm-theme/assets/css/ttm.css:2697`, inside `@media (max-width: 720px)`:

```css
.ttm-series-featured__part { grid-template-columns: 28px 1fr; }
```

The unscoped base rule sits **later** in the file, at `ttm.css:3012`:

```css
.ttm-series-featured__part { display: grid; grid-template-columns: 40px 1fr auto; … }
```

Equal specificity (0,1,0); a media query adds none, so source order wins at every viewport. The
phone override never applies. SPEC §6.7: *"≤ 720: H1 44; part rows `28px 1fr` with the date on a
second line."* `/series/` and `/series/hardening-wordpress/` therefore render the desktop
three-track row at 390.

This is precisely the bug class P5-01 documented as a spec issue and claimed to have swept for
(its interpretation #3, for `.ttm-series-single .ttm-series-featured__title`), and PLAN P5-01's
own 390 checklist lists "hub: featured stacked, part rows `28px 1fr`" as walked.

**Minimal fix:** move the `@media (max-width: 720px)` block at `ttm.css:2687-2704` to after
`.ttm-series-featured__date` (ends `:3051`).

**No test catches it:** `hub-phone` (`:2707`) asserts only `.ttm-series-featured` track count and
the `h1` font-size.

### 4. [Spec drift] Archive row dates repeat the year inside a year group — R1-04 (owner: P3-03)

`ttm/short-date` resolves to `Support\Dates::short()`
(`plugins/ttm-core/src/Support/Dates.php:36-44`), which appends `, {Y}` when the year differs from
"now". In a **year-grouped** archive the year is already the 120px group label, so
`/category/security/` renders "Nov 26, 2025" inside a 72px date column, wrapping to two lines and
dropping the row's title off the group's baseline. This is plainly visible in the committed
`docs/feedback/phase-3/archive-security.png` (2025 group).

SPEC §6.6 specifies the archive row date as `12px neutral-700 tnum padding-top 4 ("Sept 10")` in a
`72px 1fr` grid; mock 1e (`docs/Eric Mann Newspaper.dc.html:945`) shows no year.

**Minimal fix:** give the year-grouped rows a year-less format (a `ttm/short-date` arg, e.g.
`{"noYear":true}`, used by `category.html` and `archive.html`), leaving the journal stream and
search rows — which are not year-grouped — on the current behaviour.

**No test catches it:** `ar-row-date` asserts font-size, colour and padding-top only.

Folded into the same task: the serial meta line capitalises cadence
(`plugins/ttm-core/blocks/series-list/render.php:174-177` → "Novel · literary thriller ·
Monthly"), where SPEC §6.5 writes it lowercase and PLAN's spec-issue #18 says the capitalisation
belongs only to the hero stat. `wr-serial-form`'s regex carries SPEC's own `/i` flag, so nothing
can distinguish.

### 5. [Tests] The flight's own headline fixes are unguarded — R1-05 (owners: P5-02, dd4dfbe, P4-02)

Four assertions that would not fail if the mechanic were removed:

- `tests/e2e/fidelity.spec.mjs:1746` (`wr-synopsis`) and `:2550` (`hub-dek`) never read text, so
  removing `'raw'` from either render leaves them green. Add
  `expect( await el.innerHTML() ).not.toContain( '<p>' )`.
- `:2282` (`ar-aside-series`) asserts `textContent`, which `text-transform` does not affect.
  Sibling rows that use the same idiom (`cell-head:455`, `writing-head:629`, `:1107`, `:1288`)
  *pair* it with `expect( await computed( el, 'text-transform' ) ).toBe( 'uppercase' )`; this one
  does not. Add that line.
- `tests/integration/Blocks/MostReadTest.php` never asserts the number text, so reverting
  `plugins/ttm-core/blocks/most-read/render.php:75` to `sprintf( '%02d', … )` passes everything.
  Add `assertStringContainsString( '<span class="ttm-numbered__num tnum">1</span>', $html )` and
  `assertStringNotContainsString( '>01<', $html )`.
- `tests/integration/Blocks/SeriesListTest.php:266` asserts a single category
  (`'ttm-series-row__categories">Technology<'`), so **nothing** tests that `grid-2` joins
  categories with " · "; `hub-grid-cats` (`fidelity.spec.mjs:2698`) is
  `expect( t.includes( ' · ' ) || t.length > 0 ).toBe( true )`, where the second disjunct makes
  the first unreachable (it mirrors SPEC §6.9's own loose wording, so this is not an implementer
  loosening — but it leaves the hole).

### 6. [Spec drift] Declarations named in SPEC/PLAN were dropped under budget pressure — R1-06 (owners: P4-04, P4-05, P5-03)

`ttm.css` is **61439 of 61440 bytes — one byte of headroom.** `HANDOFF.md` concedes some drops and
argues the budget is correctly sized; I disagree on both counts.

The disclosure is incomplete and self-contradictory. `HANDOFF.md:196-202` says headroom was
"managed each time by losslessly shortening existing comments **rather than dropping real
declarations**"; `HANDOFF.md:203-209` then lists drops. Items missing from that list:

| SPEC / PLAN | Shipped | Where |
|---|---|---|
| hero grid `280px minmax(0,1fr)` | `280px 1fr` | `ttm.css:3059` — this is what forced P5-01's `.ttm-serial-hero__body { min-width: 0 }` band-aid; ≥721 is still unguarded |
| kicker `margin 0 0 12` | `margin-bottom: 10px` | `:3067` — added by P5-02 only to satisfy the coverage lint, at the wrong value |
| title `margin 0 0 16 −0.04em` | absent | `:3071` |
| synopsis `19px/**1.45**` … `margin 0 0 20` | line-height and margin absent | `:3075` |
| buttons `gap 10` … `margin 0 0 22` | `gap: 12px`, `margin: 12px 0` | `:3085` |
| ≤720 "stats 3-across at **16px values**" | no `.ttm-stats__value` rule in the ≤720 block | `:3159-3186` |
| chapters `__dek` 13px neutral-800, `__date` 12px neutral-700 tnum, `__title` line-height 1.2, row `align-items: baseline` | all inherit `.ttm-numbered__row`'s 14px/600/1.35 | `:2796-2818` |
| list-row mark/dek/meta margins 6/4/6 | 5/5/4 | `:1613, :1717, :1684` |
| `__count` "12px neutral-700 tnum" | no colour, no `font-feature-settings` | `:1745` |

P5-03 also *lost* ground: it entered at 61410 (30 free) and left at 61439 (1 free) — the
media-query merge saved ~22 bytes but an added `stylelint-disable-next-line` comment cost ~47. Its
"no duplication to reclaim" claim is nonetheless **correct** — I scanned independently and found
zero duplicate declarations; every repeated selector is a legitimate base-plus-override pair. What
the scan *should* have surfaced is finding 3.

At 0.0016% headroom any single-character CSS addition fails `npm run lint`, which sits inside
`verify` and therefore blocks every subsequent task. The constraint has already cost real
declarations and degraded comments to fragments (`ttm.css:2283` `/* 3b: k11,H1 34,dek17,b12 */`,
`:937` `/* §6.1.5: 12,not11. */`, `:46` `/* Rule 42: 1280, gutter0 */`). PLAN's own "round up to
the next 1024" reads naturally as 62464.

**Minimal fix:** raise `cssBudgetBytes` to 62464 in `scripts/check-budget.mjs` and `CLAUDE.md`,
restore the declarations above, and add a fidelity row per restored value so they cannot silently
vanish again.

### 7. [Tests] Seed fixture drift and a tautological seed test — R1-07 (owner: P0-08)

- `docs/fixtures/seed/books.json` holds **three** books (`The Quiet Ledger`, `Salt Water Wires`,
  `Eleven Small Doors`). SPEC §6.10 and PLAN P0-08 both say two; mock 2d's "In print" grid shows
  those two. The Writing page's `wr-books`/`wr-book-*` rows therefore render a book the mock does
  not have. `tests/integration/Cli/SeederTest.php:531-549` was written as
  `assertContains( … )` per title rather than asserting the set, which hides the extra row.
- `SeederTest.php:409-422` (`test_journal_post_one_is_on_a_sunday_with_location_and_syndication`)
  calls `set_now( '2026-09-20 12:00:00' )` — **2026-09-20 is itself a Sunday** (verified) — and
  `journal-post-1` carries `"days_ago": 0` (verified). The post date equals "now" and is a Sunday
  whatever the mechanic does; the weekday walk-back loop at `Cli/Seeder.php:366-369` could be
  deleted and the test stays green. It is the only coverage of Decision "Journal Sunday".

**Minimal fix:** drop the third book and assert the exact set; `set_now( '2026-09-23' )` (a
Wednesday) and additionally assert the date moved back exactly 3 days.

### 8. [Boundary / spec drift] Global excerpt filter, and the newsletter-box copy size — R1-08 (owners: P1-03, P1-06)

- `plugins/ttm-core/src/Blocks/Helpers.php:45` registers `excerpt_markup` on
  `render_block_core/post-excerpt` **globally** — no `is_singular()` guard, no className check.
  For any post whose manual excerpt contains `<`, it replaces core's `wp_trim_words()` output with
  the full kses'd excerpt, on the front-page lead dek, "More in" rows, archive rows, everywhere.
  Its sibling `featured_caption` (`:43`) *does* guard on `is_singular()`. Today only the seeded
  `2b` article has markup in its excerpt, so nothing is visibly wrong, but this is a site-wide
  behaviour change shipped under an "article header" task.
- `themes/ttm-theme/assets/css/ttm.css:991-995`:
  `.ttm-newsletter-box__copy { font-size: var(--wp--preset--font-size--body-s) }` = **14px**.
  SPEC §6.2 "Newsletter box" and PLAN P1-06 both say **13px** (the `ui` preset). P1-06 edited this
  exact rule and left the wrong size; §6.9 has no `box-copy` row, so nothing catches it.

### 9. [Tests] Fidelity rows that assert less than the §6.9 row they cite — R1-09

All in `tests/e2e/fidelity.spec.mjs`. Each is green today and would stay green under a real
regression:

| row | line | §6.9 says | asserts | task |
|---|---|---|---|---|
| `art-row` | 1080 | `2:1 tracks` | `t.length === 2` only — `is-style-grid-6-6` would pass | P1-02 |
| `art-hero` | 1194 | `filter: none` | evaluated on the **figure**; every grayscale rule targets an `img`, so a grayscale hero passes | P1-02 |
| `art-byline-author` | 1175 | `600 / text` | weight only; the byline's own `neutral-700` would silently win | P1-03 |
| `art-byline-tags` | 1183 | "right of the read-time span" | font-size + background only; `margin-left: auto` untested | P1-03 |
| `toc-item` | 1329 | `28px + 1` | `t.length === 2` only | P1-05 |
| `aside-phone-order` | 1441 | "all below `.ttm-prevnext`" | compares against prev/next's **top**, so an overlapping zone passes | P1-06 |
| `ar-year`, `ar-row`, `ar-mostread`, `ar-aside-row` | 2140–2296 | `120px+1`, `72px+1`, `24px 1fr`, `10px 1fr` | track **count** only; `tracks()` returns pixels and the suite asserts them elsewhere (`:1018`) | P3-03/04 |
| single-series h1 at 390 | — | P5-01 fixed the 44px cascade bug | only indirectly covered by the phone overflow loop; no assertion states the size | P5-01 |

### 10. [Constraint / hygiene] Prefix dropped, stale lint entries, no-op CSS, doc drift — R1-10

- `plugins/ttm-core/src/Fiction/Books.php:128` emits `class="book-admin-row"` (was
  `ttm-book-row`), renamed purely to silence `scripts/check-css-coverage.mjs`. CLAUDE.md:
  *"Prefixes: … CSS `ttm-`"*. Low functional risk (wp-admin only) but it leaves a generic,
  collision-prone class; the scanner should exclude admin-only files instead.
- `themes/ttm-theme/assets/css/ttm.css:2426-2429`: `.ttm-archive, .ttm-most-read { display: block }`
  is a self-admitted no-op added by P3-06 to clear rule 34's letter while defeating its purpose.
- `tests/e2e/selectors-allow.txt`: `.ttm-series-featured__part-dek # state: … the hub template
  leaves it at its false default` is **false** since P4-03 —
  `taxonomy-series.html:5` sets `"showDek":true`, `/series/hardening-wordpress/` is in
  `SCREEN_URLS`, and `single-parts` asserts `deks.count() > 0`. The line exempts a live selector
  from dead-selector detection. Delete it.
- `ttm.css:1119` `.ttm-footer__copyright:empty` can never match now that `drop_empty_bound()`
  removes empty bound paragraphs; its `selectors-allow.txt` reason is stale.
- `dd4dfbe` has **no `docs/PROGRESS.md` entry**, and `docs/PROGRESS.md:158` still documents the
  `text-transform: none` override that `dd4dfbe` removed as if it shipped.

---

## Spec issues

These are places where SPEC (or the mock) is itself unclear or wrong. None of them excuses a
deviation; they need an owner decision.

1. **CLAUDE.md's restatement of rule 2 drops its scope.** Phase-1 rule 2 reads "No
   `wp_enqueue_style` hooked to `wp_enqueue_scripts` … **in any file under
   `plugins/ttm-core/`**"; CLAUDE.md's `## Constraints` line drops the scope, so the theme's
   entirely correct `functions.php:32` enqueue reads as a violation. Restore the scope.
2. **SPEC §6.1.0's "one or the other" was resolved a third way.** SPEC says root padding is kept
   only if it does not double the gutter, "otherwise root padding is removed and the container
   owns it — one or the other". P0-09 keeps `useRootPaddingAwareAlignments: true` *and* neutralises
   it with `ttm.css:53-55` `.wp-site-blocks .has-global-padding { padding-inline: 0 }`. It works
   and `container-gutter` passes, but it silently zeroes global padding for any future constrained
   group. Either amend SPEC or take SPEC's stated fallback.
3. **Rule 41's reason vocabulary grew mid-flight.** PLAN sanctioned two reason kinds for
   `selectors-allow.txt` (`# P<n>-<nn> pending`, `# editor block style`). P1-07 and P3-06
   converted nine pending lines into a third, permanent `# state: …` category rather than making
   the selectors reachable. Each conversion is substantively defensible and rule 41 permits
   reasoned entries, but it satisfied the push tasks' literal acceptance check (`grep -c 'P1-'
   → 0`) without resolving anything. Worth ratifying the category explicitly or trimming it.
4. **§6.9 has rows that cannot fail.** `hub-grid-cats`'s "contains ' · ' or single name" and
   `wr-serial-form`'s `/i` flag are SPEC's own wording; the tests faithfully transcribe assertions
   that assert nothing. Tighten SPEC, not just the tests.
5. **Hub part numbering is undetermined.** `/series/` renders `01`–`06`; mock 1f's part number is
   templated (`{{ p.n }}`), so unlike mock 1e it settles nothing. `dd4dfbe` chose plain numbers
   for most-read on mock evidence; the hub, the article TOC, the chapter list and the 404 "Latest"
   list all still zero-pad. Needs an owner ruling, not a guess.
6. **`Values::archive_kind()`'s precedence test asserts an impossible state.**
   `tests/unit/Bindings/ValuesTest.php` asserts `archive_kind( ['month'=>true,'year'=>true] )`
   with the note "a day archive is also a month and a year archive". `WP_Query::parse_query()`
   guards each branch with `if ( ! $this->is_date )`, so exactly one flag is ever true. Harmless,
   but the test proves nothing and the recorded interpretation is factually wrong.

---

## Manual checks still owed

Copied from `docs/HANDOFF.md`, all still `NOT VERIFIED (human)`:

1. (P0-12) `docs/feedback/phase-3/article.png` masthead vs the top of
   `docs/feedback/design_article.png` — every inner page a centred 1280 column, inline nav, no
   "Close" button.
2. (P1-07) `article.png` vs `design_article.png`, and `article-390.png` vs mock `3b`
   (`docs/Eric Mann Newspaper.dc.html` lines 118–163).
3. (P2-04) `journal.png` vs `design_journal.png` — date block left, 36px body, syndication line,
   "Earlier" stream.
4. (P3-06) `archive-security.png` vs mock `1e`; `search.png` and `404.png` vs `02 §H`.
5. (P4-07) `series-hub.png` vs mock `1f`; `writing.png` vs `design_serial.png`;
   `series-single.png` vs `02 §F`.
6. (P5-01 / P5-05) Open `/signing-your-options-table/`, `/journal-post-1/`, `/writing/` and
   `/category/security/` at 390 in a **real phone browser** and compare with mock `3b` and the
   `02` Responsive bullets.
7. (P5-05) The final end-to-end comparison of every `docs/feedback/phase-3/*.png` against its
   paired mock per that directory's README, plus confirmation that CI is green on the branch
   including the `e2e` job and its `playwright-report` artifact.

Added by this review:

8. `docs/feedback/phase-3/series-single.png`: the per-part date sits on the **dek's** baseline
   rather than the title's, because the `40px 1fr auto` grid is `align-items: baseline` and the
   `1fr` cell wraps to two lines. `single-parts` asserts count, font-size and dek count only.
   Confirm against `02 §F` whether this is acceptable as drawn.
9. Hub part numbering (`01`–`06`) — see Spec issue 5.
