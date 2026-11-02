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
		delete_option( Cloudflare::LAST_ERROR_OPTION );
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

	/**
	 * @param int    $code HTTP status to answer.
	 * @param string $body JSON body.
	 */
	private function mock_response( int $code, string $body ): void {
		add_filter(
			'pre_http_request',
			static function () use ( $code, $body ) {
				return [
					'headers'  => [],
					'body'     => $body,
					'response' => [
						'code'    => $code,
						'message' => '',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}
		);
	}

	public function test_a_rejected_purge_is_recorded_for_the_admin_notice(): void {
		$this->configure( 'zone123', 'token456' );
		$this->mock_response( 403, '{"success":false,"errors":[{"code":10000,"message":"Authentication error"}]}' );

		Cloudflare::purge( [ home_url( '/' ) ] );

		$last = get_option( Cloudflare::LAST_ERROR_OPTION );
		$this->assertIsArray( $last );
		$this->assertSame( 'HTTP 403: Authentication error', $last['message'] );
		$this->assertNotEmpty( $last['at'] );
	}

	public function test_a_200_with_success_false_is_a_failure(): void {
		$this->configure( 'zone123', 'token456' );
		$this->mock_response( 200, '{"success":false,"errors":[{"message":"purge quota exceeded"}]}' );

		Cloudflare::purge( [ home_url( '/' ) ] );

		$this->assertSame( 'API answered success=false: purge quota exceeded', get_option( Cloudflare::LAST_ERROR_OPTION )['message'] );
	}

	public function test_a_transport_error_is_a_failure(): void {
		$this->configure( 'zone123', 'token456' );
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
			}
		);

		Cloudflare::purge( [ home_url( '/' ) ] );

		$this->assertSame( 'cURL error 28: timed out', get_option( Cloudflare::LAST_ERROR_OPTION )['message'] );
	}

	public function test_a_successful_purge_clears_the_recorded_failure(): void {
		$this->configure( 'zone123', 'token456' );
		update_option(
			Cloudflare::LAST_ERROR_OPTION,
			[
				'at'      => '2026-11-02T00:00:00+00:00',
				'message' => 'HTTP 403',
			],
			false 
		);
		$requests = [];
		$this->mock_success( $requests );

		Cloudflare::purge( [ home_url( '/' ) ] );

		$this->assertFalse( get_option( Cloudflare::LAST_ERROR_OPTION ) );
	}

	public function test_failure_reads_a_response(): void {
		$this->assertNull(
			Cloudflare::failure(
				[
					'response' => [ 'code' => 200 ],
					'body'     => '{"success":true}',
				] 
			) 
		);
		$this->assertSame(
			'HTTP 429',
			Cloudflare::failure(
				[
					'response' => [ 'code' => 429 ],
					'body'     => '',
				] 
			) 
		);
		$this->assertSame(
			'API answered success=false',
			Cloudflare::failure(
				[
					'response' => [ 'code' => 200 ],
					'body'     => 'not json',
				] 
			) 
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
