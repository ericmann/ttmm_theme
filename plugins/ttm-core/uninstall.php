<?php
/**
 * Uninstall: removes options and transients only, and only when TTM_REMOVE_DATA is true.
 * Terms and meta are never deleted here (SPEC §3.3 rule 23).
 *
 * @package TTM\Core
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! defined( 'TTM_REMOVE_DATA' ) || true !== TTM_REMOVE_DATA ) {
	return;
}
foreach ( array( 'ttm_verse', 'ttm_verse_history', 'ttm_verse_log', 'ttm_books', 'ttm_settings', 'ttm_series_index', 'ttm_redirects' ) as $ttm_option ) {
	delete_option( $ttm_option );
}

global $wpdb;
if ( isset( $wpdb ) ) {
	// Options table only, bounded LIKE on our own transient prefix — never terms or meta (SPEC §3.3 rule 23).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->esc_like( '_transient_ttm_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ttm_' ) . '%'
		)
	);
}
