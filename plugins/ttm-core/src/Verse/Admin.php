<?php
/**
 * "Verse" settings tab: current verse, fetch log, and a "Fetch now" action.
 *
 * @package TTM\Core\Verse
 */

declare( strict_types=1 );

namespace TTM\Core\Verse;

use TTM\Core\Admin\Page;

/**
 * Read-only tab (no `save`) plus its own `admin_post_ttm_verse_fetch` handler.
 */
class Admin {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action(
			'init',
			static function (): void {
				Page::register_tab( 'verse', __( 'Verse', 'ttm-core' ), [ self::class, 'render' ] );
			}
		);
		add_action( 'admin_post_ttm_verse_fetch', [ self::class, 'handle_fetch_now' ] );
	}

	/**
	 * Render the tab.
	 */
	public static function render(): void {
		$verse = get_option( 'ttm_verse', [] );
		$log   = get_option( 'ttm_verse_log', [] );
		$log   = is_array( $log ) ? $log : [];

		echo '<h2>' . esc_html__( 'Current verse', 'ttm-core' ) . '</h2>';

		if ( is_array( $verse ) && ! empty( $verse ) ) {
			echo '<p><strong>' . esc_html( (string) ( $verse['reference'] ?? '' ) ) . '</strong> — ' . esc_html( (string) ( $verse['text'] ?? '' ) ) . '</p>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: verse date, 2: fetched-at timestamp. */
					__( 'Date: %1$s. Fetched at: %2$s.', 'ttm-core' ),
					(string) ( $verse['date'] ?? '' ),
					(string) ( $verse['fetched_at'] ?? '' )
				)
			) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No verse stored yet.', 'ttm-core' ) . '</p>';
		}

		$fetch_url = wp_nonce_url(
			add_query_arg(
				[ 'action' => 'ttm_verse_fetch' ],
				admin_url( 'admin-post.php' )
			),
			'ttm_verse_fetch'
		);

		printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( $fetch_url ), esc_html__( 'Fetch now', 'ttm-core' ) );

		echo '<h2>' . esc_html__( 'Fetch log', 'ttm-core' ) . '</h2>';
		echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'At', 'ttm-core' ) . '</th><th>' . esc_html__( 'Result', 'ttm-core' ) . '</th><th>' . esc_html__( 'Message', 'ttm-core' ) . '</th></tr></thead><tbody>';

		foreach ( $log as $entry ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( (string) ( $entry['at'] ?? '' ) ),
				! empty( $entry['ok'] ) ? esc_html__( 'OK', 'ttm-core' ) : esc_html__( 'Failed', 'ttm-core' ),
				esc_html( (string) ( $entry['message'] ?? '' ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * `admin_post_ttm_verse_fetch`: capability + nonce checked (SPEC §3.3 rule 18), then fetch
	 * and redirect back to the tab.
	 */
	public static function handle_fetch_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ttm-core' ) );
		}
		check_admin_referer( 'ttm_verse_fetch' );

		Fetcher::fetch( true );

		if ( ! headers_sent() ) {
			$url = add_query_arg(
				[
					'page'    => 'ttm-settings',
					'tab'     => 'verse',
					'updated' => 1,
				],
				admin_url( 'options-general.php' )
			);
			// phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit -- deliberately no exit: this handler must remain directly callable from PHPUnit without terminating the test process; it is the last statement in the function either way.
			wp_safe_redirect( $url );
		}
	}
}
