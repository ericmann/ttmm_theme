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
	}

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

		// One archive row for the single seeded post: exactly one linked title anchor.
		$this->assertSame( 1, substr_count( $html, 'class="wp-block-post-title"><a' ) );
		$this->assertStringContainsString( '>Only Row Title</a>', $html );
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
}
