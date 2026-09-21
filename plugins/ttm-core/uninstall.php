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
foreach ( array( 'ttm_verse', 'ttm_verse_history', 'ttm_verse_log', 'ttm_books', 'ttm_settings', 'ttm_series_index' ) as $ttm_option ) {
	delete_option( $ttm_option );
}
