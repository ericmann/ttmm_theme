<?php
/**
 * Unit tests for TTM\Core\Newsletter\Handler.
 *
 * @package TTM\Tests\Unit\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Newsletter;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Newsletter\Handler;
use TTM\Tests\Unit\TestCase;

class HandlerTest extends TestCase {

	/**
	 * In-memory transient store standing in for get_transient()/set_transient().
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	protected function setUp(): void {
		parent::setUp();

		$this->transients = [];

		Functions\when( 'wp_salt' )->alias(
			static fn ( string $scheme = 'auth' ): string => 'unit-test-salt-' . $scheme
		);
		Functions\when( 'wp_unslash' )->alias(
			static fn ( $value ) => is_array( $value ) ? array_map( 'stripslashes', $value ) : stripslashes( (string) $value )
		);
		Functions\when( 'sanitize_email' )->alias(
			static fn ( string $value ): string => (string) filter_var( trim( $value ), FILTER_SANITIZE_EMAIL )
		);
		Functions\when( 'is_email' )->alias(
			static fn ( string $value ) => false !== filter_var( $value, FILTER_VALIDATE_EMAIL )
		);
		Functions\when( 'home_url' )->alias(
			static fn ( string $path = '' ): string => 'https://example.com' . $path
		);
		Functions\when( 'wp_validate_redirect' )->alias(
			static function ( string $location, string $default ): string {
				if ( '' === $location ) {
					return $default;
				}
				return 0 === strpos( $location, 'https://example.com' ) ? $location : $default;
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( string $key, string $value, string $url ): string {
				$sep = false === strpos( $url, '?' ) ? '?' : '&';
				return $url . $sep . $key . '=' . $value;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );

		Functions\when( 'get_transient' )->alias(
			fn ( string $key ) => $this->transients[ $key ] ?? false
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value, int $ttl ): bool {
				unset( $ttl );
				$this->transients[ $key ] = $value;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		Handler::set_forwarder( null );
		parent::tearDown();
	}

	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-09-20 12:00:00', new DateTimeZone( 'UTC' ) );
	}

	public function test_token_accepts_current_and_previous_window_only(): void {
		$now    = $this->now();
		$window = intdiv( $now->getTimestamp(), 86400 );

		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( $window ),
			],
			'1.1.1.1',
			$now
		);
		Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( $window - 1 ),
			],
			'1.1.1.2',
			$now
		);
		Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( $window + 1 ),
			],
			'1.1.1.3',
			$now
		);

		$this->assertCount( 2, $forwarded );
	}

	public function test_honeypot_returns_success_redirect_without_forwarding(): void {
		$now       = $this->now();
		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		$url = Handler::handle(
			[
				'email'       => 'a@example.com',
				'ttm_token'   => Handler::token( intdiv( $now->getTimestamp(), 86400 ) ),
				'ttm_website' => 'filled-by-a-bot',
			],
			'2.2.2.2',
			$now
		);

		$this->assertStringContainsString( 'subscribed=1', $url );
		$this->assertEmpty( $forwarded );
	}

	public function test_rate_limit_blocks_after_configured_attempts(): void {
		$now       = $this->now();
		$token     = Handler::token( intdiv( $now->getTimestamp(), 86400 ) );
		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		for ( $i = 0; $i < 5; $i++ ) {
			Handler::handle(
				[
					'email'     => 'a@example.com',
					'ttm_token' => $token,
				],
				'3.3.3.3',
				$now
			);
		}
		$this->assertCount( 5, $forwarded );

		Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => $token,
			],
			'3.3.3.3',
			$now
		);
		$this->assertCount( 5, $forwarded );
	}

	public function test_invalid_email_returns_success_redirect_without_forwarding(): void {
		$now       = $this->now();
		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		$url = Handler::handle(
			[
				'email'     => 'not-an-email',
				'ttm_token' => Handler::token( intdiv( $now->getTimestamp(), 86400 ) ),
			],
			'4.4.4.4',
			$now
		);

		$this->assertStringContainsString( 'subscribed=1', $url );
		$this->assertEmpty( $forwarded );
	}

	public function test_redirect_never_reflects_posted_values(): void {
		$now = $this->now();

		$url = Handler::handle(
			[ 'redirect_to' => 'https://evil.example/phish' ],
			'5.5.5.5',
			$now
		);

		$this->assertStringStartsWith( 'https://example.com', $url );
		$this->assertStringNotContainsString( 'evil.example', $url );
	}
}
