<?php
/**
 * Integration tests for TTM\Core\Editor\Columns.
 *
 * @package TTM\Tests\Integration\Editor
 */

declare( strict_types=1 );

use TTM\Core\Editor\Columns;
use TTM\Core\Query\SeriesIndex;

class ColumnsTest extends TTM_IntegrationTestCase {

	public function test_columns_are_added_in_order(): void {
		$columns = Columns::add_columns(
			[
				'cb'    => '',
				'title' => 'Title',
				'date'  => 'Date',
			] 
		);
		$keys    = array_keys( $columns );

		$title_pos = array_search( 'title', $keys, true );
		$this->assertSame( 'ttm_primary', $keys[ $title_pos + 1 ] );
		$this->assertSame( 'ttm_series', $keys[ $title_pos + 2 ] );
		$this->assertSame( 'ttm_words', $keys[ $title_pos + 3 ] );
	}

	public function test_series_column_shows_name_and_part(): void {
		$series  = self::factory()->term->create(
			[
				'taxonomy' => 'series',
				'name'     => 'Hardening WordPress',
			] 
		);
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'ttm_series_part', 3 );
		wp_set_object_terms( $post_id, [ $series ], 'series' );
		SeriesIndex::rebuild();

		ob_start();
		Columns::render( 'ttm_series', $post_id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Hardening WordPress', $html );
		$this->assertStringContainsString( '(3)', $html );
	}

	public function test_words_column_sorts_by_meta_in_admin(): void {
		set_current_screen( 'edit-post' );

		$a = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$b = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $a, 'ttm_word_count', 100 );
		update_post_meta( $b, 'ttm_word_count', 500 );

		$query = new WP_Query(
			[
				'post_type' => 'post',
				'orderby'   => 'ttm_words',
				'order'     => 'DESC',
				'fields'    => 'ids',
			]
		);

		$this->assertSame( $b, (int) $query->posts[0] );
	}
}
