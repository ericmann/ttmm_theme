<?php
/**
 * Deterministic seed content library, fed from docs/fixtures/seed/*.json.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\PostMeta;
use TTM\Core\Query\Stats;
use TTM\Core\Support\Clock;
use TTM\Core\Verse\Fetcher;

/**
 * Seeds categories, pages, navigation, posts and images; idempotent by slug.
 */
class Seeder {

	private const SEED_META = '_ttm_seed';

	/**
	 * Seed placeholder colours (rule 45): neutral field, darker diagonal band, lighter inset
	 * border, cover fill and cover text, one `[r, g, b]` array each -- values from
	 * `docs/_ds/…/styles.css` (neutral-400, neutral-500, neutral-300, neutral-700, neutral-100).
	 */
	private const FIELD      = [ 186, 182, 182 ];
	private const BAND       = [ 155, 151, 151 ];
	private const BORDER     = [ 215, 211, 211 ];
	private const COVER      = [ 96, 93, 93 ];
	private const COVER_TEXT = [ 248, 244, 244 ];

	/**
	 * Days added to every post's `days_ago` for the "quiet" state.
	 *
	 * @var int
	 */
	private int $days_offset = 0;

	/**
	 * Current seed state.
	 *
	 * @var string
	 */
	private string $state = 'normal';

	/**
	 * The fixtures directory: the wp-env mapping if present, else the repo path directly.
	 *
	 * @return string
	 */
	public static function fixtures_dir(): string {
		return self::fixtures_root_dir() . '/seed';
	}

	/**
	 * Validate a posts.json `form` override (pure; no WordPress calls).
	 *
	 * @param mixed $form Raw fixture value.
	 * @return string|null The value, when it is one of `Meta\PostMeta::FORMS` (the `ttm_form`
	 *                      values `Meta\Form::derive()` can produce); null otherwise (missing
	 *                      field, wrong type, or a value `Meta\Form::derive()` never produces).
	 */
	public static function normalize_form( $form ): ?string {
		if ( ! is_string( $form ) || ! in_array( $form, PostMeta::FORMS, true ) ) {
			return null;
		}

		return $form;
	}

	/**
	 * The `docs/fixtures` root: the wp-env mapping if present, else the repo path directly.
	 *
	 * @return string
	 */
	public static function fixtures_root_dir(): string {
		$mapped = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/ttm-fixtures' : '';
		if ( $mapped && is_dir( $mapped ) ) {
			return $mapped;
		}

		return dirname( TTM_CORE_DIR, 3 ) . '/docs/fixtures';
	}

	/**
	 * Load and decode a fixture JSON file.
	 *
	 * @param string $name File name, e.g. "posts.json".
	 * @return array<int, array<string, mixed>>
	 */
	private function load( string $name ): array {
		$path = self::fixtures_dir() . '/' . $name;
		if ( ! file_exists( $path ) ) {
			return [];
		}

		return (array) json_decode( (string) file_get_contents( $path ), true );
	}

	/**
	 * `$count` `core/paragraph` blocks drawn deterministically from `prose.json`, cycling by
	 * `$row_index` so the same row always gets the same paragraphs (SPEC §6.5).
	 *
	 * @param int $row_index Stable index of the row this prose is for (its position in the
	 *                        source fixture, not a database id).
	 * @param int $count     Number of paragraphs to draw; 0 or a missing/empty fixture returns ''.
	 * @return string
	 */
	private function prose( int $row_index, int $count ): string {
		if ( $count <= 0 ) {
			return '';
		}

		$paragraphs = $this->load( 'prose.json' );
		$total      = count( $paragraphs );
		if ( 0 === $total ) {
			return '';
		}

		$blocks = '';
		for ( $offset = 0; $offset < $count; $offset++ ) {
			$paragraph = (string) $paragraphs[ ( $row_index + $offset ) % $total ];
			$blocks   .= "\n\n<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->\n";
		}

		return $blocks;
	}

