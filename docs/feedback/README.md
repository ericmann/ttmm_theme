# Owner feedback screenshots

`design_*.png` — the Claude Design mocks rendered at 1280 (target).
`preview_*.png` — the build on `localhost:8888` at the same crops (wrong wherever it differs).
`phase-N/` — screenshots produced by each Foundry flight for comparison.

| screen | mock | design | preview | taken |
|---|---|---|---|---|
| front page: masthead + lead row | 2a | `design_top.png` | `preview_top.png` | phase 1 result, 2026-09-21 |
| front page: section rows + strip | 2a | `design_blocks.png` | `preview_blocks.png` | phase 1 result |
| front page: poster + footer | 2a | `design_footer.png` | `preview_footer.png` | phase 1 result |
| article | 2b | `design_article.png` | `preview_article.png` | phase 2 result, 2026-09-21 |
| journal post | 2c | `design_journal.png` | `preview_journal.png` | phase 2 result |
| Writing page | 2d | `design_serial.png` | `preview_serial.png` | phase 2 result |

The phase 2 previews were captured at 1870px wide; the page has no maximum width,
which is itself one of the defects (SPEC v3 §6.1.0).
