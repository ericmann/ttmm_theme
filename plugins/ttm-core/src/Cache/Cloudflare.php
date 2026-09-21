<?php
/**
 * Purges Cloudflare's cache for the URLs collected by `Purge`.
 *
 * @package TTM\Core\Cache
 */

declare( strict_types=1 );

namespace TTM\Core\Cache;

use TTM\Core\Config;

/**
 * The only file allowed to call `wp_safe_remote_post` for the Cloudflare purge endpoint
 * (SPEC §3.3 rule 16 -- the zone id is a constant, so the URL is never request-derived).
 */
class Cloudflare {

	/**
	 * Purge endpoint, `%s` = zone id.
	 */
	private const ENDPOINT = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache';

	/**
	 * Hook registration. Always adds the listener; `purge()` itself checks `available()` so
	 * that a settings change (or, in tests, a `ttm_config` filter) takes effect without a
	 * re-registration step.
	 */
	public static function register(): void {
		add_action( 'ttm_purge_urls', [ self::class, 'purge' ] );
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

		$zone  = (string) Config::get( 'cache.cloudflare.zone_id', '' );
		$token = (string) Config::get( 'cache.cloudflare.api_token', '' );
		$batch   = max( 1, (int) Config::get( 'cache.cloudflare.batch', 30 ) );
		$timeout = (int) Config::get( 'cache.cloudflare.timeout_seconds', 10 );

		foreach ( array_chunk( $urls, $batch ) as $chunk ) {
			$response = wp_safe_remote_post(
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

			if ( is_wp_error( $response ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'TTM Cloudflare purge failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-guarded diagnostic only, never printed.
			}
		}
	}
}
