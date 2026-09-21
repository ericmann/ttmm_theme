<?php
/**
 * Integration tests for the ttm/series-list block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesListTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * Create a series term with one published part dated `$part_date` (SeriesIndex's
	 * `last_update` is now the newest published part's post_date, per R1-03 -- a distinct
	 * date per series is what gives each row a distinct, stable `last_update`), and rebuild
	 * the index.
	 *
	 * @return int Series term id.
	 */
	private function make_series( string $slug, string $name, string $status, string $form, int $category_id, string $part_date = '2026-01-01 09:00:00' ): int {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_status', $status );
		update_term_meta( $series_id, 'ttm_form', $form );

		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $category_id ],
				'post_date'     => $part_date,
			]
		);
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		update_post_meta( $post_id, 'ttm_primary_category', $category_id );
		wp_set_object_terms( $post_id, [ $series_id ], 'series' );

		SeriesIndex::rebuild();

		return $series_id;
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/series-list' . $json . ' /-->' );
	}

	public function test_lists_in_progress_series_limited_and_sorted_by_update(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$this->make_series( 'series-a', 'Series A', 'in-progress', 'nonfiction', $tech, '2026-09-01 00:00:00' );
		$this->make_series( 'series-b', 'Series B', 'in-progress', 'nonfiction', $tech, '2026-09-20 00:00:00' );
		$this->make_series( 'series-c', 'Series C', 'in-progress', 'nonfiction', $tech, '2026-09-10 00:00:00' );

		$html = $this->render( [ 'limit' => 2 ] );

		$pos_b = strpos( $html, 'Series B' );
		$pos_c = strpos( $html, 'Series C' );

		$this->assertNotFalse( $pos_b );
		$this->assertNotFalse( $pos_c );
		$this->assertLessThan( $pos_c, $pos_b );
		$this->assertStringNotContainsString( 'Series A', $html );
	}

	public function test_fiction_filter_excludes_nonfiction(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$this->make_series( 'nonfiction-one', 'Nonfiction One', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'novel-one', 'Novel One', 'in-progress', 'novel', $tech );

		$html = $this->render( [ 'form' => 'fiction' ] );

		$this->assertStringContainsString( 'Novel One', $html );
		$this->assertStringNotContainsString( 'Nonfiction One', $html );
	}

	public function test_in_category_keeps_only_series_touching_the_queried_category(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$this->make_series( 'tech-series', 'Tech Series', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'business-series', 'Business Series', 'in-progress', 'nonfiction', $business );

		global $wp_query;
		$wp_query->queried_object    = get_term( $tech, 'category' );
		$wp_query->queried_object_id = $tech;

		$html = $this->render( [ 'inCategory' => true ] );

		$this->assertStringContainsString( 'Tech Series', $html );
		$this->assertStringNotContainsString( 'Business Series', $html );
	}

	public function test_f4_no_in_progress_falls_back_to_complete_with_empty_heading_attr(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->make_series( 'done-series', 'Done Series', 'complete', 'nonfiction', $tech );

		$html = $this->render();

		$this->assertStringContainsString( 'Done Series', $html );
		$this->assertStringContainsString( 'is-complete', $html );
		$this->assertStringContainsString( 'data-ttm-empty-heading="Series"', $html );
	}

	public function test_zero_series_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	public function test_row_is_single_anchor_with_title_as_name(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->make_series( 'solo-series', 'Solo Series', 'in-progress', 'nonfiction', $tech );

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<a ' ) );

		preg_match( '/<a[^>]*>(.*?)<\/a>/s', $html, $matches );
		$this->assertNotEmpty( $matches );
		$this->assertStringStartsWith( 'Solo Series', trim( wp_strip_all_tags( $matches[1] ) ) );
	}
}
