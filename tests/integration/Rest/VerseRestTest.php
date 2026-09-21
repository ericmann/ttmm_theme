<?php
/**
 * Integration tests for the ttm/v1 verse REST route.
 *
 * @package TTM\Tests\Integration\Rest
 */

declare( strict_types=1 );

class VerseRestTest extends TTM_IntegrationTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		delete_option( 'ttm_verse' );
		parent::tear_down();
	}

	public function test_verse_route_is_public_get_and_includes_copyright(): void {
		update_option(
			'ttm_verse',
			[
				'date'      => \TTM\Core\Support\Clock::today(),
				'text'      => 'We wait in hope.',
				'reference' => 'Psalm 33:20',
				'title'     => 'Hope',
				'url'       => 'https://dailymedtoday.com/meditation/abc',
				'source_id' => 'abc',
				'copyright' => 'Copyright notice.',
			]
		);

		$request  = new WP_REST_Request( 'GET', '/ttm/v1/verse' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'We wait in hope.', $data['text'] );
		$this->assertSame( 'Copyright notice.', $data['copyright'] );
	}

	public function test_verse_route_404_when_empty(): void {
		delete_option( 'ttm_verse' );

		$request  = new WP_REST_Request( 'GET', '/ttm/v1/verse' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
