<?php
/**
 * Integration tests for TTM\Core\Cache\Cloudflare.
 *
 * @package TTM\Tests\Integration\Cache
 */

declare( strict_types=1 );

use TTM\Core\Cache\Cloudflare;
use TTM\Core\Config;

class CloudflareTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'ttm_config' );
		Config::reset();
		parent::tear_down();
	}

	private function configure( string $zone, string $token, int $batch = 30 ): void {
		Config::reset();
		add_filter(
			'ttm_config',
			static function ( array $config ) use ( $zone, $token, $batch ): array {
				$config['cache.cloudflare.zone_id']   = $zone;
				$config['cache.cloudflare.api_token'] = $token;
				$config['cache.cloudflare.batch']     = $batch;
				return $config;
			}
		);
	}

	/**
	 * @param array<int, array{url:string, args:array<string, mixed>}> $captured Filled as requests happen.
	 */
	private function mock_success( array &$captured ): void {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$captured ) {
				$captured[] = [
					'url'  => $url,
					'args' => $args,
				];
				return [
					'headers'  => [],
					'body'     => '{"success":true}',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);
	}

	public function test_no_listener_without_constants(): void {
		$this->configure( '', '' );
		$requests = [];
		$this->mock_success( $requests );

		Cloudflare::purge( [ home_url( '/' ) ] );

		$this->assertEmpty( $requests );
		$this->assertFalse( Cloudflare::available() );
	}

	public function test_listener_posts_batches_with_bearer_token(): void {
		$this->configure( 'zone123', 'token456' );
		$requests = [];
		$this->mock_success( $requests );

		Cloudflare::purge( [ home_url( '/' ), home_url( '/series/' ) ] );

		$this->assertCount( 1, $requests );
		$this->assertSame( 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache', $requests[0]['url'] );
		$this->assertSame( 'Bearer token456', $requests[0]['args']['headers']['Authorization'] );

		$body = json_decode( $requests[0]['args']['body'], true );
		$this->assertSame( [ home_url( '/' ), home_url( '/series/' ) ], $body['files'] );
	}

	public function test_urls_are_deduplicated(): void {
		$this->configure( 'zone123', 'token456' );
		$requests = [];
		$this->mock_success( $requests );

		Cloudflare::purge( [ home_url( '/' ), home_url( '/' ), home_url( '/series/' ) ] );

		$body = json_decode( $requests[0]['args']['body'], true );
		$this->assertCount( 2, $body['files'] );
	}
}
