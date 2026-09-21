<?php
/**
 * Integration tests for the ttm/series-bar block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesBarTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, array{part:int, status?:string}> $parts
	 */
	private function make_series( string $slug, string $name, int $total_parts, array $parts ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );

		$writing = $this->category_id( 'writing', 'Writing' );
		$ids     = [];

		foreach ( $parts as $part ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => $part['status'] ?? 'publish',
					'post_category' => [ $writing ],
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $part['part'] );
			update_post_meta( $post_id, 'ttm_primary_category', $writing );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
			$ids[ $part['part'] ] = $post_id;
		}

		SeriesIndex::rebuild();

		return $ids;
	}

	private function render( int $post_id, array $attributes = [] ): string {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		setup_postdata( $post );

		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );
		$html = (string) do_blocks( '<!-- wp:ttm/series-bar' . $json . ' /-->' );

		wp_reset_postdata();

		return $html;
	}

	public function test_renders_square_name_part_of_total_and_segments(): void {
		$ids = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			6,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
			]
		);

		$html = $this->render( $ids[3] );

		$this->assertStringContainsString( 'ttm-series-mark', $html );
		$this->assertStringContainsString( 'Hardening WordPress', $html );
		$this->assertStringContainsString( 'Part 3 of 6', $html );
		$this->assertSame( 6, substr_count( $html, 'ttm-series-bar__seg is-' ) );
	}

	public function test_segment_states_before_current_after(): void {
		$ids = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			4,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
			]
		);

		$html = $this->render( $ids[2] );

		$this->assertSame( 1, substr_count( $html, 'is-done' ) );
		$this->assertSame( 1, substr_count( $html, 'is-current' ) );
		$this->assertSame( 2, substr_count( $html, 'is-todo' ) );
	}

	public function test_f23_open_ended_bar_text_and_trailing_todo_segment(): void {
		$ids = $this->make_series(
			'quiet-ledger',
			'The Quiet Ledger',
			0,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
			]
		);

		$html = $this->render( $ids[2] );

		$this->assertStringContainsString( 'Part 2', $html );
		$this->assertStringNotContainsString( 'Part 2 of', $html );
		$this->assertSame( 3, substr_count( $html, 'ttm-series-bar__seg is-' ) );
		$this->assertSame( 1, substr_count( $html, 'is-done' ) );
		$this->assertSame( 1, substr_count( $html, 'is-current' ) );
		$this->assertSame( 1, substr_count( $html, 'is-todo' ) );
	}

	public function test_f11_no_series_renders_nothing(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$html = $this->render( $post_id );

		$this->assertSame( '', trim( $html ) );
	}

	public function test_preview_state_empty_renders_nothing_in_editor(): void {
		$ids = $this->make_series( 'hardening-wp', 'Hardening WordPress', 6, [ [ 'part' => 1 ] ] );

		set_current_screen( 'post' );

		$html = $this->render( $ids[1], [ 'previewState' => 'empty' ] );

		$this->assertSame( '', trim( $html ) );
	}
}