	/**
	 * Run the full seed for a given state.
	 *
	 * "quiet": every post's days_ago + `seed.quiet_offset_days` (nothing recent; statuses stay
	 * as fixtured). "empty": normal minus Security/Opinion posts, series + chapters, stories,
	 * books, and the verse options are deleted rather than seeded.
	 *
	 * @param string $state Seed state: "normal", "quiet", or "empty".
	 * @return array{categories:int, pages:int, posts:int, navigation:int, series:int, books:int}
	 */
	public function run( string $state ): array {
		$this->state       = $state;
		$this->days_offset = 'quiet' === $state ? (int) Config::get( 'seed.quiet_offset_days', 120 ) : 0;

		// P0-05: a previous run's stats/top-tags transients must never leak into this one.
		Stats::flush_all();

		$categories = $this->seed_categories();
		$pages      = $this->seed_pages();
		$navigation = $this->seed_navigation();
		$posts      = $this->seed_posts();
		$series     = 'empty' === $state ? [] : $this->seed_series();
		$books      = 'empty' === $state ? [] : $this->seed_books();
		$this->seed_verse();
		$this->seed_newsletter();

		// SPEC §6.5: the mock's tagline. A translatable literal here is fine -- seed content
		// only, never read at request time.
		update_option( 'blogdescription', __( 'Technology, business, faith and the occasional story. One writer, several desks.', 'ttm-core' ) );

		// P0-05: every seeded post's author display name, for the byline (SPEC §6.2 art-byline).
		wp_update_user(
			[
				'ID'           => 1,
				'display_name' => Config::author_name(),
			]
		);

		return [
			'categories' => count( $categories ),
			'pages'      => count( $pages ),
			'posts'      => count( $posts ),
			'navigation' => $navigation ? 1 : 0,
			'series'     => count( $series ),
			'books'      => count( $books ),
		];
	}

	/**
	 * `wp ttm seed --starter-only` (SPEC §6.6 step 1, §6.7): the theme's starter content only
	 * -- categories, the Series/Writing/Newsletter/About pages, and the Sections navigation.
	 * No posts, series, books, verse, or newsletter settings. Idempotent by slug, same as
	 * every `seed_*()` method it calls.
	 *
	 * @return array{categories:int, pages:int, navigation:int}
	 */
	public function run_starter(): array {
		$categories = $this->seed_categories();
		$pages      = $this->seed_pages();
		$navigation = $this->seed_navigation();

		return [
			'categories' => count( $categories ),
			'pages'      => count( $pages ),
			'navigation' => $navigation ? 1 : 0,
		];
	}

	/**
	 * Whether destructive seed operations (`reset()`, `--starter-only` re-running over live
	 * content) are allowed for a given `WP_ENVIRONMENT_TYPE` value (rule 49: `seed --reset` is
	 * the one sanctioned wipe, guarded by environment). Pure: no WordPress calls.
	 *
	 * @param string $environment_type `wp_get_environment_type()` value.
	 * @return bool
	 */
	public static function may_wipe( string $environment_type ): bool {
		return 'production' !== $environment_type;
	}

	/**
	 * Slugs excluded from posts.json when seeding the "empty" state: every chapter
	 * (from series.json parts) and every standalone story (slug prefix "story-").
	 *
	 * @return string[]
	 */
	private function empty_state_excluded_slugs(): array {
		$excluded = [];
		foreach ( $this->load( 'series.json' ) as $series ) {
			foreach ( $series['parts'] as $part ) {
				$excluded[] = $part['post_slug'];
			}
		}

		return $excluded;
	}

	/**
	 * Create the seven sections plus politics (child of opinion). Idempotent by slug.
	 *
	 * @return int[] Term ids, keyed by slug.
	 */
	public function seed_categories(): array {
		$rows = $this->load( 'categories.json' );
		$ids  = [];

		// Two passes: top-level first, so `parent` slugs resolve.
		foreach ( $rows as $row ) {
			if ( ! empty( $row['parent'] ) ) {
				continue;
			}
			$ids[ $row['slug'] ] = $this->upsert_category( $row, $ids );
		}
		foreach ( $rows as $row ) {
			if ( empty( $row['parent'] ) ) {
				continue;
			}
			$ids[ $row['slug'] ] = $this->upsert_category( $row, $ids );
		}

		return $ids;
	}

