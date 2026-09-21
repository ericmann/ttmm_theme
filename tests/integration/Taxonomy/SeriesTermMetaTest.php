<?php
/**
 * Integration tests for the `series` term meta REST contract.
 *
 * @package TTM\Tests\Integration\Taxonomy
 */

declare( strict_types=1 );

class SeriesTermMetaTest extends TTM_IntegrationTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function test_all_nine_meta_keys_are_registered_with_rest_schema(): void {
		$keys = [
			'ttm_status',
			'ttm_total_parts',
			'ttm_form',
			'ttm_genre',
			'ttm_cadence',
			'ttm_next_date',
			'ttm_cover_id',
			'ttm_featured',
			'ttm_purchase_links',
		];

		$registered = get_registered_meta_keys( 'term', 'series' );

		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $registered, "Missing term meta {$key}" );
			$this->assertNotFalse( $registered[ $key ]['show_in_rest'], "{$key} is not show_in_rest" );
		}
	}

	public function test_rest_update_with_bad_enum_stores_default(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'POST', "/wp/v2/series/{$term_id}" );
		$request->set_body_params( [ 'meta' => [ 'ttm_status' => 'bogus' ] ] );
		$this->server->dispatch( $request );

		$this->assertSame( 'in-progress', get_term_meta( $term_id, 'ttm_status', true ) );
	}

	public function test_rest_update_rejects_javascript_purchase_link(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'POST', "/wp/v2/series/{$term_id}" );
		$request->set_body_params(
			[
				'meta' => [
					'ttm_purchase_links' => [
						[
							'label' => 'Bad',
							'url'   => 'javascript:alert(1)',
						],
						[
							'label' => 'Good',
							'url'   => 'https://example.com/buy',
						],
					],
				],
			]
		);
		$this->server->dispatch( $request );

		$stored = get_term_meta( $term_id, 'ttm_purchase_links', true );

		$this->assertCount( 1, $stored );
		$this->assertSame( 'https://example.com/buy', $stored[0]['url'] );
	}

	public function test_ttm_featured_round_trips_as_boolean(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'POST', "/wp/v2/series/{$term_id}" );
		$request->set_body_params( [ 'meta' => [ 'ttm_featured' => true ] ] );
		$this->server->dispatch( $request );

		$this->assertTrue( (bool) get_term_meta( $term_id, 'ttm_featured', true ) );
	}
}
