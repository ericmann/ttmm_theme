<?php
/**
 * Admin notice when a `ttm/*` block's editor bundle is missing (SPEC §6.7).
 *
 * @package TTM\Core\Admin
 */

declare( strict_types=1 );

namespace TTM\Core\Admin;

use TTM\Core\Blocks\Registrar;

/**
 * Reads `Blocks\Registrar::fallback_blocks()` (populated during the same request's `init`)
 * and, for `manage_options` users only, prints a notice telling the owner to build the editor
 * bundle. Never touches the front end (rule 6): `admin_notices` only fires in `wp-admin`.
 */
class BuildNotice {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'maybe_render' ] );
	}

	/**
	 * Print the notice when at least one block fell back to the committed editor script this
	 * request.
	 */
	public static function maybe_render(): void {
		if ( empty( Registrar::fallback_blocks() ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'ttm-core: editor bundle missing — run `npm run build`', 'ttm-core' )
		);
	}
}
