<?php
/**
 * Integration tests for the ttm/series-stats block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesStatsTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function make_series( string $slug, string $name, string $category_slug, string $category_name, string $status ): void {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_status', $status );

		$category_id = $this->category_id( $category_slug, $category_name );

		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $category_id ],
			]
		);
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		update_post_meta( $post_id, 'ttm_primary_category', $category_id );
		wp_set_object_terms( $post_id, [ $series_id ], 'series' );

		SeriesIndex::rebuild();
	}

	private function render(): string {
		return (string) do_blocks( '<!-- wp:ttm/series-stats /-->' );
	}

	public function test_counts_and_spanning_line_in_nav_order(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$this->make_series( 'hardening-wp', 'Hardening WordPress', 'technology', 'Technology', 'in-progress' );
		$this->make_series( 'quiet-ledger', 'The Quiet Ledger', 'security', 'Security', 'complete' );
		$this->make_series( 'faith-notes', 'Faith Notes', 'faith', 'Faith', 'in-progress' );

		$html = $this->render();

		$this->assertStringContainsString( '3 series', $html );
		$this->assertStringContainsString( '2 in progress', $html );
		$this->assertStringContainsString( 'Spanning', $html );

		// Config::defaults() sections.order is technology, business, faith, journal, writing,
		// security, opinion — so Faith precedes Security in the spanning line.
		$pos_tech     = strpos( $html, 'Technology' );
		$pos_faith    = strpos( $html, 'Faith' );
		$pos_security = strpos( $html, 'Security' );

		$this->assertNotFalse( $pos_tech );
		$this->assertNotFalse( $pos_faith );
		$this->assertNotFalse( $pos_security );
		$this->assertLessThan( $pos_faith, $pos_tech );
		$this->assertLessThan( $pos_security, $pos_faith );
	}

	public function test_zero_series_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}
}
