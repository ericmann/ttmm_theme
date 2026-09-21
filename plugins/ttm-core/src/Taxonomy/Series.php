<?php
/**
 * `series` taxonomy: registration, typed term meta, single-series enforcement.
 *
 * @package TTM\Core\Taxonomy
 */

declare( strict_types=1 );

namespace TTM\Core\Taxonomy;

use TTM\Core\Config;

/**
 * `series` taxonomy registration and typed term meta.
 */
class Series {

	public const STATUSES = [ 'in-progress', 'complete', 'hiatus' ];
	public const FORMS    = [ 'nonfiction', 'novel', 'novella', 'story-cycle' ];

	/**
	 * Recursion guard for enforce_single().
	 *
	 * @var bool
	 */
	private static bool $enforcing = false;

	/**
	 * Hook registration and taxonomy/meta setup.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_taxonomy' ] );
		add_action( 'init', [ self::class, 'register_meta' ] );
		add_action( 'set_object_terms', [ self::class, 'enforce_single' ], 10, 6 );
	}

	/**
	 * Register the `series` taxonomy on `post` (SPEC §5.1).
	 */
	public static function register_taxonomy(): void {
		register_taxonomy(
			'series',
			'post',
			[
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [
					'slug'       => 'series',
					'with_front' => false,
				],
				'query_var'         => 'series',
				'labels'            => [
					'name'          => __( 'Series', 'ttm-core' ),
					'singular_name' => __( 'Series', 'ttm-core' ),
				],
			]
		);
	}

	/**
	 * Register the nine typed term-meta keys (SPEC §5.1).
	 */
	public static function register_meta(): void {
		$auth = static fn (): bool => current_user_can( 'manage_categories' );

		register_term_meta(
			'series',
			'ttm_status',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => 'in-progress',
				'sanitize_callback' => [ self::class, 'sanitize_status' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
						'enum' => self::STATUSES,
					],
				],
			]
		);

		register_term_meta(
			'series',
			'ttm_total_parts',
			[
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_term_meta(
			'series',
			'ttm_form',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => 'nonfiction',
				'sanitize_callback' => [ self::class, 'sanitize_form' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
						'enum' => self::FORMS,
					],
				],
			]
		);

		register_term_meta(
			'series',
			'ttm_genre',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_term_meta(
			'series',
			'ttm_cadence',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_term_meta(
			'series',
			'ttm_next_date',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => [ self::class, 'sanitize_next_date' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_term_meta(
			'series',
			'ttm_cover_id',
			[
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => [ self::class, 'sanitize_cover_id_checked' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_term_meta(
			'series',
			'ttm_featured',
			[
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_term_meta(
			'series',
			'ttm_purchase_links',
			[
				'type'              => 'array',
				'single'            => true,
				'default'           => [],
				'sanitize_callback' => [ self::class, 'sanitize_purchase_links' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'label' => [ 'type' => 'string' ],
								'url'   => [ 'type' => 'string' ],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Keep every post to at most one series term.
	 *
	 * @param int            $object_id  Object ID.
	 * @param int[]|string[] $terms      Terms just set.
	 * @param int[]          $tt_ids     Term taxonomy IDs.
	 * @param string         $taxonomy   Taxonomy slug.
	 * @param bool           $append     Whether terms were appended.
	 * @param int[]          $old_tt_ids Previous term taxonomy IDs.
	 */
	public static function enforce_single( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
		unset( $append, $old_tt_ids );

		if ( 'series' !== $taxonomy || self::$enforcing || count( $tt_ids ) <= 1 ) {
			return;
		}

		self::$enforcing = true;
		wp_set_object_terms( $object_id, [ (int) $tt_ids[0] ], 'series', false );
		self::$enforcing = false;
	}

	/**
	 * Enum sanitizer for ttm_status.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_status( mixed $value ): string {
		return in_array( $value, self::STATUSES, true ) ? $value : 'in-progress';
	}

	/**
	 * Enum sanitizer for ttm_form.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_form( mixed $value ): string {
		return in_array( $value, self::FORMS, true ) ? $value : 'nonfiction';
	}

	/**
	 * Y-m-d sanitizer for ttm_next_date; empty string on anything invalid.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_next_date( mixed $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		[ $y, $m, $d ] = array_map( 'intval', explode( '-', $value ) );

		return checkdate( $m, $d, $y ) ? $value : '';
	}

	/**
	 * Pure: coerce to a non-negative integer. The WordPress-aware attachment-image
	 * check happens in sanitize_cover_id_checked().
	 *
	 * @param mixed $value Raw meta value.
	 * @return int
	 */
	public static function sanitize_cover_id( mixed $value ): int {
		return absint( $value );
	}

	/**
	 * WordPress-aware sanitizer for ttm_cover_id: zero unless it is an image attachment.
	 *
	 * @param mixed $value Raw meta value.
	 * @return int
	 */
	public static function sanitize_cover_id_checked( mixed $value ): int {
		$id = self::sanitize_cover_id( $value );

		return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
	}

	/**
	 * Sanitizer for ttm_purchase_links: keeps only http(s) links, capped at the config limit.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function sanitize_purchase_links( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$max   = (int) Config::get( 'series.max_purchase_links', 6 );
		$clean = [];

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( (string) $entry['url'] );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}

			$clean[] = [
				'label' => sanitize_text_field( (string) ( $entry['label'] ?? '' ) ),
				'url'   => $url,
			];

			if ( count( $clean ) >= $max ) {
				break;
			}
		}

		return $clean;
	}
}
