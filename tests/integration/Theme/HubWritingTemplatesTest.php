<?php
/**
 * Integration tests for page-series.html / taxonomy-series.html / page-writing.html (P6-05).
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class HubWritingTemplatesTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		update_option( 'ttm_books', [] );
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

	private function attachment(): int {
		return self::factory()->attachment->create_object(
			[
				'file'           => 'cover.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			]
		);
	}

	/**
	 * @param array<int, array{part:int, status?:string, date?:string}> $parts
	 * @return array{series_id:int, post_ids:array<int,int>}
	 */
	private function make_series( string $slug, string $name, int $total_parts, array $parts, array $term_meta = [], string $category_slug = 'security' ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );
		foreach ( $term_meta as $key => $value ) {
			update_term_meta( $series_id, $key, $value );
		}

		$category = $this->category_id( $category_slug, ucfirst( $category_slug ) );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => $part['status'] ?? 'publish',
					'post_category' => [ $category ],
					'post_title'    => "Chapter {$part['part']}",
					'post_date'     => $part['date'] ?? '2026-01-01 09:00:00',
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $part['part'] );
			update_post_meta( $post_id, 'ttm_primary_category', $category );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
			$post_ids[ $part['part'] ] = $post_id;
		}

		SeriesIndex::rebuild();

		return [
			'series_id' => $series_id,
			'post_ids'  => $post_ids,
		];
	}

	public function test_series_index_page_renders_stats_featured_and_all_series_grid(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series( 'hardening-wp', 'Hardening WordPress', 6, [ [ 'part' => 1 ] ] );
		$this->make_series( 'reading-cves', 'Reading CVEs', 4, [ [ 'part' => 4 ] ], [ 'ttm_status' => 'complete' ] );

		$html = $this->render_template( 'page-series' );

		$this->assertStringContainsString( 'ttm-hub-head', $html );
		$this->assertStringContainsString( '2 series', $html );
		$this->assertStringContainsString( 'ttm-series-featured', $html );
		$this->assertStringContainsString( 'ttm-hub-all', $html );
		$this->assertStringContainsString( 'ttm-series-row', $html );
	}

	public function test_single_series_renders_full_part_list_and_other_series_excluding_itself(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$featured = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[ [ 'part' => 1 ], [ 'part' => 2 ], [ 'part' => 3 ] ]
		);
		$this->make_series( 'reading-cves', 'Reading CVEs', 4, [ [ 'part' => 4 ] ], [ 'ttm_status' => 'complete' ] );

		$term = get_term( $featured['series_id'], 'series' );
		$this->go_to( (string) get_term_link( $term ) );

		$html = $this->render_template( 'taxonomy-series' );

		$this->assertStringContainsString( 'Hardening WordPress', $html );
		$this->assertSame( 3, substr_count( $html, 'ttm-series-featured__part ' ) );
		$this->assertStringNotContainsString( 'All 3 →', $html );
		$this->assertStringContainsString( 'Other series', $html );
		$this->assertStringContainsString( 'Reading CVEs', $html );
		$this->assertSame( 1, substr_count( $html, 'class="ttm-series-row"' ) );
	}

	public function test_writing_page_renders_hero_serials_chapters_tiles_and_books(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'the-quiet-ledger',
			'The Quiet Ledger',
			31,
			[ [ 'part' => 1 ], [ 'part' => 2 ] ],
			[ 'ttm_form' => 'novel' ],
			'writing'
		);

		$story_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'A Quiet Field',
			]
		);
		update_post_meta( $story_id, 'ttm_form', 'story' );
		update_post_meta( $story_id, 'ttm_word_count', 3000 );

		update_option(
			'ttm_books',
			[
				[
					'title' => 'Salt and Iron',
					'form'  => 'novel',
					'year'  => 2022,
				],
			]
		);

		$page_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'writing',
				'post_title' => 'Writing',
			]
		);
		$this->go_to( (string) get_permalink( $page_id ) );

		global $post;
		$post = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real page render context.
		setup_postdata( $post );

		$html = $this->render_template( 'page-writing' );

		wp_reset_postdata();

		$this->assertStringContainsString( 'ttm-serial-hero', $html );
		$this->assertStringContainsString( 'The Quiet Ledger', $html );
		$this->assertStringContainsString( 'All serials', $html );
		$this->assertStringContainsString( 'Recent chapters', $html );
		$this->assertStringContainsString( 'ttm-story-tiles', $html );
		$this->assertStringContainsString( 'A Quiet Field', $html );
		$this->assertStringContainsString( 'ttm-book-grid', $html );
		$this->assertStringContainsString( 'Salt and Iron', $html );
	}

	public function test_writing_category_archive_uses_page_writing_template(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$this->go_to( (string) get_category_link( $writing ) );

		$hierarchy = apply_filters( 'category_template_hierarchy', [ 'category.php' ] );

		$this->assertSame( 'page-writing', $hierarchy[0] );
	}

	public function test_writing_category_archive_has_one_h1(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'the-quiet-ledger',
			'The Quiet Ledger',
			31,
			[ [ 'part' => 1 ] ],
			[ 'ttm_form' => 'novel' ],
			'writing'
		);

		$writing = $this->category_id( 'writing', 'Writing' );
		$this->go_to( (string) get_category_link( $writing ) );

		$html = $this->render_template( 'page-writing' );

		$this->assertSame( 1, substr_count( $html, '<h1' ) );
		$this->assertStringContainsString( '<h1 class="ttm-serial-hero__title', $html );
	}

	public function test_cover_shadow_class_only_in_hero(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$attachment_id = $this->attachment();
		$this->make_series(
			'the-quiet-ledger',
			'The Quiet Ledger',
			31,
			[ [ 'part' => 1 ] ],
			[
				'ttm_cover_id' => $attachment_id,
				'ttm_form'     => 'novel',
			],
			'writing'
		);

		update_option(
			'ttm_books',
			[
				[
					'title'    => 'Another Book',
					'form'     => 'novel',
					'year'     => 2020,
					'cover_id' => $attachment_id,
				],
			]
		);

		$page_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'writing',
				'post_title' => 'Writing',
			]
		);
		$this->go_to( (string) get_permalink( $page_id ) );

		global $post;
		$post = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real page render context.
		setup_postdata( $post );

		$html = $this->render_template( 'page-writing' );

		wp_reset_postdata();

		$this->assertStringContainsString( 'ttm-cover is-hero', $html );
		$this->assertSame( 1, substr_count( $html, 'is-hero' ) );
	}

	public function test_empty_state_writing_page_omits_tiles_and_books(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$page_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'writing',
				'post_title' => 'Writing',
			]
		);
		$this->go_to( (string) get_permalink( $page_id ) );

		global $post;
		$post = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real page render context.
		setup_postdata( $post );

		$html = $this->render_template( 'page-writing' );

		wp_reset_postdata();

		$this->assertStringNotContainsString( 'ttm-story-tiles', $html );
		$this->assertStringNotContainsString( 'ttm-book-grid', $html );
	}
}
