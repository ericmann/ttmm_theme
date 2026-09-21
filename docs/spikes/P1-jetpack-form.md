# P1-07 spike: Jetpack Subscriptions widget POST contract

## Outcome

**B** — the field list mostly matches SPEC §6.3, but one claim is wrong: Jetpack's rendered
self-hosted widget form **does** include a nonce field. SPEC's "no nonce (rule 7; the widget
path has none)" is incorrect for Jetpack 16.2 as installed. `Jetpack_Subscriptions_Widget
::process_subscription`, the method SPEC cites for the handler, does not exist in this version
either (see below) — no handler could be traced further without a WordPress.com connection,
which this spike does not attempt.

Version checked: **Jetpack 16.2** (`wp plugin get jetpack --field=version`), installed via
`wp plugin install jetpack --activate` in wp-env, **not connected** to WordPress.com at any
point.

## Field list

Source: `wp-content/plugins/jetpack/modules/subscriptions/views.php`,
`Jetpack_Subscriptions_Widget::render_widget_subscription_form()`, the `self::is_jetpack()`
branch (lines 518–582) — this is the self-hosted/unconnected rendering path, the one a
`ttm/newsletter-form` provider would actually see. (There is a second, near-identical branch
for `self::is_wpcom()`, lines 402–487, not reachable on a self-hosted, unconnected install.)

| Field | SPEC §6.3 says | Actually found | Line(s) |
|---|---|---|---|
| `action` | `action=subscribe` | `action=subscribe` (hidden input) | 558 |
| `source` | `{current URL}` | the request's own `referer` URL (`is_ssl() ? 'https' : 'http'` + `HTTP_HOST` + `REQUEST_URI`, `esc_url_raw()`d) | 388, 559 |
| `sub-type` | `widget` | `widget` (hidden input, `$source = 'widget'` at line 389) | 389, 560 |
| `redirect_fragment` | `ttm-newsletter-{n}` | `self::get_redirect_fragment( $widget_id )` — Jetpack's own id scheme (`subscribe-blog` or `subscribe-blog-{instance_count}`), **not** a `ttm-newsletter-{n}` pattern; a `ttm/newsletter-form` provider would need to supply its own value for this field to control the fragment, or accept Jetpack's | 366–372, 519, 561 |
| submit button `name` | `jetpack_subscriptions_widget` | `jetpack_subscriptions_widget` | 570 |
| email field | `name="email"` | `name="email"`, `type="email"`, `autocomplete="email"`, `required="required"` | 539–549 |
| **nonce** | **"No nonce (rule 7; the widget path has none)"** | **`wp_nonce_field( 'blogsub_subscribe_' . \Jetpack_Options::get_option( 'id' ) )`** — a real nonce field is rendered, action name includes the Jetpack blog id | **562** |
| handler method | `Jetpack_Subscriptions_Widget::process_subscription` | **not found** — no method by that name exists anywhere under `wp-content/plugins/jetpack/` in 16.2 (`grep -rl process_subscription` returns nothing) | n/a |

## For P1-08

- The nonce means a `ttm/newsletter-form` provider that renders Jetpack's markup cannot claim
  "no nonce on cacheable output" the way `custom-url` does — the nonce value is itself
  per-visitor state, which conflicts with SPEC §3.2's cacheable-output rule unless the block
  either (a) treats the Jetpack widget as genuinely uncacheable, or (b) doesn't attempt to
  reproduce Jetpack's own markup byte-for-byte and instead defers entirely to Jetpack's own
  block/widget rendering (`jetpack/subscriptions` block, `do_blocks()`), which already handles
  its own nonce internally and isn't something this theme's code touches directly (matches how
  `NewsletterFormTest::test_jetpack_provider_renders_subscriptions_block` already treats it —
  as an opaque rendered block, not a markup shape `ttm-core` reconstructs itself).
- `redirect_fragment` does not follow a `ttm-newsletter-{n}` pattern in the installed source;
  SPEC's description of that field's value should be corrected or dropped in favor of "whatever
  Jetpack's own `get_redirect_fragment()` produces" if a future task needs to reference it.
- No handler method named `process_subscription` exists to point integration tests at; the
  actual POST handling for a self-hosted, connected site happens via Jetpack's REST/XML-RPC
  bridge to WordPress.com, which cannot be exercised without a real connection and is out of
  scope for this repo's tests (P1-06 already keeps `custom-url`'s own handler self-contained and
  independent of Jetpack's).

## Method

```
npx wp-env run cli wp plugin install jetpack --activate   # already installed/active from an earlier phase
npx wp-env run cli wp plugin get jetpack --field=version   # 16.2
```

Then read `wp-content/plugins/jetpack/modules/subscriptions/views.php` directly (no
`wp eval` render was needed — the file's own `self::is_jetpack()` branch is unconditional
static PHP+HTML, easy to read verbatim). No connection to WordPress.com was made or attempted
at any point.

Cleanup (per task instructions, so seed/e2e stay Jetpack-free):

```
npx wp-env run cli wp plugin deactivate jetpack
npx wp-env run cli wp plugin delete jetpack
```
