<?php
/**
 * Post meta registration for `post` (SPEC §5.2).
 *
 * @package TTM\Core\Meta
 */

declare( strict_types=1 );

namespace TTM\Core\Meta;

/**
 * Registers every ttm_* post-meta key with typed sanitizers and REST schemas.
 */
class PostMeta {

	public const FORMS = [ 'article', 'chapter', 'story' ];

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ] );
	}

	/**
	 * Register every ttm_* post-meta key (SPEC §5.2).
	 */
	public static function register_meta(): void {
		$auth = static fn ( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id );

		register_post_meta(
			'post',
			'ttm_series_part',
			[
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => [ self::class, 'sanitize_part' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_post_meta(
			'post',
			'ttm_part_title',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_post_meta(
			'post',
			'ttm_primary_category',
			[
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => [ self::class, 'sanitize_primary_category' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_post_meta(
			'post',
			'ttm_form',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => 'article',
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

		register_post_meta(
			'post',
			'ttm_form_locked',
			[
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_post_meta(
			'post',
			'ttm_word_count',
			[
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_post_meta(
			'post',
			'ttm_syndication',
			[
				'type'              => 'object',
				'single'            => true,
				'default'           => [],
				'sanitize_callback' => [ self::class, 'sanitize_syndication' ],
				'auth_callback'     => $auth,
				'show_in_rest'      => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'x'        => [ 'type' => 'string' ],
							'mastodon' => [ 'type' => 'string' ],
							'bluesky'  => [ 'type' => 'string' ],
						],
					],
				],
			]
		);

		register_post_meta(
			'post',
			'ttm_location',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		register_post_meta(
			'post',
			'ttm_featured_in_section',
			[
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
				'show_in_rest'      => true,
			]
		);

		// P8-02 classic-to-block conversion: the pre-conversion post_content (verbatim, for
		// convert:revert) and the conversion timestamp. Deliberately not exposed over REST -
		// this is an internal migration artefact, not editorial content.
		register_post_meta(
			'post',
			'ttm_classic_backup',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'wp_kses_post',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			]
		);

		register_post_meta(
			'post',
			'ttm_converted_at',
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Integer ≥ 1, 0 when unset/invalid.
	 *
	 * @param mixed $value Raw meta value.
	 * @return int
	 */
	public static function sanitize_part( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		$part = (int) $value;

		return $part >= 1 ? $part : 0;
	}

	/**
	 * Must be a `category` term id, else 0.
	 *
	 * @param mixed $value Raw meta value.
	 * @return int
	 */
	public static function sanitize_primary_category( mixed $value ): int {
		$id = absint( $value );
		if ( 0 === $id ) {
			return 0;
		}

		return term_exists( $id, 'category' ) ? $id : 0;
	}

	/**
	 * Enum sanitizer for ttm_form.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_form( mixed $value ): string {
		return in_array( $value, self::FORMS, true ) ? $value : 'article';
	}

	/**
	 * Keeps only known networks (x/mastodon/bluesky) with https URLs.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array<string, string>
	 */
	public static function sanitize_syndication( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$clean = [];
		foreach ( [ 'x', 'mastodon', 'bluesky' ] as $network ) {
			if ( empty( $value[ $network ] ) ) {
				continue;
			}
			$url = esc_url_raw( (string) $value[ $network ], [ 'https' ] );
			if ( '' !== $url ) {
				$clean[ $network ] = $url;
			}
		}

		return $clean;
	}
}
