<?php
/**
 * Theme-side half of the theme⇄plugin API-version contract. Never fatals; every
 * TTM\Core/ttm_ reference is guarded with defined()/function_exists()/class_exists().
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

if ( ! defined( 'TTM_THEME_REQUIRES_API' ) ) {
	define( 'TTM_THEME_REQUIRES_API', 1 );
}

add_action(
	'admin_notices',
	static function (): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, [ 'themes', 'plugins' ], true ) ) {
			return;
		}

		if ( ! defined( 'TTM_CORE_API' ) ) {
			echo '<div class="notice notice-info"><p>' .
				esc_html__( 'ttm-core is not active; series, verse and fiction blocks will not render.', 'ttm-theme' ) .
				'</p></div>';
			return;
		}

		if ( defined( 'TTM_CORE_API' ) && TTM_CORE_API !== TTM_THEME_REQUIRES_API ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'These Things Matter — the active ttm-core plugin version does not match this theme.', 'ttm-theme' ) .
				'</p></div>';
		}
	}
);
