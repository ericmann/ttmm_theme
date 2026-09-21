<?php
/**
 * Proves every ttm/* block renders semantic markup, with no inline styles outside the SPEC
 * rule 2 allow-list, under a default WordPress theme (SPEC §2 separability goal).
 *
 * @package TTM\Tests\Integration\Separability
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class PluginAloneTest extends TTM_IntegrationTestCase {

	private const DEFAULT_THEME = 'twentytwentyfive';

	/**
	 * Every fixture is created inside a test method (never set_up_before_class): WP's own
	 * per-test DB transaction only wraps content created during the test itself, and content
	 * written outside that (e.g. via a shared class fixture in set_up_before_class) survives
	 * tear_down_after_class's _delete_all_data() imperfectly enough to have broken an unrelated
	 * test (StarterContentTest) the first time this file used that shortcut -- see the commit
	 * body for what that looked like.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! wp_get_theme( self::DEFAULT_THEME )->exists() ) {
			$this->markTestSkipped( self::DEFAULT_THEME . ' is not present in this WordPress build.' );
		}

		switch_theme( self::DEFAULT_THEME );
		$this->set_now( '2026-09-20 12:00:00' );
	}

	public function tear_down(): void {
		switch_theme( 'ttm-theme' );
		wp_reset_postdata();
		parent::tear_down();
	}

	private function category( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * A post directly assigned its final category on insert (so Form::on_save()/
	 * PrimaryCategory::on_save() see the real category on the very first save -- no need for
	 * the two-step wp_set_post_categories() dance Seeder::seed_posts() has to do).
	 */
	private function post_in( int $category_id, array $extra = [] ): int {
		$post_id = self::factory()->post->create(
			array_merge(
				[
					'post_status'   => 'publish',
					'post_category' => [ $category_id ],
				],
				$extra
			)
		);
		update_post_meta( $post_id, 'ttm_primary_category', $category_id );

		return $post_id;
	}

	/**
	 * Minimal fixture: enough for every block case below, built fresh per test.
	 *
	 * @return array{tech:int, security:int, writing:int, journal:int, series_id:int, series_post_id:int, journal_post_id:int}
	 */
	private function seed_minimal(): array {
		$tech     = $this->category( 'technology', 'Technology' );
		$security = $this->category( 'security', 'Security' );
		$writing  = $this->category( 'writing', 'Writing' );
		$journal  = $this->category( 'journal', 'Journal' );

		// A 2-part series so series-bar/toc/prev-next/progress/featured/list/stats all have
		// something real to show.
		$term      = wp_insert_term( 'Hardening WordPress', 'series' );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', 2 );

		$part_1 = $this->post_in(
			$security,
			[
				'post_title' => 'Part One',
				'tags_input' => [ 'security-basics' ],
			] 
		);
		update_post_meta( $part_1, 'ttm_series_part', 1 );
		// So ttm/most-read (needs a featured-in-section post) and ttm/tag-filter (needs a
		// tagged post in the category) both have something real to show on /category/security/.
		update_post_meta( $part_1, 'ttm_featured_in_section', '1' );
		wp_set_object_terms( $part_1, [ $series_id ], 'series' );

		$part_2 = $this->post_in( $tech, [ 'post_title' => 'Part Two' ] );
		update_post_meta( $part_2, 'ttm_series_part', 2 );
		wp_set_object_terms( $part_2, [ $series_id ], 'series' );

		SeriesIndex::rebuild();

		// A fiction series (serial-hero picks the in-progress one with the newest part).
		$fiction_term = wp_insert_term( 'The Quiet Ledger', 'series' );
		$fiction_id   = (int) $fiction_term['term_id'];
		update_term_meta( $fiction_id, 'ttm_form', 'novel' );
		update_term_meta( $fiction_id, 'ttm_total_parts', 20 );
		$chapter_1 = $this->post_in( $writing, [ 'post_title' => 'Chapter One' ] );
		update_post_meta( $chapter_1, 'ttm_series_part', 1 );
		wp_set_object_terms( $chapter_1, [ $fiction_id ], 'series' );
		SeriesIndex::rebuild();

		// A standalone Writing story (form is derived, not set, since the category is real on
		// the very first save).
		$this->post_in( $writing, [ 'post_title' => 'A Quiet Field' ] );

		// A print book, no cover needed for the caption to render.
		update_option(
			'ttm_books',
			[
				[
					'title' => 'Salt and Iron',
					'form'  => 'novel',
					'year'  => 2024,
				],
			]
		);

		// A Journal post with syndication data (ttm/syndicated-to).
		$journal_post = $this->post_in( $journal, [ 'post_title' => 'Journal Entry' ] );
		update_post_meta( $journal_post, 'ttm_syndication', [ 'bluesky' => 'https://bsky.app/example' ] );

		update_option(
			'ttm_verse',
			[
				'date'      => '2026-09-20',
				'text'      => 'A short meditation.',
				'reference' => 'Example 1:1',
				'url'       => 'https://dailymedtoday.com/meditation/example',
				'copyright' => '',
			]
		);

		return [
			'tech'            => $tech,
			'security'        => $security,
			'writing'         => $writing,
			'journal'         => $journal,
			'series_id'       => $series_id,
			'series_post_id'  => $part_2,
			'journal_post_id' => $journal_post,
		];
	}

	private function set_up_category_context( int $category_id ): void {
		$this->go_to( (string) get_category_link( $category_id ) );
	}

	private function set_up_post_context( int $post_id ): void {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		if ( $post ) {
			setup_postdata( $post );
		}
	}

	/**
	 * `[block slug, markup, context key: 'series-post'|'journal-post'|'security'|'', expect
	 * non-empty output]`. `__SERIES_ID__` in markup is replaced with the fixture's series term id.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function block_cases(): array {
		return [
			'lead-story'       => [ 'lead-story', '<!-- wp:ttm/lead-story /-->', '', true ],
			'writing-cell'     => [ 'writing-cell', '<!-- wp:ttm/writing-cell /-->', '', true ],
			'newsletter-form'  => [ 'newsletter-form', '<!-- wp:ttm/newsletter-form /-->', '', true ],
			'verse-of-the-day' => [ 'verse-of-the-day', '<!-- wp:ttm/verse-of-the-day /-->', '', true ],
			'serial-hero'      => [ 'serial-hero', '<!-- wp:ttm/serial-hero /-->', '', true ],
			'story-tiles'      => [ 'story-tiles', '<!-- wp:ttm/story-tiles /-->', '', true ],
			'book-grid'        => [ 'book-grid', '<!-- wp:ttm/book-grid /-->', '', true ],
			'series-list'      => [ 'series-list', '<!-- wp:ttm/series-list {"status":"any"} /-->', '', true ],
			'series-stats'     => [ 'series-stats', '<!-- wp:ttm/series-stats /-->', '', true ],
			'series-progress'  => [ 'series-progress', '<!-- wp:ttm/series-progress {"seriesId":__SERIES_ID__} /-->', '', true ],
			'series-featured'  => [ 'series-featured', '<!-- wp:ttm/series-featured {"seriesId":__SERIES_ID__} /-->', '', true ],
			'series-toc'       => [ 'series-toc', '<!-- wp:ttm/series-toc {"seriesId":__SERIES_ID__} /-->', '', true ],
			'series-bar'       => [ 'series-bar', '<!-- wp:ttm/series-bar /-->', 'series-post', true ],
			'series-prev-next' => [ 'series-prev-next', '<!-- wp:ttm/series-prev-next {"mode":"auto"} /-->', 'series-post', true ],
			'syndicated-to'    => [ 'syndicated-to', '<!-- wp:ttm/syndicated-to /-->', 'journal-post', true ],
			'category-stats'   => [ 'category-stats', '<!-- wp:ttm/category-stats /-->', 'security', true ],
			'most-read'        => [ 'most-read', '<!-- wp:ttm/most-read /-->', 'security', true ],
			'tag-filter'       => [ 'tag-filter', '<!-- wp:ttm/tag-filter /-->', 'security', true ],
			'archive-by-year'  => [ 'archive-by-year', '<!-- wp:ttm/archive-by-year --><!-- wp:query {"query":{"perPage":5,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query --><!-- /wp:ttm/archive-by-year -->', 'security', true ],
		];
	}

	/**
	 * @dataProvider block_cases
	 */
	public function test_every_block_renders_semantic_markup_under_default_theme( string $slug, string $markup, string $context, bool $expect_non_empty ): void {
		$fixture = $this->seed_minimal();

		if ( 'series-post' === $context ) {
			$this->set_up_post_context( $fixture['series_post_id'] );
		} elseif ( 'journal-post' === $context ) {
			$this->set_up_post_context( $fixture['journal_post_id'] );
		} elseif ( 'security' === $context ) {
			$this->set_up_category_context( $fixture['security'] );
		}

		$markup = str_replace( '__SERIES_ID__', (string) $fixture['series_id'], $markup );

		$html = (string) do_blocks( $markup );

		$this->assertIsString( $html );

		if ( $expect_non_empty ) {
			$this->assertNotSame( '', trim( $html ), "ttm/{$slug} rendered nothing under the seeded fixture" );
			$this->assertStringContainsString( 'data-ttm-block', $html, "ttm/{$slug} did not wrap its output in the standard block wrapper" );
		}
	}

	public function test_no_inline_styles_outside_allow_list(): void {
		$fixture = $this->seed_minimal();
		$html    = '';

		foreach ( self::block_cases() as [ , $markup, $context ] ) {
			if ( 'series-post' === $context ) {
				$this->set_up_post_context( $fixture['series_post_id'] );
			} elseif ( 'journal-post' === $context ) {
				$this->set_up_post_context( $fixture['journal_post_id'] );
			} elseif ( 'security' === $context ) {
				$this->set_up_category_context( $fixture['security'] );
			}

			$markup = str_replace( '__SERIES_ID__', (string) $fixture['series_id'], $markup );
			$html  .= (string) do_blocks( $markup );
		}

		$this->assertStringNotContainsString( '<style', $html );

		preg_match_all( '/style="([^"]*)"/', $html, $matches );
		foreach ( $matches[1] as $style ) {
			$this->assertMatchesRegularExpression(
				'/^(grid-column|aspect-ratio|--ttm-)/',
				$style,
				"inline style \"{$style}\" is outside the SPEC rule 2 allow-list"
			);
		}
	}
}
