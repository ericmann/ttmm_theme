<?php
/**
 * Integration tests for the ttm/v1 series and lead REST routes.
 *
 * @package TTM\Tests\Integration\Rest
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesRestTest extends TTM_IntegrationTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	private function make_series( string $status = 'in-progress', string $form = 'nonfiction' ): array {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		update_term_meta( $term_id, 'ttm_status', $status );
		update_term_meta( $term_id, 'ttm_form', $form );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		wp_set_object_terms( $post_id, [ $term_id ], 'series' );
		SeriesIndex::rebuild();

		return [ $term_id, $post_id ];
	}

	public function test_series_list_is_public_and_matches_index(): void {
		[ $term_id ] = $this->make_series();

		$request  = new WP_REST_Request( 'GET', '/ttm/v1/series' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$slugs = array_column( $response->get_data(), 'id' );
		$this->assertContains( $term_id, $slugs );
	}

	public function test_status_filter_narrows(): void {
		$this->make_series( 'complete' );
		$this->make_series( 'in-progress' );

		$request = new WP_REST_Request( 'GET', '/ttm/v1/series' );
		$request->set_param( 'status', 'complete' );
		$response = $this->server->dispatch( $request );

		foreach ( $response->get_data() as $row ) {
			$this->assertSame( 'complete', $row['status'] );
		}
	}

	public function test_form_filter_fiction_matches_non_nonfiction(): void {
		[ $novel_id ]      = $this->make_series( 'in-progress', 'novel' );
		[ $nonfiction_id ] = $this->make_series( 'in-progress', 'nonfiction' );

		$request = new WP_REST_Request( 'GET', '/ttm/v1/series' );
		$request->set_param( 'form', 'fiction' );
		$response = $this->server->dispatch( $request );

		$ids = array_column( $response->get_data(), 'id' );

		$this->assertContains( $novel_id, $ids );
		$this->assertNotContains( $nonfiction_id, $ids );
	}

	public function test_single_series_by_slug_includes_parts(): void {
		[ $term_id ] = $this->make_series();
		$term        = get_term( $term_id, 'series' );

		$request  = new WP_REST_Request( 'GET', "/ttm/v1/series/{$term->slug}" );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data()['parts'] );
	}

	public function test_unknown_slug_is_404(): void {
		$request  = new WP_REST_Request( 'GET', '/ttm/v1/series/does-not-exist' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_scheduled_part_hides_post_id(): void {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$future  = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'future',
				'post_date'   => $future,
			]
		);
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		wp_set_object_terms( $post_id, [ $term_id ], 'series' );

		$published = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $published, 'ttm_series_part', 2 );
		wp_set_object_terms( $published, [ $term_id ], 'series' );
		SeriesIndex::rebuild();

		$term     = get_term( $term_id, 'series' );
		$request  = new WP_REST_Request( 'GET', "/ttm/v1/series/{$term->slug}" );
		$response = $this->server->dispatch( $request );

		$future_part = null;
		foreach ( $response->get_data()['parts'] as $part ) {
			if ( 1 === $part['part'] ) {
				$future_part = $part;
			}
		}

		$this->assertNotNull( $future_part );
		$this->assertSame( 0, $future_part['post_id'] );
	}

	public function test_post_to_series_route_is_404_or_405(): void {
		$request  = new WP_REST_Request( 'POST', '/ttm/v1/series' );
		$response = $this->server->dispatch( $request );

		$this->assertContains( $response->get_status(), [ 404, 405 ] );
	}

	public function test_lead_stub_returns_404(): void {
		$request  = new WP_REST_Request( 'GET', '/ttm/v1/lead' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
