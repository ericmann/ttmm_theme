<?php
/**
 * Registers the front-page block-binding sources (SPEC §6.3, 05 §4).
 *
 * @package TTM\Core\Bindings
 */

declare( strict_types=1 );

namespace TTM\Core\Bindings;

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;
use TTM\Core\Support\Clock;
use WP_Block;

/**
 * Gathers WordPress data (post, primary category, series position, category counts) and hands
 * it to the pure `Values` functions; only `ttm/meta-line` may return HTML, and only for
 * `core/paragraph`'s `content` attribute.
 */
class Sources {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_sources' ] );
	}

	/**
	 * Register every source. No-op on WordPress versions without block bindings.
	 */
	public static function register_sources(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		// Guard against re-registration: this runs on every `init` fire (including the test
		// harness's per-test re-fire), and the registry throws a doing_it_wrong() otherwise.
		if ( \WP_Block_Bindings_Registry::get_instance()->is_registered( 'ttm/kicker' ) ) {
			return;
		}

		register_block_bindings_source(
			'ttm/kicker',
			[
				'label'              => __( 'TTM: Kicker', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'kicker' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/meta-line',
			[
				'label'              => __( 'TTM: Meta line', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'meta_line' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/short-date',
			[
				'label'              => __( 'TTM: Short date', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'short_date' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/relative-date',
			[
				'label'              => __( 'TTM: Relative date', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'relative_date' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/category-count',
			[
				'label'              => __( 'TTM: Category count', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'category_count' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/today',
			[
				'label'              => __( 'TTM: Today', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'today' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);
	}

	/**
	 * `ttm/kicker`.
	 *
	 * @param array<string, mixed> $source_args     Unused: no args.
	 * @param WP_Block             $block_instance  Consuming block.
	 * @param string               $attribute_name  Consuming attribute.
	 * @return string
	 */
	public static function kicker( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post_id = (int) ( $block_instance->context['postId'] ?? 0 );
		if ( ! $post_id ) {
			return '';
		}

		$category_id  = PrimaryCategory::id( $post_id );
		$category     = $category_id ? get_term( $category_id, 'category' ) : null;
		$has_category = $category && ! is_wp_error( $category );

		$ctx = [
			'section_name' => $has_category ? $category->name : '',
			'is_politics'  => $has_category && 'politics' === $category->slug,
		];

		$series = Helpers::series_position( $post_id );
		if ( $series ) {
			$ctx['series_name'] = $series['name'];
			$ctx['part']        = $series['part'];
			$ctx['total']       = self::open_ended_total( $series['slug'] );
		}

		return self::finalize( Values::kicker( $ctx ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/meta-line`. The only source allowed to return HTML (a "Part N: {title}" link),
	 * and only into `core/paragraph`'s `content` attribute.
	 *
	 * @param array{parts?: string[]} $source_args    `{parts: string[]}`.
	 * @param WP_Block                $block_instance Consuming block.
	 * @param string                  $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function meta_line( array $source_args, $block_instance, string $attribute_name ): string {
		$post_id = (int) ( $block_instance->context['postId'] ?? 0 );
		$parts   = array_values( (array) ( $source_args['parts'] ?? [] ) );

		if ( ! $post_id || empty( $parts ) ) {
			return '';
		}

		$ctx = [];

		if ( in_array( 'date', $parts, true ) ) {
			$post        = get_post( $post_id );
			$ctx['date'] = $post ? Helpers::date_short( $post->post_date ) : '';
		}

		if ( in_array( 'reading', $parts, true ) ) {
			$ctx['reading'] = Helpers::reading_time(
				$post_id,
				/* translators: %d: minutes to read. */
				__( '%d min read', 'ttm-core' )
			);
		}

		if ( in_array( 'prev-part', $parts, true ) ) {
			$prev = self::previous_part( $post_id );
			if ( $prev ) {
				$ctx['prev_part'] = $prev;
			}
		}

		return self::finalize( Values::meta_line( $parts, $ctx ), $block_instance, $attribute_name, true );
	}

	/**
	 * `ttm/short-date`.
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function short_date( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post = self::context_post( $block_instance );
		if ( ! $post ) {
			return '';
		}

		$date = Clock::at( $post->post_date );
		if ( ! $date ) {
			return '';
		}

		return self::finalize( Values::short_date( $date, Clock::now() ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/relative-date`.
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function relative_date( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post = self::context_post( $block_instance );
		if ( ! $post ) {
			return '';
		}

		$date = Clock::at( $post->post_date );
		if ( ! $date ) {
			return '';
		}

		$window      = (int) Config::get( 'journal.relative_day_window', 6 );
		$rail_window = (int) Config::get( 'journal.rail_window_days', 30 );

		return self::finalize( Values::relative_date( $date, Clock::now(), $window, $rail_window ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/category-count`.
	 *
	 * @param array{category?: string, format?: string} $source_args    `{category: slug, format: articles|short}`.
	 * @param WP_Block                                  $block_instance Consuming block.
	 * @param string                                    $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function category_count( array $source_args, $block_instance, string $attribute_name ): string {
		$slug   = (string) ( $source_args['category'] ?? '' );
		$format = (string) ( $source_args['format'] ?? 'articles' );

		$term  = '' !== $slug ? get_term_by( 'slug', $slug, 'category' ) : null;
		$count = $term && ! is_wp_error( $term ) ? (int) $term->count : 0;

		return self::finalize( Values::category_count( $count, $format ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/today`.
	 *
	 * @param array{format?: string} $source_args    `{format: masthead|compact|year}`.
	 * @param WP_Block               $block_instance Consuming block.
	 * @param string                 $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function today( array $source_args, $block_instance, string $attribute_name ): string {
		$format = (string) ( $source_args['format'] ?? 'masthead' );

		return self::finalize( Values::today( Clock::now(), $format ), $block_instance, $attribute_name );
	}

	/**
	 * The consuming block's contextual post, or null.
	 *
	 * @param WP_Block $block_instance Consuming block.
	 * @return \WP_Post|null
	 */
	private static function context_post( $block_instance ): ?\WP_Post {
		$post_id = (int) ( $block_instance->context['postId'] ?? 0 );

		return $post_id ? get_post( $post_id ) : null;
	}

	/**
	 * The previous published part `{part, title, url}`, or null (none, or this is part 1).
	 *
	 * @param int $post_id Current post id.
	 * @return array{part:int, title:string, url:string}|null
	 */
	private static function previous_part( int $post_id ): ?array {
		$row = SeriesIndex::for_post( $post_id );
		if ( ! $row ) {
			return null;
		}

		$current_part = 0;
		foreach ( $row['parts'] as $entry ) {
			if ( (int) $entry['post_id'] === $post_id ) {
				$current_part = (int) $entry['part'];
				break;
			}
		}

		if ( $current_part <= 1 ) {
			return null;
		}

		foreach ( $row['parts'] as $entry ) {
			if ( (int) $entry['part'] === $current_part - 1 && 'publish' === $entry['status'] ) {
				return [
					'part'  => $current_part - 1,
					'title' => (string) $entry['title'],
					'url'   => (string) get_permalink( $entry['post_id'] ),
				];
			}
		}

		return null;
	}

	/**
	 * The series' declared total parts, or null when `ttm_total_parts` is open-ended (0/empty).
	 *
	 * @param string $slug Series term slug.
	 * @return int|null
	 */
	private static function open_ended_total( string $slug ): ?int {
		$term = get_term_by( 'slug', $slug, 'series' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$raw = (int) get_term_meta( $term->term_id, 'ttm_total_parts', true );

		return $raw > 0 ? $raw : null;
	}

	/**
	 * Only `ttm/meta-line` into `core/paragraph`'s `content` attribute may carry HTML; every
	 * other source/attribute combination is plain text (SPEC §6.3).
	 *
	 * @param string   $value          The source's raw value.
	 * @param WP_Block $block_instance Consuming block.
	 * @param string   $attribute_name Consuming attribute.
	 * @param bool     $allow_html     Whether this source is HTML-capable at all.
	 * @return string
	 */
	private static function finalize( string $value, $block_instance, string $attribute_name, bool $allow_html = false ): string {
		if ( $allow_html && 'core/paragraph' === ( $block_instance->name ?? '' ) && 'content' === $attribute_name ) {
			return $value;
		}

		return wp_strip_all_tags( $value );
	}
}
