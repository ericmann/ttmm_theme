/* eslint-disable no-console */
// CSS budget (SPEC §3.4 rule 30). cssBudgetBytes is the single tunable.
// Raised from 25600 to 28000 in P5-04 (archive/search templates), then to
// 33000 in P6-05: series progress/toc/featured, story tile, book cover/grid,
// stat row and the Writing hero + hub CSS (27358 -> ~32000 bytes measured
// before this bump); see the P6-05 commit body for exact before/after counts.
// R1-09: tightened to 32700 after adding the .ttm-hp honeypot rule and
// de-duplicating the grid/rule/button CSS and two multi-line comments
// (33121 with .ttm-hp added and nothing removed -> 32610 after cleanup;
// CLAUDE.md's constraint line was stale at 25600 since P5-04/P6-05 and is
// corrected to match here).
// R1-12: raised to 33200 for 01 §4.13's missing .ttm-syndication rule
// (32610 -> 33070, including the stylelint-disable comment its descending-
// specificity warning needed); CLAUDE.md's constraint line updated to match.
// P0-03: rule 30 amendment raises the budget to 40960 ahead of phase 2's
// front-page CSS (lead row, journal rail, masthead/nav, writing cell); no
// CSS added by this task. CLAUDE.md already says 40960 (this file was
// stale against it). The number lives only here and in CLAUDE.md.
// P3-03: rule 30's raise (40960 -> 41984) for the Writing cell's body grid/
// kicker/headline/dek/also-running CSS (41845 measured, after trimming
// comments elsewhere in the file first).
// P3-04: R6's raise turned out to need a second correction once the series
// strip's real CSS was measured (41984 -> 43008; 42583 measured, after the
// same kind of comment-trimming). P3-05 still runs its own measurement pass
// per its own scope, but should find this settled rather than raise again.
// P0-01: rule 30 amendment raises the budget to 61440 ahead of this flight's
// remaining templates (article, journal, archives, series hub, Writing);
// no CSS added by this task. 61440 is the ⚠️ ASSUMPTION default and lives
// only here and in CLAUDE.md; a later phase may tune it down in P5-03.
// R1-06: ttm.css shipped at 61439/61440 -- one byte of headroom -- which had
// forced several SPEC/PLAN-named declarations to be dropped to fit. Raised
// to 62464 (the next 1024 multiple above 61439) to restore them with real
// headroom. The number lives only here and in CLAUDE.md.
// R4-01: shipped at 62463/62464 -- one byte of headroom -- which blocked the
// series-row count `grid-row` placement fix and the Writing body flex-column
// fix (rule 36). Raised to 63488 (the next 1024 multiple) to restore real
// headroom. The number lives only here and in CLAUDE.md.
// P5-03: end-of-flight measurement (SPEC §5 tuning task): 62568 bytes before
// this phase's CSS changes (coverage-exemption/live-check work added none),
// 62640 after -- a 72-byte growth, nowhere near the 1024-byte shrink this
// task's own rule requires before lowering the budget. Kept at 63488
// unchanged.
// P4-01: end-of-flight measurement (SPEC §8 Phase 4 tuning task): 63090
// bytes before this task (no CSS changes) and 63090 after -- no shrink, so
// kept at 63488 unchanged.
import { statSync, existsSync } from 'node:fs';
const cssBudgetBytes = 63488;
const file = 'themes/ttm-theme/assets/css/ttm.css';
if ( ! existsSync( file ) ) {
	console.error( `${ file } missing` );
	process.exit( 1 );
}
const size = statSync( file ).size;
if ( size > cssBudgetBytes ) {
	console.error( `${ file } is ${ size } bytes; budget ${ cssBudgetBytes }` );
	process.exit( 1 );
}
console.log( `${ file }: ${ size } / ${ cssBudgetBytes } bytes` );
