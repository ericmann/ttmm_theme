<?php
/**
 * `GET /wp-json/ttm/v1/lead` stub — 404 until Phase 3 implements lead selection.
 *
 * @package TTM\Core\Rest
 */

declare( strict_types=1 );

namespace TTM\Core\Rest;

use WP_Error;

/**
 * Placeholder route; P3-04 replaces get_item() with real lead logic.
 */
class LeadController {

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
			'/lead',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ self::class, 'get_item' ],
			]
		);
	}

	/**
	 * `GET /lead`: 404 until Phase 3.
	 *
	 * @return WP_Error
	 */
	public static function get_item(): WP_Error {
		return new WP_Error( 'ttm_lead_not_ready', __( 'Lead selection is not implemented yet.', 'ttm-core' ), [ 'status' => 404 ] );
	}
}
