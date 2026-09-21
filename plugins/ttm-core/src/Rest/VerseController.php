<?php
/**
 * `GET /wp-json/ttm/v1/verse` (SPEC §6.6).
 *
 * @package TTM\Core\Rest
 */

declare( strict_types=1 );

namespace TTM\Core\Rest;

use WP_Error;
use WP_REST_Response;

/**
 * Public, GET-only route over the stored `ttm_verse` option.
 */
class VerseController {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register the route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'ttm/v1',
			'/verse',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ self::class, 'get_item' ],
			]
		);
	}

	/**
	 * `GET /verse`: the current verse, including `copyright` (attribution is mandatory); 404
	 * when none is stored.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_item() {
		$verse = get_option( 'ttm_verse', [] );

		if ( ! is_array( $verse ) || empty( $verse ) ) {
			return new WP_Error( 'ttm_not_found', __( 'No verse available.', 'ttm-core' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $verse );
	}
}
