# Phase 5 screenshots

Written by `npm run screenshots` (SPEC §6.10) against the running, seeded wp-env site
(`npx wp-env start && npx wp-env run cli wp ttm seed --reset && npm run screenshots`). Each
phase's push task re-runs it and commits the PNGs here. All six shots are full-page captures at
1280×900 except `front-390.png`, which is 390×844. `docs/feedback/phase-4/` stays untouched
(history). Live screenshots are not taken this flight — the demo content shown here is the
same seeded, synthetic content every other phase-5 check runs against.

| file | what to look at |
|---|---|
| `front.png` | The lead photograph and every section cell's photograph fit their frames without stretching or cropping out the subject; no placeholder band anywhere on the page; footer and masthead byline read correctly. |
| `article.png` | The article hero photograph's fit/crop; its caption is editorial ("what this shows"), never a credit line — credits live only in `docs/fixtures/demo/CREDITS.json`; no red anywhere. |
| `archive-technology.png` | Every cell photograph on the Technology archive fits its frame; no placeholder band; masthead byline unchanged. |
| `writing.png` | The story-tile cover photographs fit their frames; no placeholder band; footer unchanged. |
| `about.png` | The owner's portrait photograph's fit/crop and aspect ratio; no placeholder band. |
| `front-390.png` | The front page at phone width: same photograph-fit and no-placeholder-band checks as `front.png`, plus the masthead/footer stay legible at 390. |

Nothing here should be red (rule "seed never uses the accent red") or show a broken-image icon
or a flat placeholder rectangle where a photograph belongs — every seeded photo slot should
carry a real Openverse CC0/PDM image by the time these are captured. The owner's name in the
footer and masthead byline should read exactly as `Config::author_name()` returns it, unchanged
from phase 4.
