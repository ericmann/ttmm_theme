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
		// Production by default so `CustomUrl::dev_accept_applies()` never suppresses the
		// forwarder in the tests below that exercise the traditional forward path; the
		// dev-accept-specific tests override this to a non-production value.
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );

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
		// The `\Jetpack` stub below is a real global class once any test in this process
		// declares it (class_alias can't be undone); reset its toggle so tests that never call
		// configure_jetpack_provider() -- in this file or any other test file sharing the same
		// PHPUnit process -- keep seeing an unavailable Jetpack provider.
		if ( class_exists( '\Jetpack', false ) ) {
			\Jetpack::$ready = false;
		}
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

	public function test_dev_accept_skips_forward_and_redirects_with_subscribed(): void {
		Functions\when( 'wp_get_environment_type' )->justReturn( 'local' );

		$now       = $this->now();
		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		$url = Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( intdiv( $now->getTimestamp(), 86400 ) ),
			],
			'6.6.6.6',
			$now
		);

		$this->assertStringContainsString( 'subscribed=1', $url );
		$this->assertEmpty( $forwarded, 'The forwarder must not be called when dev_accept applies.' );
	}

	public function test_dev_accept_never_applies_in_production(): void {
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );

		$now       = $this->now();
		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( intdiv( $now->getTimestamp(), 86400 ) ),
			],
			'7.7.7.7',
			$now
		);

		$this->assertCount( 1, $forwarded, 'The forwarder must still be called in production, even with an empty endpoint.' );
	}

	/**
	 * Makes `Providers::current()` resolve to `jetpack` without touching `WP_Block_Type_Registry`
	 * (never loaded in this WordPress-free suite): the stub global `Jetpack` class below (its
	 * `is_connection_ready()` returns true) short-circuits `Provider\Jetpack::available()` before
	 * it gets there.
	 */
	private function configure_jetpack_provider(): void {
		\Jetpack::$ready                     = true;
		\Jetpack_Subscriptions::$next_result = true;
		\Jetpack_Subscriptions::$subscribed  = [];

		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				if ( 'ttm_config' === $tag ) {
					$value['newsletter.provider'] = 'jetpack';
				}
				return $value;
			}
		);
		Functions\when( 'is_wp_error' )->alias(
			static fn ( $thing ): bool => $thing instanceof \WP_Error
		);
	}

	public function test_jetpack_provider_subscribes_through_jetpack_api_not_forward(): void {
		$this->configure_jetpack_provider();

		$now       = $this->now();
		$forwarded = [];
		Handler::set_forwarder(
			static function ( string $email ) use ( &$forwarded ): void {
				$forwarded[] = $email;
			}
		);

		$url = Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( intdiv( $now->getTimestamp(), 86400 ) ),
			],
			'8.8.8.8',
			$now
		);

		$this->assertStringContainsString( 'subscribed=1', $url );
		$this->assertSame( [ 'a@example.com' ], \Jetpack_Subscriptions::$subscribed );
		$this->assertEmpty( $forwarded, 'The custom-url forwarder must never be called for the jetpack provider.' );
	}

	public function test_jetpack_subscribe_failure_redirects_with_error(): void {
		$this->configure_jetpack_provider();
		\Jetpack_Subscriptions::$next_result = new \WP_Error( 'subscribe_failed', 'nope' );

		$now = $this->now();

		$url = Handler::handle(
			[
				'email'     => 'a@example.com',
				'ttm_token' => Handler::token( intdiv( $now->getTimestamp(), 86400 ) ),
			],
			'9.9.9.9',
			$now
		);

		$this->assertStringContainsString( 'subscribed=1', $url, 'A failed Jetpack subscribe still redirects the visitor to the same success URL (never an oracle).' );
	}
}

// The three classes below stand in for real WordPress/Jetpack globals this WordPress-free suite
// never loads. `class_exists()`/`instanceof` checks in the code under test use a leading
// backslash (global namespace), so each stub is declared here (namespaced, like everything else
// in this file) and then `class_alias()`'d onto its real, global name -- the standard way to
// hand a namespaced file's PHPUnit process a global-namespace stub without mixing bracketed and
// unbracketed namespace syntax in one file.
if ( ! class_exists( '\Jetpack' ) ) {
	/**
	 * Test-only stand-in for the real `\Jetpack` class: only `is_connection_ready()`, the method
	 * `Provider\Jetpack::available()` calls.
	 */
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test-only global stubs of third-party classes, deliberately kept together.
	class JetpackConnectionStub {
		/**
		 * Off by default so tests that never call `configure_jetpack_provider()` see an
		 * unavailable Jetpack, regardless of which test in the process declares this stub first.
		 *
		 * @var bool
		 */
		public static bool $ready = false;

		public static function is_connection_ready(): bool {
			return self::$ready;
		}
	}

	class_alias( __NAMESPACE__ . '\JetpackConnectionStub', 'Jetpack' );
}

if ( ! class_exists( '\WP_Error' ) ) {
	/**
	 * Test-only stand-in for WordPress's `WP_Error`, just enough for `is_wp_error()` (stubbed
	 * alongside it in `configure_jetpack_provider()`) to recognise an instance.
	 */
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test-only global stubs of third-party classes, deliberately kept together.
	class WpErrorStub {
		public function __construct( string $code = '', string $message = '' ) {
			unset( $code, $message );
		}
	}

	class_alias( __NAMESPACE__ . '\WpErrorStub', 'WP_Error' );
}

if ( ! class_exists( '\Jetpack_Subscriptions' ) ) {
	/**
	 * Test-only stand-in for Jetpack's own `Jetpack_Subscriptions`: records every `subscribe()`
	 * call and returns `$next_result` (settable per test), matching the real
	 * `widget_submit()`-called method's `subscribe( $email, $blog_id, $flag )` signature.
	 */
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test-only global stubs of third-party classes, deliberately kept together.
	class JetpackSubscriptionsStub {
		/**
		 * @var mixed
		 */
		public static $next_result = true;

		/**
		 * @var string[]
		 */
		public static array $subscribed = [];

		public static function init(): self {
			return new self();
		}

		/**
		 * @param string $email   Submitted email.
		 * @param int    $blog_id Unused; matches the real signature.
		 * @param bool   $flag    Unused; matches the real signature.
		 * @return mixed
		 */
		public function subscribe( string $email, int $blog_id, bool $flag ) {
			unset( $blog_id, $flag );
			self::$subscribed[] = $email;
			return self::$next_result;
		}
	}

	class_alias( __NAMESPACE__ . '\JetpackSubscriptionsStub', 'Jetpack_Subscriptions' );
}

if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
	/**
	 * Test-only stand-in for WordPress's `WP_Block_Type_Registry`: every test that reaches
	 * `Handler::handle()` runs `Providers::current()`, which falls through to
	 * `Provider\Jetpack::available()`'s `\WP_Block_Type_Registry::get_instance()->is_registered()`
	 * check whenever the `\Jetpack` stub above isn't "ready" -- i.e. every test that doesn't call
	 * `configure_jetpack_provider()`. Always registered as not-registered; nothing in this suite
	 * needs `jetpack/subscriptions` to appear registered here.
	 */
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test-only global stubs of third-party classes, deliberately kept together.
	class WpBlockTypeRegistryStub {
		public static function get_instance(): self {
			return new self();
		}

		public function is_registered( string $name ): bool {
			unset( $name );
			return false;
		}
	}

	class_alias( __NAMESPACE__ . '\WpBlockTypeRegistryStub', 'WP_Block_Type_Registry' );
}
