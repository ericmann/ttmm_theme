<?php
/**
 * General settings tab: journal_in_main_feed, lead.sticky_days/stale_days, comments_enabled.
 *
 * @package TTM\Core\Admin
 */

declare( strict_types=1 );

namespace TTM\Core\Admin;

use TTM\Core\Config;

/**
 * Registers itself as the "General" tab on Page.
 */
class General {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action(
			'init',
			static function (): void {
				Page::register_tab( 'general', __( 'General', 'ttm-core' ), [ self::class, 'render' ], [ self::class, 'save' ] );
			}
		);
	}

	/**
	 * Render the tab's fields.
	 */
	public static function render(): void {
		$journal  = (bool) Config::get( 'journal_in_main_feed' );
		$sticky   = (int) Config::get( 'lead.sticky_days' );
		$stale    = (int) Config::get( 'lead.stale_days' );
		$comments = (bool) Config::get( 'comments_enabled' );

		echo '<table class="form-table"><tbody>';

		echo '<tr><th><label for="ttm_journal_in_main_feed">' . esc_html__( 'Include Journal in main feed', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="checkbox" name="journal_in_main_feed" id="ttm_journal_in_main_feed" value="1"%s></td></tr>', checked( $journal, true, false ) );

		echo '<tr><th><label for="ttm_lead_sticky_days">' . esc_html__( 'Lead sticky days', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="number" min="0" name="lead_sticky_days" id="ttm_lead_sticky_days" value="%d"></td></tr>', (int) $sticky );

		echo '<tr><th><label for="ttm_lead_stale_days">' . esc_html__( 'Lead stale days', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="number" min="0" name="lead_stale_days" id="ttm_lead_stale_days" value="%d"></td></tr>', (int) $stale );

		echo '<tr><th><label for="ttm_comments_enabled">' . esc_html__( 'Comments', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="checkbox" name="comments_enabled" id="ttm_comments_enabled" value="1"%s> ', checked( $comments, true, false ) );
		echo '<p class="description">' . esc_html__( 'Comments are closed by design; this toggle is informational only.', 'ttm-core' ) . '</p></td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Save handler: reads $_POST (already nonce/capability-checked by Page::handle_save()).
	 */
	public static function save(): void {
		$settings = get_option( 'ttm_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$settings['journal_in_main_feed'] = ! empty( $_POST['journal_in_main_feed'] );
		$settings['comments_enabled']     = ! empty( $_POST['comments_enabled'] );

		$sticky_raw = isset( $_POST['lead_sticky_days'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_sticky_days'] ) ) : '';
		$stale_raw  = isset( $_POST['lead_stale_days'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_stale_days'] ) ) : '';

		if ( ! isset( $settings['lead'] ) || ! is_array( $settings['lead'] ) ) {
			$settings['lead'] = [];
		}

		if ( '' === $sticky_raw ) {
			unset( $settings['lead']['sticky_days'] );
		} else {
			$settings['lead']['sticky_days'] = absint( $sticky_raw );
		}

		if ( '' === $stale_raw ) {
			unset( $settings['lead']['stale_days'] );
		} else {
			$settings['lead']['stale_days'] = absint( $stale_raw );
		}

		if ( empty( $settings['lead'] ) ) {
			unset( $settings['lead'] );
		}

		update_option( 'ttm_settings', $settings );
		Config::reset();

		do_action( 'ttm_purge_urls', [ home_url( '/' ) ] );
	}
}
