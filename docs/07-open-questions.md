# 07 · Open questions & defaults

Decisions the design leaves to the build/planning pass. Each has the design’s default so work can proceed without waiting.

| # | Question | Default | Notes |
|---|---|---|---|
| Q1 | Greenfield theme vs Powder child | **Greenfield** `ttm-theme` | Powder’s presets conflict with the token set; nothing of its templates survives. |
| Q2 | Journal template selection: primary-category filter vs post format `aside` | **Primary-category filter** (`single-journal.html`) | Post formats are legacy; a category is already how the owner files them. |
| Q3 | Politics → child category of Opinion or a tag | **Child category** `opinion/politics` with redirects from `/category/politics/` | Keeps its archive; cells show “· Politics”. |
| Q4 | Books: option repeater vs CPT | **Option repeater** (`ttm_books`) | Promote to CPT if > 10 books or if books need their own pages. |
| Q5 | Newsletter provider | **Buttondown** adapter first; provider-agnostic interface | Owner to confirm; poster copy assumes weekly. Day of send unknown (“every Sunday” copy pending). |
| Q6 | “Most read” source | **Manual flag** `ttm_featured_in_section` (up to 3 per category) | No analytics dependency; can switch to a views counter later. |
| Q7 | Include Journal in the main RSS feed | **Yes** | Setting exists to flip it. |
| Q8 | Comments | **Off** by default; theme styles the core form minimally if enabled | Not designed. |
| Q9 | Verse API payload shape | **Inspect at build** (`wp ttm verse inspect`) | Design assumes date, text, reference, URL exist. If no per-item URL, link the site root. |
| Q10 | Writing category archive URL (`/category/writing/`) vs page (`/writing/`) | **Both resolve to the Writing layout**; canonical `/writing/` | Redirect the category URL. |
| Q11 | Series hub URL | `/series/` page + `/series/{slug}/` taxonomy archives | Reserve the slug on activation. |
| Q12 | Newsletter archive on `/newsletter/` | **Not built**; page has poster + explainer only | Add when a provider archive is available. |
| Q13 | About page portrait | **Optional**, colour, 3:2 above H1 | Owner supplies. |
| Q14 | Italic font file | **Load 400 italic** (Archivo has true italics) | Skip 600/800 italic. |
| Q15 | Analytics/consent UI | **None designed** | If required, a single 12px neutral-700 line above the footer, no banner. |
| Q16 | Search results ranking | **Core** | Design covers layout only. |
| Q17 | Migration of existing series | Owner lists them; `wp ttm series:assign` from tags, hand-correct part numbers | Do before launch so the hub isn’t empty (F4/F5 would otherwise show completed-only). |
| Q18 | Sticky-post behaviour | **Sticky within 30 days = lead**; older stickies ignored | Documented in `03 §7`. |
| Q19 | Phone nav: overlay vs scroll row on inner pages | **Overlay** via core Navigation “Menu” on inner pages; **scroll row** on the front page only | The front page has room for the row; inner pages want the header short. |
| Q20 | Dark mode | **No** | Not designed; do not add. |
