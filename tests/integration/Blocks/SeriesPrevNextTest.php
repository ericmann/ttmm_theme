<?php
/**
 * Integration tests for the ttm/series-prev-next block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesPrevNextTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, array{part:int, title?:string}> $parts
	 * @return array<int,int> part => post id
	 */
	private function make_series( string $slug, string $name, array $parts ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];

		$writing  = $this->category_id( 'writing', 'Writing' );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $writing ],
					'post_title'    => $part['title'] ?? "Chapter {$part['part']}",
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $part['part'] );
			update_post_meta( $post_id, 'ttm_primary_category', $writing );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
			$post_ids[ $part['part'] ] = $post_id;
		}

		SeriesIndex::rebuild();

		return $post_ids;
	}

	private function render( int $post_id, array $attributes = [] ): string {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		setup_postdata( $post );

		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );
		$html = (string) do_blocks( '<!-- wp:ttm/series-prev-next' . $json . ' /-->' );

		wp_reset_postdata();

		return $html;
	}

	public function test_series_mode_links_previous_and_next_parts_with_labels(): void {
		$ids = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			[ [ 'part' => 1 ], [ 'part' => 2 ], [ 'part' => 3 ] ]
		);

		$html = $this->render( $ids[2], [ 'mode' => 'series' ] );

		$this->assertStringContainsString( '← Part 1', $html );
		$this->assertStringContainsString( 'Part 3 →', $html );
		$this->assertStringContainsString( 'Chapter 1', $html );
		$this->assertStringContainsString( 'Chapter 3', $html );
	}

	/**
	 * SPEC §6.2 "Prev/next": a missing side keeps its cell (and so the grid and rules) -- here
	 * the first part, which has no previous.
	 */
	public function test_missing_side_keeps_empty_cell(): void {
		$ids = $this->make_series( 'hardening-wp', 'Hardening WordPress', [ [ 'part' => 1 ], [ 'part' => 2 ] ] );

		$html = $this->render( $ids[1] );

		$this->assertMatchesRegularExpression( '/<div class="ttm-prevnext__prev">\s*<\/div>/', $html );
		$this->assertStringContainsString( 'class="ttm-prevnext__next"', $html );
		$this->assertStringContainsString( 'Part 2 →', $html );
	}

	public function test_last_part_keeps_empty_next_cell(): void {
		$ids = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			[ [ 'part' => 1 ], [ 'part' => 2 ] ]
		);

		$html = $this->render( $ids[2], [ 'mode' => 'series' ] );

		$this->assertStringContainsString( 'ttm-prevnext__next', $html );

		preg_match( '/<div class="ttm-prevnext__next">(.*?)<\/div>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_f11_chronological_within_primary_category(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$older = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-10 09:00:00',
			]
		);
		update_post_meta( $older, 'ttm_primary_category', $tech );

		$current = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-15 09:00:00',
			]
		);
		update_post_meta( $current, 'ttm_primary_category', $tech );

		$newer = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-18 09:00:00',
			]
		);
		update_post_meta( $newer, 'ttm_primary_category', $tech );

		$html = $this->render( $current );

		$this->assertStringContainsString( '← Previously in Technology', $html );
		$this->assertStringContainsString( 'Next →', $html );
	}

	public function test_chronological_skips_posts_whose_primary_is_elsewhere(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$matching_older = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-01 09:00:00',
				'post_title'    => 'Matching Older',
			]
		);
		update_post_meta( $matching_older, 'ttm_primary_category', $tech );

		$closer_but_other = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $business ],
				'post_date'     => '2026-09-12 09:00:00',
				'post_title'    => 'Closer But Other',
			]
		);
		update_post_meta( $closer_but_other, 'ttm_primary_category', $business );

		$current = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-15 09:00:00',
			]
		);
		update_post_meta( $current, 'ttm_primary_category', $tech );

		$html = $this->render( $current );

		$this->assertStringContainsString( 'Matching Older', $html );
		$this->assertStringNotContainsString( 'Closer But Other', $html );
	}

	public function test_auto_prefers_series(): void {
		$ids = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			[ [ 'part' => 1 ], [ 'part' => 2 ] ]
		);

		$html = $this->render( $ids[2] );

		$this->assertStringContainsString( '← Part 1', $html );
	}

	/**
	 * P1-02, F11: three Technology-primary posts outside any series, the middle one rendered
	 * -- both neighbour titles and both labels.
	 */
	public function test_f11_chronological_pair_asserts_both_titles_and_labels(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$older = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-10 09:00:00',
				'post_title'    => 'Older Post',
			]
		);
		update_post_meta( $older, 'ttm_primary_category', $tech );

		$current = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-15 09:00:00',
			]
		);
		update_post_meta( $current, 'ttm_primary_category', $tech );

		$newer = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-18 09:00:00',
				'post_title'    => 'Newer Post',
			]
		);
		update_post_meta( $newer, 'ttm_primary_category', $tech );

		$html = $this->render( $current );

		$this->assertStringContainsString( '← Previously in Technology', $html );
		$this->assertStringContainsString( 'Next →', $html );
		$this->assertStringContainsString( 'Older Post', $html );
		$this->assertStringContainsString( 'Newer Post', $html );
	}

	/**
	 * P1-02, SPEC §6.3: chronology ignores series membership -- a series part sharing the
	 * post's primary category is a valid chronological neighbour.
	 */
	public function test_chronological_neighbour_may_be_a_series_part(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$series_term = wp_insert_term( 'Hardening WordPress', 'series', [ 'slug' => 'hardening-wp' ] );
		$series_id   = (int) $series_term['term_id'];

		$series_part = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-10 09:00:00',
				'post_title'    => 'Series Part One',
			]
		);
		update_post_meta( $series_part, 'ttm_primary_category', $tech );
		update_post_meta( $series_part, 'ttm_series_part', 1 );
		wp_set_object_terms( $series_part, [ $series_id ], 'series' );
		SeriesIndex::rebuild();

		$current = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-15 09:00:00',
			]
		);
		update_post_meta( $current, 'ttm_primary_category', $tech );

		$html = $this->render( $current );

		$this->assertStringContainsString( 'Series Part One', $html );
	}
}
