<?php
/**
 * Tuning verification for newsletter.token_ttl / rate_limit_per_ip / rate_limit_window
 * (SPEC §3.3 rule 20, §6.9).
 *
 * @package TTM\Tests\Unit\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Newsletter;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use TTM\Core\Newsletter\Handler;
use TTM\Tests\Unit\TestCase;

class HandlerTuningTest extends TestCase {

	/**
	 * In-memory transient store standing in for get_transient()/set_transient(); manually
	 * clearable to simulate a transient's TTL having expired.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	protected function setUp(): void {
		parent::setUp();

		$this->transients = [];

		Functions\when( 'wp_salt' )->alias( static fn ( string $scheme = 'auth' ): string => 'tuning-salt-' . $scheme );
		Functions\when( 'wp_unslash' )->alias(
			static fn ( $value ) => is_array( $value ) ? array_map( 'stripslashes', $value ) : stripslashes( (string) $value )
		);
		Functions\when( 'sanitize_email' )->alias( static fn ( string $value ): string => (string) filter_var( trim( $value ), FILTER_SANITIZE_EMAIL ) );
		Functions\when( 'is_email' )->alias( static fn ( string $value ) => false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.com' . $path );
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

		Functions\when( 'get_transient' )->alias( fn ( string $key ) => $this->transients[ $key ] ?? false );
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

	/**
	 * "A page cached one second before a window boundary, viewed at the end of its cache
	 * life" (max_age_cap_seconds == token_ttl == 86400, per P7-05's measurement): the token
	 * baked into that page was computed for the window just before the boundary, and the page
	 * may still be served (and submitted) up to `cap` seconds later, i.e. exactly one window
	 * after generation -- landing precisely on the "previous window" Handler::token_valid()
	 * accepts.
	 */
	public function test_worst_case_token_age_is_accepted(): void {
		$ttl = 86400;
		$cap = 86400;

		$boundary       = 100 * $ttl;
		$token_gen_time = $boundary - 1;
		$view_time      = $token_gen_time + $cap;

		$this->assertSame( 99, intdiv( $token_gen_time, $ttl ), 'sanity: token generated in window 99' );
		$this->assertSame( 100, intdiv( $view_time, $ttl ), 'sanity: viewed in window 100 (one window later)' );

		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		Handler::handle(
			[
				'email'     => 'reader@example.com',
				'ttm_token' => Handler::token( intdiv( $token_gen_time, $ttl ) ),
			],
			'20.0.0.1',
			new DateTimeImmutable( '@' . $view_time )
		);

		$this->assertCount( 1, $forwarded, 'a token exactly one window old (the worst case under a cap == token_ttl) must still be accepted' );
	}

	/**
	 * Simulates 20 submissions from one IP inside a single rate-limit window: exactly
	 * `newsletter.rate_limit_per_ip` (5) are forwarded, the rest are silently dropped.
	 */
	public function test_sixth_submission_in_window_is_not_forwarded(): void {
		$now   = new DateTimeImmutable( '2026-09-20 12:00:00' );
		$token = Handler::token( intdiv( $now->getTimestamp(), 86400 ) );

		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		for ( $i = 0; $i < 20; $i++ ) {
			Handler::handle(
				[
					'email'     => 'reader@example.com',
					'ttm_token' => $token,
				],
				'30.0.0.1',
				$now
			);
		}

		$this->assertCount( 5, $forwarded, '20 submissions in one window from one IP must yield exactly rate_limit_per_ip (5) forwards' );
	}

	/**
	 * Once the rate-limit window has passed (the transient expired), the same IP may submit
	 * again -- simulated here by clearing the in-memory transient store between bursts.
	 */
	public function test_window_reset_allows_again(): void {
		$now   = new DateTimeImmutable( '2026-09-20 12:00:00' );
		$token = Handler::token( intdiv( $now->getTimestamp(), 86400 ) );

		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		for ( $i = 0; $i < 5; $i++ ) {
			Handler::handle(
				[
					'email'     => 'reader@example.com',
					'ttm_token' => $token,
				],
				'40.0.0.1',
				$now
			);
		}
		$this->assertCount( 5, $forwarded );

		// Simulate the rate_limit_window (600s) elapsing: the transient has expired.
		$this->transients = [];

		Handler::handle(
			[
				'email'     => 'reader@example.com',
				'ttm_token' => $token,
			],
			'40.0.0.1',
			$now
		);

		$this->assertCount( 6, $forwarded, 'a submission after the window resets must be forwarded again' );
	}
}
