<?php
/**
 * Purges Cloudflare's cache for the URLs collected by `Purge`.
 *
 * @package TTM\Core\Cache
 */

declare( strict_types=1 );

namespace TTM\Core\Cache;

use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * The only file allowed to call `wp_safe_remote_post` for the Cloudflare purge endpoint
 * (SPEC §3.3 rule 16 -- the zone id is a constant, so the URL is never request-derived).
 *
 * A purge that fails leaves the published change invisible until the day boundary, so a failure
 * is never silent: it is logged whatever `WP_DEBUG` says, kept in `ttm_cloudflare_last_error`
 * and shown to administrators until a purge succeeds again.
 */
class Cloudflare {

	/**
	 * Purge endpoint, `%s` = zone id.
	 */
	private const ENDPOINT = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache';

	/**
	 * Option holding the last failure: `['at' => ISO 8601, 'message' => string]`.
	 */
	public const LAST_ERROR_OPTION = 'ttm_cloudflare_last_error';

	/**
	 * Hook registration. Always adds the listener; `purge()` itself checks `available()` so
	 * that a settings change (or, in tests, a `ttm_config` filter) takes effect without a
	 * re-registration step.
	 */
	public static function register(): void {
		add_action( 'ttm_purge_urls', [ self::class, 'purge' ] );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
	}

	/**
	 * Whether both Cloudflare constants/config values are set.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return '' !== (string) Config::get( 'cache.cloudflare.zone_id', '' )
			&& '' !== (string) Config::get( 'cache.cloudflare.api_token', '' );
	}

	/**
	 * Purge every URL, chunked by `cache.cloudflare.batch`, deduped.
	 *
	 * @param string[] $urls Affected URLs.
	 */
	public static function purge( array $urls ): void {
		if ( ! self::available() ) {
			return;
		}

		$urls = array_values( array_unique( $urls ) );
		if ( empty( $urls ) ) {
			return;
		}

		$batch  = max( 1, (int) Config::get( 'cache.cloudflare.batch', 30 ) );
		$failed = false;

		foreach ( array_chunk( $urls, $batch ) as $chunk ) {
			$error = self::failure( self::request( $chunk ) );
			if ( null === $error ) {
				continue;
			}

			$failed = true;
			self::record_failure( $error );
			error_log( 'TTM Cloudflare purge failed: ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- an operator must see this; the message carries no secret.
		}

		if ( ! $failed && false !== get_option( self::LAST_ERROR_OPTION ) ) {
			delete_option( self::LAST_ERROR_OPTION );
		}
	}

	/**
	 * One purge request for one chunk of URLs.
	 *
	 * @param string[] $chunk URLs, at most `cache.cloudflare.batch`.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function request( array $chunk ) {
		$zone    = (string) Config::get( 'cache.cloudflare.zone_id', '' );
		$token   = (string) Config::get( 'cache.cloudflare.api_token', '' );
		$timeout = (int) Config::get( 'cache.cloudflare.timeout_seconds', 10 );

		return wp_safe_remote_post(
			sprintf( self::ENDPOINT, $zone ),
			[
				'timeout' => $timeout, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- deliberate: a slow purge/subscribe endpoint should not silently drop the request early; this runs off the request/response cycle path, not on a page load.
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( [ 'files' => $chunk ] ),
			]
		);
	}

	/**
	 * Pure: why a purge response is a failure, or null when the API accepted it.
	 *
	 * @param array<string, mixed>|\WP_Error $response `wp_safe_remote_post()` result.
	 * @return string|null
	 */
	public static function failure( $response ): ?string {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : [];

		$messages = [];
		foreach ( (array) ( $body['errors'] ?? [] ) as $item ) {
			if ( is_array( $item ) && isset( $item['message'] ) ) {
				$messages[] = (string) $item['message'];
			}
		}
		$detail = $messages ? ': ' . implode( '; ', $messages ) : '';

		if ( 200 !== $code ) {
			return sprintf( 'HTTP %d%s', $code, $detail );
		}

		if ( empty( $body['success'] ) ) {
			return 'API answered success=false' . $detail;
		}

		return null;
	}

	/**
	 * Keep the latest failure for the admin notice.
	 *
	 * @param string $message Why.
	 */
	private static function record_failure( string $message ): void {
		update_option(
			self::LAST_ERROR_OPTION,
			[
				'at'      => Clock::now()->format( DATE_ATOM ),
				'message' => $message,
			],
			false
		);
	}

	/**
	 * `admin_notices`: the last failure, until a purge succeeds.
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$last = get_option( self::LAST_ERROR_OPTION );
		if ( ! is_array( $last ) || empty( $last['message'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: when, 2: the error */
					__( 'The Cloudflare cache purge failed (%1$s): %2$s. Published changes stay cached until the next day boundary or a successful purge.', 'ttm-core' ),
					(string) ( $last['at'] ?? '' ),
					(string) $last['message']
				)
			)
		);
	}
}
