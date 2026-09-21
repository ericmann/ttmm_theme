<?php
/**
 * Computed `Cache-Control` for anonymous front-end GETs, feeds and `ttm/v1` REST responses.
 *
 * @package TTM\Core\Cache
 */

declare( strict_types=1 );

namespace TTM\Core\Cache;

use DateTimeImmutable;
use TTM\Core\Config;
use TTM\Core\Support\Clock;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * `send_headers` for the main front-end request; `rest_post_dispatch` for `ttm/v1` routes.
 * The only file allowed to call `is_user_logged_in()` (SPEC rule 8), and only to skip caching.
 */
class Headers {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'send_headers', [ self::class, 'send' ] );
		add_filter( 'rest_post_dispatch', [ self::class, 'rest_send' ], 10, 3 );
	}

	/**
	 * Emit `Cache-Control` for the current front-end request (skips admin/REST — REST is
	 * handled by `rest_send()`).
	 */
	public static function send(): void {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$value = self::for_request(
			[
				'admin'     => is_admin(),
				'rest'      => false,
				'feed'      => is_feed(),
				'logged_in' => is_user_logged_in(),
				'method'    => isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only method check, never persisted or output.
			]
		);

		if ( null !== $value && ! headers_sent() ) {
			header( 'Cache-Control: ' . $value );
		}
	}

	/**
	 * Add `Cache-Control` to `ttm/v1` REST responses.
	 *
	 * @param mixed           $result  Response to send, usually a WP_REST_Response.
	 * @param WP_REST_Server  $server  Server instance (unused).
	 * @param WP_REST_Request $request Request being dispatched.
	 * @return mixed
	 */
	public static function rest_send( $result, $server, $request ) {
		unset( $server );

		if ( ! ( $result instanceof WP_REST_Response ) ) {
			return $result;
		}

		if ( 0 !== strpos( $request->get_route(), '/ttm/v1/' ) ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			$result->header( 'Cache-Control', 'no-store' );
			return $result;
		}

		$result->header( 'Cache-Control', self::header_value( self::max_age( Clock::now() ) ) );

		return $result;
	}

	/**
	 * Pure decision: the `Cache-Control` value for a request shape, or `null` for "send nothing".
	 *
	 * @param array{admin?:bool, rest?:bool, feed?:bool, logged_in?:bool, method?:string} $flags Request shape.
	 * @return string|null
	 */
	public static function for_request( array $flags ): ?string {
		if ( ! empty( $flags['admin'] ) || ! empty( $flags['logged_in'] ) ) {
			return 'no-store';
		}

		if ( ! in_array( strtoupper( (string) ( $flags['method'] ?? 'GET' ) ), [ 'GET', 'HEAD' ], true ) ) {
			return null;
		}

		if ( ! empty( $flags['feed'] ) ) {
			return self::header_value( (int) Config::get( 'cache.feed_seconds', 3600 ) );
		}

		return self::header_value( self::max_age( Clock::now() ) );
	}

	/**
	 * The earlier of the next local midnight and the next local `$hour`:00, as an absolute
	 * moment (so the caller's timestamp diff already reflects real elapsed seconds across a
	 * DST transition — no special-casing needed here).
	 *
	 * @param DateTimeImmutable $now  Reference "now".
	 * @param int               $hour Boundary hour (0-23).
	 * @return DateTimeImmutable
	 */
	public static function boundary( DateTimeImmutable $now, int $hour ): DateTimeImmutable {
		$midnight = $now->setTime( 0, 0, 0 )->modify( '+1 day' );

		$hour_boundary = $now->setTime( $hour, 0, 0 );
		if ( $hour_boundary <= $now ) {
			$hour_boundary = $hour_boundary->modify( '+1 day' );
		}

		return $midnight < $hour_boundary ? $midnight : $hour_boundary;
	}

	/**
	 * `max-age`/`s-maxage` seconds: clamped seconds-until-boundary, filterable via
	 * `ttm_cache_max_age`.
	 *
	 * @param DateTimeImmutable $now Reference "now".
	 * @return int
	 */
	public static function max_age( DateTimeImmutable $now ): int {
		$hour     = (int) Config::get( 'cache.verse_boundary_hour', 6 );
		$boundary = self::boundary( $now, $hour );
		$seconds  = $boundary->getTimestamp() - $now->getTimestamp();

		$min = (int) Config::get( 'cache.min_age_seconds', 60 );
		$cap = (int) Config::get( 'cache.max_age_cap_seconds', 86400 );

		$seconds = max( $min, min( $cap, $seconds ) );

		return (int) apply_filters( 'ttm_cache_max_age', $seconds, $now );
	}

	/**
	 * `public, max-age=N, s-maxage=N`.
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	public static function header_value( int $seconds ): string {
		return sprintf( 'public, max-age=%1$d, s-maxage=%1$d', $seconds );
	}
}
