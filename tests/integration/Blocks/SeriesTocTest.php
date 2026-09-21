<?php
/**
 * Integration tests for the ttm/series-toc block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesTocTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, array{part:int, status?:string, date?:string, title?:string}> $parts
	 * @return array{series_id:int, post_ids:array<int,int>}
	 */
	private function make_series( string $slug, string $name, int $total_parts, array $parts ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );

		$writing  = $this->category_id( 'writing', 'Writing' );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$args = [
				'post_status'   => $part['status'] ?? 'publish',
				'post_category' => [ $writing ],
				'post_title'    => $part['title'] ?? "Chapter {$part['part']}",
				'post_excerpt'  => "Dek for chapter {$part['part']}.",
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
		$html = (string) do_blocks( '<!-- wp:ttm/series-toc' . $json . ' /-->' );

		wp_reset_postdata();

		return $html;
	}

	public function test_lists_parts_in_order_with_current_highlighted_and_unlinked(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
			]
		);

		$html = $this->render( $series['post_ids'][2] );

		$this->assertSame( 3, substr_count( $html, 'ttm-series-toc__item' ) );
		$this->assertStringContainsString( 'is-current', $html );

		// The current part is not linked: its <li> contains no <a>.
		preg_match( '/<li class="ttm-series-toc__item is-current">(.*?)<\/li>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_f24_scheduled_part_unlinked_with_title_date(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[ 'part' => 1 ],
				[
					'part'   => 2,
					'status' => 'future',
					'date'   => '2026-09-26 09:00:00',
				],
			]
		);

		$html = $this->render( $series['post_ids'][1] );

		$this->assertStringContainsString( 'is-scheduled', $html );
		$this->assertStringContainsString( 'title="Scheduled Sept 26"', $html );

		preg_match( '/<li class="ttm-series-toc__item is-scheduled">(.*?)<\/li>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_f23_open_ended_lists_published_only(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'quiet-ledger',
			'The Quiet Ledger',
			0,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[
					'part'   => 3,
					'status' => 'future',
					'date'   => '2026-10-01 09:00:00',
				],
			]
		);

		$html = $this->render( $series['post_ids'][2] );

		$this->assertSame( 2, substr_count( $html, 'ttm-series-toc__item' ) );
		$this->assertStringNotContainsString( 'is-scheduled', $html );
	}

	public function test_chapters_variant_newest_first_limited_with_dek(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
			]
		);

		$html = $this->render(
			$series['post_ids'][1],
			[
				'variant' => 'chapters',
				'order'   => 'desc',
				'limit'   => 2,
				'showDek' => true,
			]
		);

		$this->assertSame( 2, substr_count( $html, 'ttm-series-toc__item' ) );
		$this->assertStringContainsString( 'Dek for chapter 3.', $html );
		$this->assertStringNotContainsString( 'Dek for chapter 1.', $html );

		$pos_3 = strpos( $html, '>03<' );
		$pos_2 = strpos( $html, '>02<' );
		$this->assertNotFalse( $pos_3 );
		$this->assertNotFalse( $pos_2 );
		$this->assertLessThan( $pos_2, $pos_3 );
	}

	public function test_series_id_attribute_overrides_context(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series( 'hardening-wp', 'Hardening WordPress', 2, [ [ 'part' => 1 ], [ 'part' => 2 ] ] );

		$other_post = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$html = $this->render( $other_post, [ 'seriesId' => $series['series_id'] ] );

		$this->assertStringContainsString( 'Hardening WordPress', $html );
		$this->assertSame( 2, substr_count( $html, 'ttm-series-toc__item' ) );
	}

	public function test_no_series_renders_nothing(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$html = $this->render( $post_id );

		$this->assertSame( '', trim( $html ) );
	}
}
