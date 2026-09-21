<?php
/**
 * Plugin-side half of the theme⇄plugin API-version contract. Imports Config only.
 *
 * @package TTM\Core\Compat
 */

declare( strict_types=1 );

namespace TTM\Core\Compat;

/**
 * Plugin-side half of the theme⇄plugin API-version contract.
 */
class Theme {

	/**
	 * Hook the admin notice.
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'maybe_notice' ] );
	}

	/**
	 * Show a non-dismissible error notice on the themes/plugins screens when the active
	 * theme declares an API version the plugin does not match.
	 */
	public static function maybe_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, [ 'themes', 'plugins' ], true ) ) {
			return;
		}

		$theme_requires = defined( 'TTM_THEME_REQUIRES_API' ) ? TTM_THEME_REQUIRES_API : null;

		if ( ! self::mismatch( $theme_requires, TTM_CORE_API ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'These Things Matter — Core: the active theme requires a different plugin API version.', 'ttm-core' ) .
			'</p></div>';
	}

	/**
	 * Pure comparison: true when the theme declares a required API version that does not match.
	 *
	 * @param int|null $theme_requires Theme's declared TTM_THEME_REQUIRES_API, or null if undeclared.
	 * @param int      $api            The plugin's TTM_CORE_API.
	 * @return bool
	 */
	public static function mismatch( ?int $theme_requires, int $api ): bool {
		if ( null === $theme_requires ) {
			return false;
		}

		return $theme_requires !== $api;
	}
}