	/**
	 * Create or reuse one category term.
	 *
	 * @param array<string, mixed> $row Fixture row.
	 * @param int[]                $ids Slugs resolved so far, for `parent`.
	 * @return int
	 */
	private function upsert_category( array $row, array $ids ): int {
		$existing = get_term_by( 'slug', $row['slug'], 'category' );
		if ( $existing ) {
			// The theme's starter content (after_switch_theme) creates the sections with no
			// description, so a seed on a fresh install must still apply the fixture's copy.
			$description = $row['description'] ?? '';
			if ( '' !== $description && $description !== $existing->description ) {
				wp_update_term( (int) $existing->term_id, 'category', [ 'description' => $description ] );
			}
			return (int) $existing->term_id;
		}

		$args = [ 'description' => $row['description'] ?? '' ];
		if ( ! empty( $row['parent'] ) && isset( $ids[ $row['parent'] ] ) ) {
			$args['parent'] = $ids[ $row['parent'] ];
		}

		$created = wp_insert_term( $row['name'], 'category', array_merge( $args, [ 'slug' => $row['slug'] ] ) );
		$term_id = (int) $created['term_id'];
		update_term_meta( $term_id, self::SEED_META, 1 );

		return $term_id;
	}

	/**
	 * Create the four pages. Idempotent by slug.
	 *
	 * @return int[] Post ids.
	 */
	public function seed_pages(): array {
		$rows = $this->load( 'pages.json' );
		$ids  = [];

		foreach ( $rows as $row ) {
			$existing = get_page_by_path( $row['slug'], OBJECT, 'page' );
			if ( $existing ) {
				// The theme's starter content creates `about` without a portrait; add it.
				if ( ! empty( $row['featured_image'] ) && ! has_post_thumbnail( $existing->ID ) ) {
					$attachment_id = $this->image( $row['title'], 'ttm-thumb' );
					if ( $attachment_id ) {
						set_post_thumbnail( $existing->ID, $attachment_id );
					}
				}
				$ids[] = $existing->ID;
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $row['slug'],
					'post_title'   => $row['title'],
					'post_content' => $row['content'],
				]
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_wp_page_template', $row['template'] . '.html' );
				update_post_meta( $post_id, self::SEED_META, 1 );

				if ( ! empty( $row['featured_image'] ) ) {
					$attachment_id = $this->image( $row['title'], 'ttm-thumb' );
					if ( $attachment_id ) {
						set_post_thumbnail( $post_id, $attachment_id );
					}
				}

				$ids[] = $post_id;
			}
		}//end foreach

