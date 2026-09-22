<?php
/**
 * Integration tests for category.html / category-journal.html / archive.html / search.html (P5-04).
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

class ArchiveTemplatesTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		$_GET = [];
		delete_option( 'ttm_settings' );
		parent::tear_down();
	}

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_category_security_renders_header_stats_filter_year_groups_and_aside(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		$older = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2022-05-01 09:00:00',
				'tags_input'    => [ 'php' ],
			]
		);
		update_post_meta( $older, 'ttm_primary_category', $security );

		$newer = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2026-05-01 09:00:00',
				'tags_input'    => [ 'php' ],
			]
		);
		update_post_meta( $newer, 'ttm_primary_category', $security );
		update_post_meta( $newer, 'ttm_featured_in_section', '1' );

		$this->go_to( (string) get_category_link( $security ) );

		$html = $this->render_template( 'category' );

		$this->assertStringContainsString( 'ttm-archive-head', $html );
		$this->assertStringContainsString( 'ttm-category-stats', $html );
		$this->assertStringContainsString( 'ttm-filter-row', $html );
		$this->assertStringNotContainsString( 'ttm-filter-row-wrap', $html );
		$this->assertMatchesRegularExpression( '/<a class="tag tag-accent" href="[^"]*">All<\/a>/', $html );
		$this->assertStringContainsString( 'ttm-archive-year', $html );
		$this->assertStringContainsString( '2022', $html );
		$this->assertStringContainsString( '2026', $html );
		$this->assertStringContainsString( 'aria-label="Section extras"', $html );
		$this->assertStringContainsString( 'ttm-most-read', $html );
	}

	/**
	 * SPEC §6.6 "Journal archive": the stream rows full width (each one anchor, title first,
	 * Decision "Whole-row links"), no filter row, no 8/4 body, previous/next pagination only.
	 */
	public function test_category_journal_renders_stream_rows(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$journal = $this->category_id( 'journal', 'Journal' );

		$posts = [];
		foreach ( range( 1, 9 ) as $i ) {
			$post = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $journal ],
					'post_date'     => sprintf( '2026-05-%02d 09:00:00', $i ),
				]
			);
			update_post_meta( $post, 'ttm_primary_category', $journal );
			$posts[] = $post;
		}

		$this->go_to( (string) get_category_link( $journal ) );

		$html = $this->render_template( 'category-journal' );

		$this->assertGreaterThanOrEqual( 9, preg_match_all( '/<a href="[^"]+" class="wp-block-group ttm-journal-row[^"]*">/', $html ) );
		$this->assertStringContainsString( 'href="' . get_permalink( $posts[0] ) . '"', $html );
		$this->assertStringContainsString( get_the_title( $posts[0] ), $html );
		$this->assertStringNotContainsString( '<h3 class="wp-block-post-title"><a', $html );
		$this->assertMatchesRegularExpression( '/<a href="[^"]+" class="wp-block-group ttm-journal-row[^"]*">\s*<h3 class="wp-block-post-title">/', $html );
		$this->assertStringNotContainsString( 'ttm-filter-row', $html );
		$this->assertStringNotContainsString( 'ttm-archive-body', $html );
		$this->assertStringNotContainsString( 'wp-block-query-pagination-numbers', $html );
		$this->assertSame( 1, preg_match( '/<main class="([^"]*)"/', $html, $m ) );
		$this->assertStringContainsString( 'ttm-journal-archive', $m[1] );
		$this->assertStringNotContainsString( 'is-layout-constrained', $m[1] );
	}

	public function test_tag_archive_has_no_filter_row_and_keeps_most_read(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$this->go_to( get_tag_link( get_term_by( 'slug', 'php', 'post_tag' ) ) );

		$html = $this->render_template( 'archive' );

		$this->assertStringNotContainsString( 'ttm-filter-row-wrap', $html );
		$this->assertStringContainsString( 'ttm-archive-head', $html );
	}

	/**
	 * SPEC §6.6 "Search": H1 "Search", the summary reads the query and result count via
	 * `ttm/search-summary`, and each row carries a category kicker (Decision "New bindings").
	 */
	public function test_search_template_renders_query_and_rows(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_title'    => 'Findable Cache Article',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$this->go_to( '/?s=Findable' );

		$html = $this->render_template( 'search' );

		$this->assertStringContainsString( 'wp-block-search', $html );
		$this->assertStringContainsString( 'ttm-archive-row', $html );
		$this->assertStringContainsString( 'Findable Cache Article', $html );
		$this->assertMatchesRegularExpression( '/<h1 class="[^"]*is-style-display-xl[^"]*">Search<\/h1>/', $html );
		$this->assertStringContainsString( 'Results for “Findable”', $html );
		$this->assertMatchesRegularExpression( '/<div class="taxonomy-category is-style-kicker wp-block-post-terms"><a[^>]*>Technology<\/a><\/div>/', $html );
	}

	public function test_search_with_no_results_shows_nothing_matched(): void {
		$this->go_to( '/?s=NoSuchThingAnywhere' );

		$html = $this->render_template( 'search' );

		$this->assertStringContainsString( 'Nothing matched “NoSuchThingAnywhere”.', $html );
		$this->assertStringNotContainsString( 'ttm-archive-row', $html );
	}

	/**
	 * Decision "Whole-row links": the row's outer group is the single anchor (link_rows());
	 * the title (isLink: false) is not itself a link and comes first in the DOM.
	 */
	public function test_archive_row_is_single_link_with_title_name(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_title'    => 'Only Row Title',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $security );

		$this->go_to( (string) get_category_link( $security ) );

		$html = $this->render_template( 'category' );

		$this->assertSame( 1, preg_match_all( '/<a href="[^"]+" class="wp-block-group ttm-archive-row[^"]*">/', $html ) );
		$this->assertStringNotContainsString( '<h3 class="wp-block-post-title"><a', $html );
		$this->assertMatchesRegularExpression( '/<a href="[^"]+" class="wp-block-group ttm-archive-row[^"]*">\s*<h3 class="ttm-archive-row__title wp-block-post-title">Only Row Title<\/h3>/', $html );
	}

	/**
	 * Decision "Pagination": the missing side (page 1 has no previous) renders as a disabled,
	 * unlinked span with the literal label, not core's empty string.
	 */
	public function test_pagination_missing_side_renders_disabled_span(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['archive.per_page'] = 1;
				return $config;
			}
		);

		foreach ( [ '2022-05-01 09:00:00', '2026-05-01 09:00:00' ] as $date ) {
			$id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $security ],
					'post_date'     => $date,
				]
			);
			update_post_meta( $id, 'ttm_primary_category', $security );
		}

		$this->go_to( (string) get_category_link( $security ) );

		$html = $this->render_template( 'category' );

		$this->assertMatchesRegularExpression( '/<span class="wp-block-query-pagination-previous is-disabled">← Newer<\/span>/', $html );
		$this->assertStringNotContainsString( 'wp-block-query-pagination-numbers', $html );
	}

	public function test_category_page_two_has_newer_label_with_years(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['archive.per_page'] = 1;
				return $config;
			}
		);

		foreach ( [ '2022-05-01 09:00:00', '2026-05-01 09:00:00' ] as $date ) {
			$id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $security ],
					'post_date'     => $date,
				]
			);
			update_post_meta( $id, 'ttm_primary_category', $security );
		}

		$this->go_to( add_query_arg( 'paged', 2, (string) get_category_link( $security ) ) );

		$html = $this->render_template( 'category' );

		$this->assertStringContainsString( 'Newer', $html );
		$this->assertStringContainsString( '2022', $html );
	}

	/**
	 * SPEC §6.6 "Tag / date archive": the shared header pattern reads "Tag" from
	 * `ttm/archive-kind`, the title has no "Tag:" prefix, and the stats block renders nothing.
	 */
	public function test_tag_archive_header_reads_tag_kicker_and_no_stats(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$this->go_to( get_tag_link( get_term_by( 'slug', 'php', 'post_tag' ) ) );

		$html = $this->render_template( 'archive' );

		$this->assertSame( 1, preg_match( '/<div class="[^"]*ttm-archive-head[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s', $html, $m ) );
		$head = $m[1];
		$this->assertMatchesRegularExpression( '/<p class="is-style-kicker[^"]*">Tag<\/p>/', $head );
		$this->assertMatchesRegularExpression( '/<h1 class="[^"]*is-style-display-xl[^"]*">php<\/h1>/', $head );
		$this->assertStringNotContainsString( 'Tag:', $head );
		$this->assertStringNotContainsString( 'ttm-category-stats', $head );
		$this->assertStringNotContainsString( 'is-layout-constrained', $head );
	}

	/**
	 * SPEC §6.6 "Right aside": the "Series in {Section}" heading reads the queried category
	 * name via `ttm/section-label`, the series rows are the `rail` layout, and Most read is
	 * the shared numbered component.
	 */
	public function test_category_aside_reads_series_in_section_and_numbered_most_read(): void {
		$security = $this->category_id( 'security', 'Security' );

		$term      = wp_insert_term( 'Hardening WordPress', 'series' );
		$series_id = (int) $term['term_id'];
		$part      = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
			]
		);
		update_post_meta( $part, 'ttm_series_part', 1 );
		update_post_meta( $part, 'ttm_primary_category', $security );
		wp_set_object_terms( $part, [ $series_id ], 'series' );
		\TTM\Core\Query\SeriesIndex::rebuild();

		$flagged = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_title'    => 'Flagged Post',
			]
		);
		update_post_meta( $flagged, 'ttm_primary_category', $security );
		update_post_meta( $flagged, 'ttm_featured_in_section', '1' );

		$this->go_to( (string) get_category_link( $security ) );

		$html = $this->render_template( 'category' );

		$this->assertSame( 1, preg_match( '/<aside[^>]*>(.*)<\/aside>/s', $html, $m ) );
		$aside = $m[1];

		$this->assertMatchesRegularExpression( '/<h3 class="wp-block-heading ttm-cell-heading__label">Series in Security<\/h3>/', $aside );
		$this->assertStringContainsString( 'ttm-series-list is-rail', $aside );
		$this->assertStringContainsString( 'Hardening WordPress', $aside );
		$this->assertStringContainsString( 'ttm-numbered__row', $aside );
		$this->assertStringContainsString( 'Flagged Post', $aside );
		$this->assertStringNotContainsString( 'ttm-most-read__item', $aside );
	}
}
