<?php
/**
 * `GET /wp-json/ttm/v1/series` and `/series/{slug}` (SPEC §6.6).
 *
 * @package TTM\Core\Rest
 */

declare( strict_types=1 );

namespace TTM\Core\Rest;

use TTM\Core\Query\SeriesIndex;
use TTM\Core\Taxonomy\Series;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public, GET-only routes over the series index.
 */
class SeriesController {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register the routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'ttm/v1',
			'/series',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => [
					'status' => [
						'type' => 'string',
						'enum' => Series::STATUSES,
					],
					'form'   => [
						'type' => 'string',
						'enum' => Series::FORMS,
					],
				],
				'callback'            => [ self::class, 'get_items' ],
			]
		);

		register_rest_route(
			'ttm/v1',
			'/series/(?P<slug>[a-z0-9-]+)',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ self::class, 'get_item' ],
			]
		);
	}

	/**
	 * `GET /series`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_items( WP_REST_Request $request ): WP_REST_Response {
		$status = $request->get_param( 'status' );
		$form   = $request->get_param( 'form' );

		$rows = array_filter( SeriesIndex::all(), [ self::class, 'has_published_part' ] );

		if ( $status ) {
			$rows = array_filter( $rows, static fn ( array $row ): bool => $row['status'] === $status );
		}
		if ( $form ) {
			$rows = array_filter( $rows, static fn ( array $row ): bool => $row['form'] === $form );
		}

		$rows = array_map( [ self::class, 'public_row' ], array_values( $rows ) );

		return rest_ensure_response( array_values( $rows ) );
	}

	/**
	 * `GET /series/{slug}`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_item( WP_REST_Request $request ) {
		$row = SeriesIndex::by_slug( (string) $request->get_param( 'slug' ) );

		if ( ! $row || ! self::has_published_part( $row ) ) {
			return new WP_Error( 'ttm_not_found', __( 'Series not found.', 'ttm-core' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( self::public_row( $row ) );
	}

	/**
	 * Whether a row has at least one published part.
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return bool
	 */
	private static function has_published_part( array $row ): bool {
		foreach ( $row['parts'] as $part ) {
			if ( 'publish' === $part['status'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Strip a row to public parts: only publish/future statuses, future parts hide post_id.
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return array<string, mixed>
	 */
	private static function public_row( array $row ): array {
		$row['parts'] = array_values(
			array_map(
				static function ( array $part ): array {
					if ( 'future' === $part['status'] ) {
						$part['post_id'] = 0;
					}
					return $part;
				},
				array_filter(
					$row['parts'],
					static fn ( array $part ): bool => in_array( $part['status'], [ 'publish', 'future' ], true )
				)
			)
		);

		return $row;
	}
}