		return $ids;
	}

	/**
	 * Create the "Sections" navigation menu. Idempotent by title.
	 *
	 * @return int 0 if it already existed or nothing to create.
	 */
	public function seed_navigation(): int {
		$existing = get_posts(
			[
				'post_type'      => 'wp_navigation',
				'title'          => 'Sections',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $existing ) ) {
			return 0;
		}

		$order = (array) Config::get( 'sections.order', [] );
		$links = '';
		foreach ( $order as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$term_link = get_term_link( $term );
			if ( is_wp_error( $term_link ) ) {
				continue;
			}
			$links .= sprintf(
				'<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->',
				esc_attr( $term->name ),
				esc_url( $term_link )
			);
		}
		$links .= '<!-- wp:navigation-link {"label":"Series","url":"' . esc_url( home_url( '/series/' ) ) . '","kind":"custom"} /-->';

		$post_id = wp_insert_post(
			[
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Sections',
				'post_content' => $links,
			]
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, self::SEED_META, 1 );
			return $post_id;
		}

		return 0;
	}

	/**
	 * Create the seeded posts. Idempotent by slug.
	 *
	 * @return int[] Post ids.
	 */
	public function seed_posts(): array {
		$rows     = $this->load( 'posts.json' );
		$ids      = [];
		$excluded = 'empty' === $this->state ? $this->empty_state_excluded_slugs() : [];
		// R5-01: read the clock once for the whole run and offset every row by its fixture
		// index in seconds (structural arithmetic, not a Config tunable, rule 24) so rows
		// inserted within the same wall-clock second still get distinct post_date values.
		// The offset is applied BEFORE the weekday walk-back below so a pin still lands on
		// the right weekday even when the offset crosses midnight.
		$now = Clock::now();

		foreach ( $rows as $index => $row ) {
			if ( 'empty' === $this->state ) {
				$in_excluded_categories = array_intersect( $row['categories'], [ 'security', 'opinion' ] );
				$is_chapter_or_story    = in_array( $row['slug'], $excluded, true ) || str_starts_with( $row['slug'], 'story-' );
				// Every other Writing-category post in the fixture (e.g. "writing-post-*") has
				// no series, so per 03 §4 ("Story = a Writing post ... with no series") it would
				// auto-classify as a Story the moment Form::on_save() sees its real category
				// (below) -- found live via P7-08's separability test once the seeder's stale
				// ttm_form bug (see the re-derive call below) was fixed: the "empty" state is
				// meant to have zero fiction, so any Writing post must be excluded outright here,
				// not just the ones the fixture happens to name like a chapter or a story.
				$is_writing = in_array( 'writing', $row['categories'], true );
				if ( $in_excluded_categories || $is_chapter_or_story || $is_writing ) {
					continue;
				}
			}

			$existing = get_page_by_path( $row['slug'], OBJECT, 'post' );
			if ( $existing ) {
				$ids[] = $existing->ID;
				continue;
			}

			$is_future = ! empty( $row['future'] );
			$days_ago  = $is_future ? (int) $row['days_ago'] : (int) $row['days_ago'] + $this->days_offset;
			$moment    = $now->modify( ( $days_ago >= 0 ? '-' : '+' ) . abs( $days_ago ) . ' days' )
				->modify( '-' . (int) $index . ' seconds' );

			// P0-08: pin a post to a specific weekday (e.g. the Journal's Sunday post),
			// walking back at most a week -- never forward, so a "future" post stays future.
			if ( ! empty( $row['weekday'] ) ) {
				for ( $shift = 0; $shift < 7 && $moment->format( 'l' ) !== $row['weekday']; $shift++ ) {
					$moment = $moment->modify( '-1 day' );
				}
			}

			$date = $moment->format( 'Y-m-d H:i:s' );

			$post_id = wp_insert_post(
				[
					'post_type'     => 'post',
					'post_author'   => 1,
					'post_status'   => $is_future ? 'future' : 'publish',
					'post_name'     => $row['slug'],
					'post_title'    => $row['title'],
					'post_content'  => ( $row['content'] ?? '' ) . $this->prose( $index, (int) ( $row['paragraphs'] ?? 0 ) ),
					'post_excerpt'  => $row['excerpt'] ?? '',
					'post_date'     => $date,
					'post_date_gmt' => get_gmt_from_date( $date ),
					'tags_input'    => $row['tags'] ?? [],
				]
			);

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			if ( isset( $row['part_title'] ) ) {
				update_post_meta( $post_id, 'ttm_part_title', $row['part_title'] );
			}

			$category_ids = [];
			foreach ( $row['categories'] as $slug ) {
				$term = get_term_by( 'slug', $slug, 'category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$category_ids[] = $term->term_id;
				}
			}
			if ( $category_ids ) {
				wp_set_post_categories( $post_id, $category_ids );

				// wp_insert_post() above fired save_post_post (and PrimaryCategory::on_save(),
				// Form::on_save()) before these categories were attached, so PrimaryCategory
				// resolved and stored "Uncategorized" and Form derived "article" for every post
				// (a standalone Writing story looked like it had no category, so `in_writing`
				// was false). wp_set_post_categories() does not refire save_post, so those stale
				// values would otherwise persist forever (found live via P7-08's separability
				// test, which seeds a real ttm/story-tiles render and got nothing back): force a
				// fresh resolve of both now that the post's real categories are in place.
				delete_post_meta( $post_id, 'ttm_primary_category' );
				\TTM\Core\Meta\PrimaryCategory::on_save( $post_id, get_post( $post_id ) );
				\TTM\Core\Meta\Form::on_save( $post_id, get_post( $post_id ), true );
			}

			// A fixture `form` override wins over the re-derive above and is locked so a later
			// save_post never overwrites it (R3-03: two Writing essays with no series would
			// otherwise auto-classify as "story" and displace SPEC §6.10's actual stories).
			$ttm_form = self::normalize_form( $row['form'] ?? null );
			if ( null !== $ttm_form ) {
				update_post_meta( $post_id, 'ttm_form', $ttm_form );
				update_post_meta( $post_id, 'ttm_form_locked', 1 );
			}

			if ( ! empty( $row['featured_image'] ) ) {
				$attachment_id = $this->image( $row['title'], 'ttm-tile' );
				if ( $attachment_id ) {
					set_post_thumbnail( $post_id, $attachment_id );
					if ( ! empty( $row['caption'] ) ) {
						wp_update_post(
							[
								'ID'           => $attachment_id,
								'post_excerpt' => $row['caption'],
							]
						);
					}
				}
			}

			if ( ! empty( $row['most_read'] ) ) {
				update_post_meta( $post_id, 'ttm_featured_in_section', true );
			}
			if ( ! empty( $row['syndication'] ) ) {
				update_post_meta( $post_id, 'ttm_syndication', $row['syndication'] );
			}
			if ( ! empty( $row['location'] ) ) {
				update_post_meta( $post_id, 'ttm_location', $row['location'] );
			}

			update_post_meta( $post_id, self::SEED_META, 1 );
			$ids[] = $post_id;
		}//end foreach

		return $ids;
	}

	/**
	 * Create the series terms and attach their parts. Idempotent by slug.
	 *
	 * @return int[] Term ids.
	 */
	public function seed_series(): array {
		$rows = $this->load( 'series.json' );
		$ids  = [];

		foreach ( $rows as $row ) {
			$term = get_term_by( 'slug', $row['slug'], 'series' );
			if ( ! $term ) {
				$created = wp_insert_term( $row['name'], 'series', [ 'slug' => $row['slug'] ] );
				$term    = get_term( (int) $created['term_id'], 'series' );
				update_term_meta( $term->term_id, self::SEED_META, 1 );
			}
			$term_id = (int) $term->term_id;

			if ( isset( $row['description'] ) && $row['description'] !== $term->description ) {
				wp_update_term( $term_id, 'series', [ 'description' => $row['description'] ] );
			}

			update_term_meta( $term_id, 'ttm_status', $row['status'] );
			update_term_meta( $term_id, 'ttm_form', $row['form'] );
			update_term_meta( $term_id, 'ttm_total_parts', (int) $row['total_parts'] );
			update_term_meta( $term_id, 'ttm_genre', $row['genre'] ?? '' );
			update_term_meta( $term_id, 'ttm_cadence', $row['cadence'] ?? '' );

			if ( ! empty( $row['featured'] ) ) {
				update_term_meta( $term_id, 'ttm_featured', 1 );
			} else {
				// Idempotent: a reseed after the fixture drops "featured" must not leave a
				// stale flag behind.
				delete_term_meta( $term_id, 'ttm_featured' );
			}

			if ( ! empty( $row['next_date_days_ahead'] ) ) {
				$next = Clock::now()->modify( '+' . (int) $row['next_date_days_ahead'] . ' days' )->format( 'Y-m-d' );
				update_term_meta( $term_id, 'ttm_next_date', $next );
			}

			if ( ! empty( $row['cover'] ) ) {
				$cover_id = $this->cover( $row['name'] );
				if ( $cover_id ) {
					update_term_meta( $term_id, 'ttm_cover_id', $cover_id );
				}
			}

			if ( ! empty( $row['purchase_links'] ) ) {
				update_term_meta( $term_id, 'ttm_purchase_links', $row['purchase_links'] );
			}

			foreach ( $row['parts'] as $part ) {
				$post = get_page_by_path( $part['post_slug'], OBJECT, 'post' );
				if ( ! $post ) {
					continue;
				}
				wp_set_object_terms( $post->ID, [ $term_id ], 'series' );
				update_post_meta( $post->ID, 'ttm_series_part', (int) $part['part'] );

				// seed_posts() already re-derived ttm_form once (see its own comment), but that
				// ran before this series term existed, so every chapter was still classified
				// "story" (no series -> in Writing -> Form::derive() returns 'story') and stuck
				// that way forever (wp_set_object_terms() doesn't refire save_post). Found live:
				// the Writing cell's "Also running" list showed the featured serial's own latest
				// chapter a second time, labelled "Story", because Serials::stories() matches
				// ttm_form=story. Re-derive now that the real series membership is in place.
				\TTM\Core\Meta\Form::on_save( $post->ID, get_post( $post->ID ), true );
			}

			$ids[] = $term_id;
		}//end foreach

		\TTM\Core\Query\SeriesIndex::rebuild();

		return $ids;
	}

	/**
	 * Create the seeded books option. Idempotent (replaces the whole option each run).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function seed_books(): array {
		$rows  = $this->load( 'books.json' );
		$books = [];

		foreach ( $rows as $row ) {
			$series_id = 0;
			if ( ! empty( $row['series_slug'] ) ) {
				$term = get_term_by( 'slug', $row['series_slug'], 'series' );
				if ( $term && ! is_wp_error( $term ) ) {
					$series_id = (int) $term->term_id;
				}
			}

			$cover_id = ! empty( $row['cover'] ) ? $this->cover( $row['title'] ) : 0;

			$books[] = [
				'title'     => $row['title'],
				'form'      => $row['form'],
				'year'      => (int) $row['year'],
				'cover_id'  => $cover_id,
				'formats'   => $row['formats'] ?? [],
				'links'     => $row['links'] ?? [],
				'series_id' => $series_id,
			];
		}//end foreach

		update_option( 'ttm_books', \TTM\Core\Fiction\Books::sanitize( $books ) );

		return $books;
	}

	/**
	 * Seed `ttm_verse`/`ttm_verse_history` from docs/fixtures/verse-sample.json, or delete
	 * them for the "empty" state.
	 */
	public function seed_verse(): void {
		if ( 'empty' === $this->state ) {
			delete_option( 'ttm_verse' );
			delete_option( 'ttm_verse_history' );
			return;
		}

		$path = self::fixtures_root_dir() . '/verse-sample.json';
		if ( ! file_exists( $path ) ) {
			return;
		}

		$payload = json_decode( (string) file_get_contents( $path ), true );
		$items   = $payload['data'] ?? [];
		if ( empty( $items ) ) {
			return;
		}

		update_option( 'ttm_verse', self::seed_verse_item( $items[0] ) );
		update_option( 'ttm_verse_history', array_map( [ self::class, 'seed_verse_item' ], array_slice( $items, 0, 6 ) ) );
	}

	/**
	 * SPEC §6.3: `wp ttm seed` configures `custom-url` with an empty endpoint so
	 * `newsletter.dev_accept` applies outside production and the dev poster shows and submits
	 * a real, locally-accepted form. No Jetpack install (P1-06 dropped it; never seeded).
	 */
	private function seed_newsletter(): void {
		$settings = get_option( 'ttm_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$settings['newsletter'] = [
			'provider' => 'custom-url',
			'endpoint' => '',
		];

		update_option( 'ttm_settings', $settings );
		Config::reset();
	}

	/**
	 * Map one raw verse API item to the ttm_verse shape (SPEC Appendix A) via the same parsing
	 * `Verse\Fetcher` uses, adding the `fetched_at` stamp `Fetcher::fetch()` would add.
	 *
	 * @param array<string, mixed> $item Raw API item.
	 * @return array<string, mixed>
	 */
	public static function seed_verse_item( array $item ): array {
		$verse               = Fetcher::parse_item( $item );
		$verse['fetched_at'] = Clock::now()->format( 'Y-m-d H:i:s' );

		return $verse;
	}

	/**
	 * Generate a neutral placeholder PNG (rule 45: never red) at the given images.sizes
	 * dimensions and attach it: a `FIELD`-coloured background, a rotated `BAND` polygon whose
	 * position along the diagonal is deterministic per label, and a 2px `BORDER` rectangle
	 * inset at the edge.
	 *
	 * @param string $label    Alt text / file base name.
	 * @param string $size_key Key into Config images.sizes.
	 * @return int Attachment id, or 0 when GD is unavailable.
	 */
	public function image( string $label, string $size_key ): int {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$sizes              = (array) Config::get( 'images.sizes', [] );
		[ $width, $height ] = $sizes[ $size_key ] ?? [ 800, 600, true ];

		$image = imagecreatetruecolor( $width, $height );
		$field = imagecolorallocate( $image, ...self::FIELD );
		imagefill( $image, 0, 0, $field );

		$this->draw_band( $image, $width, $height, $label );

		$border = imagecolorallocate( $image, ...self::BORDER );
		imagesetthickness( $image, 2 );
		imagerectangle( $image, 1, 1, $width - 2, $height - 2, $border );

		return $this->upload_png( $image, sanitize_title( $label ) . '.png', $label );
	}

	/**
	 * Generate a `ttm-cover` placeholder PNG (rule 45): a `COVER`-coloured 2:3 field, a
	 * `BORDER` rectangle inset at the edge, and the title centred, word-wrapped at 18
	 * characters.
	 *
	 * @param string $title Cover title (also the alt text / file base name).
	 * @return int Attachment id, or 0 when GD is unavailable.
	 */
	public function cover( string $title ): int {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$sizes              = (array) Config::get( 'images.sizes', [] );
		[ $width, $height ] = $sizes['ttm-cover'] ?? [ 600, 900, true ];

		$image = imagecreatetruecolor( $width, $height );
		$bg    = imagecolorallocate( $image, ...self::COVER );
		imagefill( $image, 0, 0, $bg );

		$border = imagecolorallocate( $image, ...self::BORDER );
		imagesetthickness( $image, 2 );
		imagerectangle( $image, 1, 1, $width - 2, $height - 2, $border );

		$text         = imagecolorallocate( $image, ...self::COVER_TEXT );
		$lines        = explode( "\n", wordwrap( $title, 18, "\n", true ) );
		$line_height  = imagefontheight( 5 ) + 6;
		$total_height = count( $lines ) * $line_height;
		$y            = (int) ( ( $height - $total_height ) / 2 );
		foreach ( $lines as $line ) {
			$x = (int) ( ( $width - imagefontwidth( 5 ) * strlen( $line ) ) / 2 );
			imagestring( $image, 5, $x, $y, $line, $text );
			$y += $line_height;
		}

		return $this->upload_png( $image, sanitize_title( $title ) . '-cover.png', $title );
	}

	/**
	 * Draw the diagonal placeholder band (rule 45): a filled polygon roughly 22% of the
	 * shorter side wide, rotated by `seed.image_band_angle`, centred at a point along the
	 * canvas whose horizontal offset is `crc32( $label ) % $width` -- deterministic per label,
	 * not per run (rule 9: no clock in seed content).
	 *
	 * @param \GdImage $image  Target image (mutated in place).
	 * @param int      $width  Canvas width.
	 * @param int      $height Canvas height.
	 * @param string   $label  Deterministic seed for the band's position.
	 */
	private function draw_band( $image, int $width, int $height, string $label ): void {
		$band_color = imagecolorallocate( $image, ...self::BAND );
		$band_width = (int) round( 0.22 * min( $width, $height ) );
		$angle      = deg2rad( (float) Config::get( 'seed.image_band_angle', 30 ) );
		$length     = $width + $height; 
		// Long enough to cross the canvas at any angle.

		$cx = crc32( $label ) % $width;
		$cy = $height / 2;
		$dx = cos( $angle );
		$dy = sin( $angle );
		// Perpendicular unit vector, for the band's thickness.
		$px = -$dy;
		$py = $dx;

		$points = [
			$cx - $dx * $length / 2 - $px * $band_width / 2,
			$cy - $dy * $length / 2 - $py * $band_width / 2,
			$cx + $dx * $length / 2 - $px * $band_width / 2,
			$cy + $dy * $length / 2 - $py * $band_width / 2,
			$cx + $dx * $length / 2 + $px * $band_width / 2,
			$cy + $dy * $length / 2 + $py * $band_width / 2,
			$cx - $dx * $length / 2 + $px * $band_width / 2,
			$cy - $dy * $length / 2 + $py * $band_width / 2,
		];

		imagefilledpolygon( $image, $points, $band_color );
	}

	/**
	 * Encode a GD image as PNG, upload it and attach it to the media library.
	 *
	 * @param \GdImage $image    Image to encode (destroyed by this call).
	 * @param string   $filename Upload filename.
	 * @param string   $label    Alt text / attachment title.
	 * @return int Attachment id, or 0 on failure.
	 */
	private function upload_png( $image, string $filename, string $label ): int {
		ob_start();
		imagepng( $image );
		$data = ob_get_clean();
		imagedestroy( $image );

		$upload = wp_upload_bits( $filename, null, $data );
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => 'image/png',
				'post_title'     => $label,
				'post_status'    => 'inherit',
			],
			$upload['file']
		);

		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $label );
		update_post_meta( $attachment_id, self::SEED_META, 1 );

		return (int) $attachment_id;
	}

	/**
	 * Delete every object carrying the seed meta.
	 */
	/**
	 * `wp ttm seed --reset` (SPEC §6.6 last paragraph, rule 49): return the site to empty,
	 * `wp site empty`-equivalent -- every post of every registered post type (not just post/
	 * page/attachment: `wp_block`, `wp_navigation`, `nav_menu_item`, `wp_template`,
	 * `wp_global_styles`, `custom_css` and anything else registered, e.g. a live import's own
	 * `feedback` rows), every comment, and every non-default category/tag/series term -- not
	 * only rows this seeder itself wrote (Decision "Seeder::reset() from a live state": after a
	 * live import, non-seed content must go too). Guarded by `may_wipe()`; a no-op when the
	 * environment doesn't allow it. `wp_delete_term()` already refuses to delete the default
	 * category on its own, so no special case is needed here; `run()` recreates the seeder's
	 * own navigation afterward. Batched by `cli.batch` throughout (rule 12).
	 */
	public function reset(): void {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( ! self::may_wipe( $environment ) ) {
			return;
		}

		// P0-05: clear stats/top-tags transients before the deleted posts can leave stale data
		// behind for whatever content (seeded or not) remains.
		Stats::flush_all();

		$batch = (int) Config::get( 'cli.batch', 200 );

		foreach ( get_post_types( [], 'names' ) as $post_type ) {
			// 'any' does not include attachments' 'inherit' status (rule 12: still a bounded,
			// explicit status list, not an unlimited page size).
			$status = 'attachment' === $post_type
				? [ 'inherit', 'private', 'publish', 'draft', 'pending', 'future', 'trash' ]
				: 'any';

			do {
				$query = new \WP_Query(
					[
						'post_type'      => $post_type,
						'post_status'    => $status,
						'posts_per_page' => $batch,
						'fields'         => 'ids',
					]
				);
				foreach ( $query->posts as $post_id ) {
					wp_delete_post( (int) $post_id, true );
				}
				$found = count( $query->posts );
			} while ( $found === $batch );
		}//end foreach

		do {
			$comment_ids = get_comments(
				[
					'number' => $batch,
					'fields' => 'ids',
				]
			);
			foreach ( $comment_ids as $comment_id ) {
				wp_delete_comment( (int) $comment_id, true );
			}
			$found = count( $comment_ids );
		} while ( $found === $batch );

		foreach ( [ 'category', 'post_tag', 'series' ] as $taxonomy ) {
			// Batched (rule 12): a term this loop can't delete (the default category) would
			// otherwise reappear in every page and never let the loop terminate, so it also
			// stops once a whole pass deletes nothing.
			do {
				$term_ids = get_terms(
					[
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'fields'     => 'ids',
						'number'     => $batch,
					]
				);
				$found    = count( (array) $term_ids );
				$deleted  = 0;
				foreach ( (array) $term_ids as $term_id ) {
					if ( wp_delete_term( (int) $term_id, $taxonomy ) ) {
						++$deleted;
					}
				}
			} while ( $found === $batch && $deleted > 0 );
		}//end foreach

		delete_option( 'ttm_books' );
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_history' );
		\TTM\Core\Query\SeriesIndex::rebuild();
	}
}
