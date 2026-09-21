<?php
/**
 * Integration tests for TTM\Core\Taxonomy\SeriesAdmin.
 *
 * @package TTM\Tests\Integration\Taxonomy
 */

declare( strict_types=1 );

use TTM\Core\Taxonomy\SeriesAdmin;

class SeriesAdminTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		$_POST    = [];
		$_REQUEST = [];
		parent::tear_down();
	}

	public function test_save_handler_ignores_post_without_nonce(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$_POST = [ 'ttm_status' => 'complete' ];
		SeriesAdmin::save( $term_id );

		$this->assertSame( 'in-progress', get_term_meta( $term_id, 'ttm_status', true ) );
	}

	public function test_save_handler_sanitizes_and_stores_all_fields(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$_POST = [
			'_wpnonce'           => wp_create_nonce( 'update-tag_' . $term_id ),
			'ttm_status'         => 'complete',
			'ttm_total_parts'    => '6',
			'ttm_form'           => 'novel',
			'ttm_genre'          => 'Sci-fi',
			'ttm_cadence'        => 'weekly',
			'ttm_next_date'      => '2026-12-25',
			'ttm_cover_id'       => '0',
			'ttm_featured'       => '1',
			'ttm_purchase_links' => [
				[
					'label' => 'Amazon',
					'url'   => 'https://amazon.com/x',
				],
			],
		];

		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test fixture mirrors $_POST into $_REQUEST for check_admin_referer(); not request handling.

		SeriesAdmin::save( $term_id );

		$this->assertSame( 'complete', get_term_meta( $term_id, 'ttm_status', true ) );
		$this->assertSame( 6, (int) get_term_meta( $term_id, 'ttm_total_parts', true ) );
		$this->assertSame( 'novel', get_term_meta( $term_id, 'ttm_form', true ) );
		$this->assertSame( 'Sci-fi', get_term_meta( $term_id, 'ttm_genre', true ) );
		$this->assertSame( 'weekly', get_term_meta( $term_id, 'ttm_cadence', true ) );
		$this->assertSame( '2026-12-25', get_term_meta( $term_id, 'ttm_next_date', true ) );
		$this->assertTrue( (bool) get_term_meta( $term_id, 'ttm_featured', true ) );
		$links = get_term_meta( $term_id, 'ttm_purchase_links', true );
		$this->assertSame( 'https://amazon.com/x', $links[0]['url'] );
	}

	public function test_part_list_renders_parts_in_order(): void {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		wp_set_object_terms( $post_id, [ $term_id ], 'series' );
		\TTM\Core\Query\SeriesIndex::rebuild();

		$term = get_term( $term_id, 'series' );

		ob_start();
		SeriesAdmin::edit_form_fields( $term );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<td>1</td>', $html );
	}

	public function test_columns_show_status_and_parts(): void {
		$columns = SeriesAdmin::columns( [ 'name' => 'Name' ] );

		$this->assertArrayHasKey( 'ttm_status', $columns );
		$this->assertArrayHasKey( 'ttm_form', $columns );
		$this->assertArrayHasKey( 'ttm_parts', $columns );

		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		update_term_meta( $term_id, 'ttm_status', 'complete' );

		$this->assertSame( 'complete', SeriesAdmin::column_content( '', 'ttm_status', $term_id ) );
	}
}
