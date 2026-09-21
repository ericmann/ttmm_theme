<?php
/**
 * `GET /wp-json/ttm/v1/lead` (SPEC §6.6).
 *
 * @package TTM\Core\Rest
 */

declare( strict_types=1 );

namespace TTM\Core\Rest;

use TTM\Core\Query\Lead;
use WP_Error;
use WP_REST_Response;

/**
 * Public, GET-only route over the cached lead selection.
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
	 * `GET /lead`: `{id, reason}`; 404 only when `reason === 'none'`.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_item() {
		$lead = Lead::compute();

		if ( 'none' === $lead['reason'] ) {
			return new WP_Error( 'ttm_not_found', __( 'No lead post available.', 'ttm-core' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $lead );
	}
}
