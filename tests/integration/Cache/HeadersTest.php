<?php
/**
 * Integration tests for TTM\Core\Cache\Headers and Batcache.
 *
 * @package TTM\Tests\Integration\Cache
 */

declare( strict_types=1 );

use TTM\Core\Cache\Batcache;
use TTM\Core\Cache\Headers;

class HeadersTest extends TTM_IntegrationTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function test_anonymous_front_end_get_sends_public_max_age(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$value = Headers::for_request(
			[
				'admin'     => false,
				'rest'      => false,
				'feed'      => false,
				'logged_in' => false,
				'method'    => 'GET',
			]
		);

		$this->assertSame( 'public, max-age=43200, s-maxage=43200', $value );
	}

	public function test_feed_gets_3600(): void {
		$value = Headers::for_request(
			[
				'admin'     => false,
				'rest'      => false,
				'feed'      => true,
				'logged_in' => false,
				'method'    => 'GET',
			]
		);

		$this->assertSame( 'public, max-age=3600, s-maxage=3600', $value );
	}

	public function test_logged_in_gets_no_store(): void {
		$value = Headers::for_request(
			[
				'admin'     => false,
				'rest'      => false,
				'feed'      => false,
				'logged_in' => true,
				'method'    => 'GET',
			]
		);

		$this->assertSame( 'no-store', $value );
	}

	public function test_post_request_gets_nothing(): void {
		$value = Headers::for_request(
			[
				'admin'     => false,
				'rest'      => false,
				'feed'      => false,
				'logged_in' => false,
				'method'    => 'POST',
			]
		);

		$this->assertNull( $value );
	}

	public function test_rest_ttm_v1_response_carries_header(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		// WP_REST_Server::dispatch() alone doesn't apply 'rest_post_dispatch' -- that filter only
		// runs inside serve_request()/rest_do_request()'s caller chain -- so apply it explicitly,
		// exactly as WP_REST_Server::serve_request() does after dispatch().
		$request  = new WP_REST_Request( 'GET', '/ttm/v1/lead' );
		$response = $this->server->dispatch( $request );
		$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $this->server, $request );

		$headers = $response->get_headers();

		$this->assertArrayHasKey( 'Cache-Control', $headers );
		$this->assertSame( 'public, max-age=43200, s-maxage=43200', $headers['Cache-Control'] );
	}

	public function test_batcache_global_receives_max_age(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$GLOBALS['batcache'] = [];

		Batcache::set_max_age();

		$this->assertSame( 43200, $GLOBALS['batcache']['max_age'] );

		unset( $GLOBALS['batcache'] );
	}
}
