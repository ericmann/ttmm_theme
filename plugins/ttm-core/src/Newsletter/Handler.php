<?php
/**
 * `custom-url` newsletter provider's unauthenticated submission handler (SPEC §6.1/§6.9).
 *
 * @package TTM\Core\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter;

use DateTimeImmutable;
use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * `admin_post(_nopriv)_ttm_subscribe`: stateless HMAC token (no `wp_create_nonce` - rule 7),
 * honeypot and per-IP rate limit, all failing the same way (a plain success redirect, never an
 * oracle). The actual HTTP forward is behind an injectable callable so `handle()` is unit
 * testable without WordPress.
 */
class Handler {

	/**
	 * Injected forwarder for tests; null uses the real `Provider\CustomUrl::subscribe()`.
	 *
	 * @var callable|null
	 */
	private static $forwarder = null;

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'admin_post_nopriv_ttm_subscribe', [ self::class, 'handle_request' ] );
		add_action( 'admin_post_ttm_subscribe', [ self::class, 'handle_request' ] );
	}

	/**
	 * Test-only: replace the forward callable (or pass null to restore the real one).
	 *
	 * @param callable|null $forwarder Receives `(string $email)`.
	 */
	public static function set_forwarder( ?callable $forwarder ): void {
		self::$forwarder = $forwarder;
	}

	/**
	 * `admin_post_ttm_subscribe`: dispatch to `handle()` and redirect.
	 */
	public static function handle_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this route is intentionally unauthenticated (SPEC rule 18); protected by the HMAC token, honeypot and rate limit inside handle() instead.
		$url = self::handle( $_POST, self::client_ip(), Clock::now() );

		if ( ! headers_sent() ) {
			// phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit -- deliberately no exit, matching Admin\Page::handle_save(): this handler must stay directly callable from PHPUnit.
			wp_safe_redirect( $url );
		}
	}

	/**
	 * `hash_hmac('sha256', $window_index . '|ttm_subscribe', wp_salt('nonce'))`.
	 *
	 * @param int $window_index `intdiv(timestamp, newsletter.token_ttl)`.
	 * @return string
	 */
	public static function token( int $window_index ): string {
		return hash_hmac( 'sha256', $window_index . '|ttm_subscribe', wp_salt( 'nonce' ) );
	}

	/**
	 * Pure-ish core: validates and forwards a submission, always returning a redirect URL.
	 * Never reflects `$post` values back into the redirect beyond a validated local path.
	 *
	 * @param array<string, mixed> $post Raw `$_POST`.
	 * @param string               $ip   Client IP.
	 * @param DateTimeImmutable    $now  Reference "now".
	 * @return string
	 */
	public static function handle( array $post, string $ip, DateTimeImmutable $now ): string {
		$redirect_to = isset( $post['redirect_to'] ) ? esc_url_raw( (string) wp_unslash( $post['redirect_to'] ) ) : '';
		$target      = (string) wp_validate_redirect( $redirect_to, home_url( '/' ) );

		if ( self::rate_limited( $ip ) ) {
			return self::success_url( $target );
		}

		$honeypot_field = (string) Config::get( 'newsletter.honeypot_field', 'ttm_website' );
		$honeypot_value = isset( $post[ $honeypot_field ] ) ? sanitize_text_field( (string) wp_unslash( $post[ $honeypot_field ] ) ) : '';

		if ( '' !== $honeypot_value ) {
			return self::success_url( $target );
		}

		$token = isset( $post['ttm_token'] ) ? sanitize_text_field( (string) wp_unslash( $post['ttm_token'] ) ) : '';

		if ( ! self::token_valid( $token, $now ) ) {
			return self::success_url( $target );
		}

		$email = isset( $post['email'] ) ? sanitize_email( (string) wp_unslash( $post['email'] ) ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			return self::success_url( $target );
		}

		self::forward( $email );

		do_action( 'ttm_newsletter_subscribed', hash( 'sha256', strtolower( $email ) ), 'custom-url' );

		return self::success_url( $target );
	}

	/**
	 * Whether `$token` matches the current or previous window (grace across a cache purge).
	 *
	 * @param string            $token Submitted token.
	 * @param DateTimeImmutable $now   Reference "now".
	 * @return bool
	 */
	private static function token_valid( string $token, DateTimeImmutable $now ): bool {
		if ( '' === $token ) {
			return false;
		}

		$window = self::window_index( $now );

		return hash_equals( self::token( $window ), $token ) || hash_equals( self::token( $window - 1 ), $token );
	}

	/**
	 * `intdiv(timestamp, newsletter.token_ttl)`.
	 *
	 * @param DateTimeImmutable $now Reference "now".
	 * @return int
	 */
	private static function window_index( DateTimeImmutable $now ): int {
		return intdiv( $now->getTimestamp(), (int) Config::get( 'newsletter.token_ttl', 86400 ) );
	}

	/**
	 * Per-IP rate limit via a `ttm_rl_{hash}` transient; counts every attempt.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	private static function rate_limited( string $ip ): bool {
		$key    = 'ttm_rl_' . hash( 'sha256', $ip . wp_salt( 'auth' ) );
		$count  = (int) get_transient( $key );
		$limit  = (int) Config::get( 'newsletter.rate_limit_per_ip', 5 );
		$window = (int) Config::get( 'newsletter.rate_limit_window', 600 );

		if ( $count >= $limit ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}

	/**
	 * Forward the email via the injected callable, else the real provider.
	 *
	 * @param string $email Validated email address.
	 */
	private static function forward( string $email ): void {
		$forwarder = self::$forwarder ?? [ Provider\CustomUrl::class, 'subscribe' ];
		call_user_func( $forwarder, $email );
	}

	/**
	 * `?subscribed=1` appended to a validated local target.
	 *
	 * @param string $target Validated local URL.
	 * @return string
	 */
	private static function success_url( string $target ): string {
		return add_query_arg( 'subscribed', '1', $target );
	}

	/**
	 * Best-effort client IP from the standard server var, never trusted for anything beyond
	 * rate-limit bucketing.
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__ -- rate-limit bucketing only, sanitised, never trusted for identity or output.
	}
}
