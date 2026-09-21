<?php
/**
 * Integration tests for the ttm/series-progress block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesProgressTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, array{part:int, status?:string, date?:string}> $parts
	 * @return array{series_id:int, post_ids:array<int,int>}
	 */
	private function make_series( string $slug, string $name, int $total_parts, array $parts, array $term_meta = [] ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );
		foreach ( $term_meta as $key => $value ) {
			update_term_meta( $series_id, $key, $value );
		}

		$writing  = $this->category_id( 'writing', 'Writing' );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$args = [
				'post_status'   => $part['status'] ?? 'publish',
				'post_category' => [ $writing ],
				'post_title'    => "Chapter {$part['part']}",
			];
			if ( isset( $part['date'] ) ) {
				$args['post_date'] = $part['date'];
			}

			$post_id = self::factory()->post->create( $args );
			update_post_meta( $post_id, 'ttm_series_part', $part['part'] );
			update_post_meta( $post_id, 'ttm_primary_category', $writing );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
			$post_ids[ $part['part'] ] = $post_id;
		}

		SeriesIndex::rebuild();

		return [
			'series_id' => $series_id,
			'post_ids'  => $post_ids,
		];
	}

	private function render( int $post_id, array $attributes = [] ): string {
		global $post;
		$post = $post_id ? get_post( $post_id ) : null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		if ( $post ) {
			setup_postdata( $post );
		}

		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );
		$html = (string) do_blocks( '<!-- wp:ttm/series-progress' . $json . ' /-->' );

		wp_reset_postdata();

		return $html;
	}

	public function test_segments_and_meta_with_next_date(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			6,
			[ [ 'part' => 1 ], [ 'part' => 2 ], [ 'part' => 3 ] ],
			[
				'ttm_next_date' => '2026-09-26',
			]
		);

		$html = $this->render( $series['post_ids'][1] );

		$this->assertSame( 3, substr_count( $html, 'ttm-series-progress__seg is-done' ) );
		$this->assertSame( 3, substr_count( $html, 'ttm-series-progress__seg is-todo' ) );
		$this->assertStringContainsString( '3 of 6 published', $html );
		$this->assertStringContainsString( 'next part Sept 26', $html );
	}

	public function test_complete_series_meta_finished_date(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			4,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
				[
					'part' => 4,
					'date' => '2026-08-19 09:00:00',
				],
			],
			[
				'ttm_status' => 'complete',
			]
		);

		$html = $this->render( $series['post_ids'][1] );

		$this->assertStringContainsString( '4 of 4 published', $html );
		$this->assertStringContainsString( 'finished Aug 19', $html );
	}

	public function test_f23_open_ended_segments_and_meta(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'quiet-ledger',
			'The Quiet Ledger',
			0,
			[ [ 'part' => 1 ], [ 'part' => 2 ] ]
		);

		$html = $this->render( $series['post_ids'][1] );

		$this->assertSame( 2, substr_count( $html, 'ttm-series-progress__seg is-done' ) );
		$this->assertSame( 1, substr_count( $html, 'ttm-series-progress__seg is-todo' ) );
		$this->assertStringContainsString( '2 parts', $html );
	}

	public function test_resolves_queried_series_term(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series( 'hardening-wp', 'Hardening WordPress', 2, [ [ 'part' => 1 ], [ 'part' => 2 ] ] );

		$this->go_to( '/?series=hardening-wp' );

		$html = $this->render( 0 );

		$this->assertStringContainsString( '2 of 2 published', $html );
	}

	public function test_renders_nothing_without_series(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$html = $this->render( $post_id );

		$this->assertSame( '', trim( $html ) );
	}
}
