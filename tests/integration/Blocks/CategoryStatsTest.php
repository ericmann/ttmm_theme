<?php
/**
 * Integration tests for the ttm/category-stats block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class CategoryStatsTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function render(): string {
		return (string) do_blocks( '<!-- wp:ttm/category-stats /-->' );
	}

	public function test_renders_count_year_range_series_line_and_feed_link(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2020-01-01 09:00:00',
			] 
		);
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2024-01-01 09:00:00',
			] 
		);

		$series_post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2023-01-01 09:00:00',
			] 
		);
		update_post_meta( $series_post, 'ttm_series_part', 1 );
		$term = wp_insert_term( 'Hardening WordPress', 'series' );
		wp_set_object_terms( $series_post, [ (int) $term['term_id'] ], 'series' );
		SeriesIndex::rebuild();

		$this->go_to( (string) get_category_link( $tech ) );

		$html = $this->render();

		$this->assertStringContainsString( '3 articles', $html );
		$this->assertStringContainsString( '2020–2024', $html );
		$this->assertStringContainsString( '1 series touches this section', $html );
		$this->assertStringContainsString( 'Technology RSS', $html );
	}

	public function test_single_year_and_zero_series_omit_range_and_series_line(): void {
		$business = $this->category_id( 'business', 'Business' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $business ],
				'post_date'     => '2026-01-01 09:00:00',
			] 
		);

		$this->go_to( (string) get_category_link( $business ) );

		$html = $this->render();

		$this->assertStringContainsString( '1 article', $html );
		$this->assertStringContainsString( '2026', $html );
		$this->assertStringNotContainsString( '–', $html );
		$this->assertStringNotContainsString( 'series touch', $html );
	}

	public function test_renders_nothing_outside_category(): void {
		$this->go_to( '/' );

		$this->assertSame( '', trim( $this->render() ) );
	}
}
