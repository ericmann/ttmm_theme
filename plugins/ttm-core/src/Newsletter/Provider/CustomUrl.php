<?php
/**
 * `custom-url` newsletter provider: a self-hosted form posting to `admin-post.php`, forwarded
 * server-side to `newsletter.endpoint` (SPEC §6.1).
 *
 * @package TTM\Core\Newsletter\Provider
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter\Provider;

use TTM\Core\Config;
use TTM\Core\Newsletter\Form;
use TTM\Core\Newsletter\Handler;
use TTM\Core\Support\Clock;

/**
 * The only file allowed to call `wp_safe_remote_post` for the configured newsletter endpoint
 * (SPEC §3.3 rule 16 -- only when it is `https` and passes `wp_http_validate_url()`).
 */
class CustomUrl implements Provider {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'custom-url';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return self::endpoint_is_valid() || self::dev_accept_applies();
	}

	/**
	 * Whether the configured endpoint is `https` and passes `wp_http_validate_url()`.
	 *
	 * @return bool
	 */
	public static function endpoint_is_valid(): bool {
		$endpoint = (string) Config::get( 'newsletter.endpoint', '' );

		if ( '' === $endpoint || 0 !== strpos( $endpoint, 'https://' ) ) {
			return false;
		}

		return false !== wp_http_validate_url( $endpoint );
	}

	/**
	 * Whether an empty `newsletter.endpoint` should still accept submissions locally: no
	 * outbound forward, no log, a plain success redirect (SPEC §6.3 "New", §5
	 * `newsletter.dev_accept`). Never true in production, regardless of config.
	 *
	 * @return bool
	 */
	public static function dev_accept_applies(): bool {
		return '' === (string) Config::get( 'newsletter.endpoint', '' )
			&& (bool) Config::get( 'newsletter.dev_accept', true )
			&& 'production' !== wp_get_environment_type();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $placement `poster` or `box`.
	 */
	public function render( string $placement ): string {
		$honeypot_field = (string) Config::get( 'newsletter.honeypot_field', 'ttm_website' );
		$token          = Handler::token( intdiv( Clock::now()->getTimestamp(), (int) Config::get( 'newsletter.token_ttl', 86400 ) ) );
		$current_url    = home_url( add_query_arg( null, null ) );

		return Form::render(
			admin_url( 'admin-post.php' ),
			[
				'action'        => 'ttm_subscribe',
				'ttm_token'     => $token,
				'redirect_to'   => $current_url,
				$honeypot_field => '',
			],
			$placement
		);
	}

	/**
	 * The real forward: POST the email to `newsletter.endpoint` with the configured API key.
	 * Failures are silent to the visitor by design (`Handler` never surfaces them).
	 *
	 * @param string $email Validated email address.
	 */
	public static function subscribe( string $email ): void {
		if ( ! self::endpoint_is_valid() ) {
			return;
		}

		$endpoint = (string) Config::get( 'newsletter.endpoint', '' );
		$api_key  = (string) Config::get( 'newsletter.api_key', '' );
		$list_id  = (string) Config::get( 'newsletter.list_id', '' );

		$headers = [ 'Content-Type' => 'application/json' ];
		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		wp_safe_remote_post(
			$endpoint,
			[
				'timeout' => (int) Config::get( 'newsletter.timeout_seconds', 10 ), // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- deliberate: the newsletter forward runs after the visitor has already been redirected, not on their request/response cycle.
				'headers' => $headers,
				'body'    => (string) wp_json_encode(
					[
						'email'   => $email,
						'list_id' => $list_id,
					]
				),
			]
		);
	}
}
