<?php
/**
 * Newsletter settings tab: provider, endpoint, fallback email, list id (SPEC §6.1, rule 17).
 *
 * @package TTM\Core\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter;

use TTM\Core\Admin\Page;
use TTM\Core\Config;

/**
 * Registers itself as the "Newsletter" tab on Admin\Page. Never renders the configured API key
 * (a PHP constant, not an option) -- masks it as `••••` when set (rule 17).
 */
class Settings {

	/**
	 * Provider choices offered in the tab.
	 */
	private const PROVIDERS = [ 'jetpack', 'custom-url', 'mailto', 'none' ];

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action(
			'init',
			static function (): void {
				Page::register_tab( 'newsletter', __( 'Newsletter', 'ttm-core' ), [ self::class, 'render' ], [ self::class, 'save' ] );
			}
		);
	}

	/**
	 * Render the tab's fields.
	 */
	public static function render(): void {
		$provider = (string) Config::get( 'newsletter.provider', 'jetpack' );
		$endpoint = (string) Config::get( 'newsletter.endpoint', '' );
		$fallback = (string) Config::get( 'newsletter.fallback_email', '' );
		$list_id  = (string) Config::get( 'newsletter.list_id', '' );
		$api_key  = (string) Config::get( 'newsletter.api_key', '' );

		echo '<table class="form-table"><tbody>';

		echo '<tr><th><label for="ttm_newsletter_provider">' . esc_html__( 'Provider', 'ttm-core' ) . '</label></th><td>';
		echo '<select name="newsletter_provider" id="ttm_newsletter_provider">';
		foreach ( self::PROVIDERS as $slug ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $provider, $slug, false ), esc_html( $slug ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Jetpack posts to your WordPress.com subscriber list; custom-url forwards to your own endpoint below.', 'ttm-core' ) . '</p></td></tr>';

		echo '<tr><th><label for="ttm_newsletter_endpoint">' . esc_html__( 'Custom endpoint URL', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="url" class="regular-text" name="newsletter_endpoint" id="ttm_newsletter_endpoint" value="%s"></td></tr>', esc_attr( $endpoint ) );

		echo '<tr><th><label for="ttm_newsletter_api_key">' . esc_html__( 'API key', 'ttm-core' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" value="' . ( '' !== $api_key ? '••••' : '' ) . '" disabled>';
		echo '<p class="description">' . esc_html__( 'Set via the TTM_NEWSLETTER_API_KEY constant -- never stored in the database.', 'ttm-core' ) . '</p></td></tr>';

		echo '<tr><th><label for="ttm_newsletter_fallback_email">' . esc_html__( 'Fallback email (mailto)', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="email" class="regular-text" name="newsletter_fallback_email" id="ttm_newsletter_fallback_email" value="%s"></td></tr>', esc_attr( $fallback ) );

		echo '<tr><th><label for="ttm_newsletter_list_id">' . esc_html__( 'List id', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="text" class="regular-text" name="newsletter_list_id" id="ttm_newsletter_list_id" value="%s"></td></tr>', esc_attr( $list_id ) );

		echo '</tbody></table>';
	}

	/**
	 * Save handler: reads $_POST (already nonce/capability-checked by Page::handle_save()).
	 */
	public static function save(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce + capability already checked by Admin\Page::handle_save() before dispatching here.
		$provider = isset( $_POST['newsletter_provider'] ) ? sanitize_key( wp_unslash( $_POST['newsletter_provider'] ) ) : 'jetpack';
		$endpoint = isset( $_POST['newsletter_endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['newsletter_endpoint'] ) ) : '';
		$fallback = isset( $_POST['newsletter_fallback_email'] ) ? sanitize_email( wp_unslash( $_POST['newsletter_fallback_email'] ) ) : '';
		$list_id  = isset( $_POST['newsletter_list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['newsletter_list_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			$provider = 'jetpack';
		}

		$settings = get_option( 'ttm_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$settings['newsletter'] = [
			'provider'       => $provider,
			'endpoint'       => $endpoint,
			'fallback_email' => $fallback,
			'list_id'        => $list_id,
		];

		update_option( 'ttm_settings', $settings );
		Config::reset();

		do_action( 'ttm_purge_urls', [ home_url( '/' ) ] );
	}
}
