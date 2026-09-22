<?php
/**
 * Registers the front-page block-binding sources (SPEC §6.3, 05 §4).
 *
 * @package TTM\Core\Bindings
 */

declare( strict_types=1 );

namespace TTM\Core\Bindings;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Meta\SeriesPosition;
use TTM\Core\Query\Archive;
use TTM\Core\Query\SeriesIndex;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Dates;
use TTM\Core\Support\Html;
use TTM\Core\Support\Text;
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
		add_filter( 'render_block_core/query-pagination-next', [ self::class, 'label_next' ], 10, 3 );
		add_filter( 'render_block_core/query-pagination-previous', [ self::class, 'label_previous' ], 10, 3 );
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

		register_block_bindings_source(
			'ttm/reading-time',
			[
				'label'              => __( 'TTM: Reading time', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'reading_time' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/word-count',
			[
				'label'              => __( 'TTM: Word count', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'word_count' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/journal-subline',
			[
				'label'              => __( 'TTM: Journal subline', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'journal_subline' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/series-name',
			[
				'label'              => __( 'TTM: Series name', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'series_name' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/series-part',
			[
				'label'              => __( 'TTM: Series part', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'series_part' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);

		register_block_bindings_source(
			'ttm/pagination-label',
			[
				'label'              => __( 'TTM: Pagination label', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'pagination_label' ],
			]
		);

		register_block_bindings_source(
			'ttm/verse-copyright',
			[
				'label'              => __( 'TTM: Verse copyright', 'ttm-core' ),
				'get_value_callback' => [ self::class, 'verse_copyright' ],
			]
		);
	}

	/**
	 * `ttm/verse-copyright` (SPEC §6.4): the stored verse's NIV copyright notice, plain text,
	 * only when `verse.copyright_placement` is `'footer'` and a verse is stored.
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function verse_copyright( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		if ( 'footer' !== Config::get( 'verse.copyright_placement', 'footer' ) ) {
			return '';
		}

		$verse = get_option( 'ttm_verse' );

		return self::finalize( (string) ( $verse['copyright'] ?? '' ), $block_instance, $attribute_name );
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
			'is_politics'  => $has_category && (string) Config::get( 'sections.politics_slug', 'politics' ) === $category->slug,
		];

		$series = SeriesPosition::for_post( $post_id );
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
			$ctx['date'] = $post ? self::short_date_string( $post->post_date ) : '';
		}

		if ( in_array( 'reading', $parts, true ) ) {
			$ctx['reading'] = self::reading_time_string(
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

		if ( in_array( 'tags-or-series', $parts, true ) ) {
			$ctx['tags_or_series'] = self::tags_or_series( $post_id );
		}

		if ( self::in_politics( $post_id ) ) {
			$ctx['politics'] = true;
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
		$text  = Values::category_count( $count, $format );

		if ( $term && ! is_wp_error( $term ) ) {
			$link = (string) get_category_link( $term );
			return self::finalize( Html::link( $link, $text ), $block_instance, $attribute_name, true );
		}

		return self::finalize( $text, $block_instance, $attribute_name );
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

		if ( 'footer' === $format ) {
			$value = Values::footer_line( get_bloginfo( 'name' ), Clock::now()->format( 'Y' ) );
			return self::finalize( $value, $block_instance, $attribute_name );
		}

		return self::finalize( Values::today( Clock::now(), $format ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/reading-time`. `''` for a Journal post (03 §10: reading time is not shown there).
	 *
	 * @param array{format?: string} $source_args    `{format: long|short}`.
	 * @param WP_Block               $block_instance Consuming block.
	 * @param string                 $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function reading_time( array $source_args, $block_instance, string $attribute_name ): string {
		$format  = 'short' === ( $source_args['format'] ?? 'long' ) ? 'short' : 'long';
		$post_id = (int) ( $block_instance->context['postId'] ?? 0 );

		if ( ! $post_id ) {
			return '';
		}

		$words = (int) get_post_meta( $post_id, 'ttm_word_count', true );
		$wpm   = (int) Config::get( 'reading.words_per_minute', 230 );

		return self::finalize( Values::reading_time( $words, $wpm, $format, self::is_journal_post( $post_id ) ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/word-count`.
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function word_count( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post_id = (int) ( $block_instance->context['postId'] ?? 0 );
		if ( ! $post_id ) {
			return '';
		}

		$words = (int) get_post_meta( $post_id, 'ttm_word_count', true );

		return self::finalize( Values::word_count( $words ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/journal-subline`: "Sunday · Portland" (location appended only when set).
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function journal_subline( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post = self::context_post( $block_instance );
		if ( ! $post ) {
			return '';
		}

		$date = Clock::at( $post->post_date );
		if ( ! $date ) {
			return '';
		}

		$location = (string) get_post_meta( $post->ID, 'ttm_location', true );

		return self::finalize( Values::journal_subline( $date, $location ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/series-name`.
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function series_name( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post_id  = (int) ( $block_instance->context['postId'] ?? 0 );
		$position = $post_id ? SeriesPosition::for_post( $post_id ) : null;

		return self::finalize( Values::series_name( $position ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/series-part`: "Part 3 of 6", or F23's open-ended "Part 3".
	 *
	 * @param array<string, mixed> $source_args    Unused: no args.
	 * @param WP_Block             $block_instance Consuming block.
	 * @param string               $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function series_part( array $source_args, $block_instance, string $attribute_name ): string {
		unset( $source_args );

		$post_id  = (int) ( $block_instance->context['postId'] ?? 0 );
		$position = $post_id ? SeriesPosition::for_post( $post_id ) : null;

		if ( null !== $position ) {
			$position['total'] = self::open_ended_total( $position['slug'] );
		}

		return self::finalize( Values::series_part( $position ), $block_instance, $attribute_name );
	}

	/**
	 * `ttm/pagination-label`: "Older (2014–2022) →" / "← Newer (2023–2026)".
	 *
	 * @param array{dir?: string} $source_args    `{dir: older|newer}`.
	 * @param WP_Block            $block_instance Consuming block.
	 * @param string              $attribute_name Consuming attribute.
	 * @return string
	 */
	public static function pagination_label( array $source_args, $block_instance, string $attribute_name ): string {
		$dir = 'newer' === ( $source_args['dir'] ?? 'older' ) ? 'newer' : 'older';

		return self::finalize( self::resolve_pagination_label( $dir ), $block_instance, $attribute_name );
	}

	/**
	 * `render_block_core/query-pagination-next`: relabel "Older (2014–2022) →" when the
	 * pagination's query inherits the main query.
	 *
	 * @param string               $content      Rendered block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block (unused).
	 * @param WP_Block             $block        The pagination-next block.
	 * @return string
	 */
	public static function label_next( string $content, $parsed_block, $block ): string {
		unset( $parsed_block );

		return self::relabel_pagination( $content, $block, 'older' );
	}

	/**
	 * `render_block_core/query-pagination-previous`: relabel "← Newer (2023–2026)".
	 *
	 * @param string               $content      Rendered block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block (unused).
	 * @param WP_Block             $block        The pagination-previous block.
	 * @return string
	 */
	public static function label_previous( string $content, $parsed_block, $block ): string {
		unset( $parsed_block );

		return self::relabel_pagination( $content, $block, 'newer' );
	}

	/**
	 * Swap a pagination link's text for the year-range label, only when the query inherits the
	 * main query (a custom, non-inheriting query keeps core's own "Older"/"Newer" markup).
	 *
	 * @param string   $content Rendered block HTML.
	 * @param WP_Block $block   The pagination-next/previous block.
	 * @param string   $dir     `older` or `newer`.
	 * @return string
	 */
	private static function relabel_pagination( string $content, $block, string $dir ): string {
		if ( empty( $block->context['query']['inherit'] ) ) {
			return $content;
		}

		$label = self::resolve_pagination_label( $dir );
		if ( '' === $label ) {
			return $content;
		}

		if ( preg_match( '/(<a[^>]*>)(.*?)(<\/a>)/s', $content, $matches ) ) {
			return $matches[1] . esc_html( $label ) . $matches[3];
		}

		return $content;
	}

	/**
	 * The formatted pagination label for a direction, from `Query\Archive::year_range()`'s pure
	 * year-range lookup and `Values::pagination_label()`'s formatting -- the single source of
	 * the "Older"/"← Newer" translatable strings. `''` when there is no target page.
	 *
	 * @param string $dir `older` or `newer`.
	 * @return string
	 */
	public static function resolve_pagination_label( string $dir ): string {
		$range = Archive::year_range( $dir );

		if ( null === $range ) {
			return '';
		}

		return Values::pagination_label( $dir, $range['from'], $range['to'] );
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
	 * A MySQL datetime formatted with Dates::short() relative to now. Duplicated here (rather
	 * than reused from `Blocks\Helpers::date_short()`) because `Bindings/` may not import
	 * `Blocks/` (SPEC §4.2); both compositions of `Support\Clock` + `Support\Dates` stay in
	 * sync because neither has any other logic.
	 *
	 * @param string $mysql_date MySQL datetime string.
	 * @return string
	 */
	private static function short_date_string( string $mysql_date ): string {
		$date = Clock::at( $mysql_date );
		if ( ! $date ) {
			return '';
		}

		return Dates::short( $date, Clock::now() );
	}

	/**
	 * Reading time, formatted with `%d` for the minute count. Duplicated here (rather than
	 * reused from `Blocks\Helpers::reading_time()`) because `Bindings/` may not import
	 * `Blocks/` (SPEC §4.2).
	 *
	 * @param int    $post_id Post id.
	 * @param string $format  sprintf() format containing one `%d`.
	 * @return string
	 */
	private static function reading_time_string( int $post_id, string $format ): string {
		$words   = (int) get_post_meta( $post_id, 'ttm_word_count', true );
		$minutes = Text::reading_minutes( $words, (int) Config::get( 'reading.words_per_minute', 230 ) );

		return sprintf( $format, $minutes );
	}

	/**
	 * Whether a post carries the "politics" category (Opinion's child, 03 §1), in any position
	 * — not just primary.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function in_politics( int $post_id ): bool {
		$politics_slug = (string) Config::get( 'sections.politics_slug', 'politics' );

		foreach ( wp_get_post_categories( $post_id ) as $category_id ) {
			$category = get_term( $category_id, 'category' );
			if ( $category && ! is_wp_error( $category ) && $politics_slug === $category->slug ) {
				return true;
			}
		}

		return false;
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
	 * Whether a post's primary category is Journal.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function is_journal_post( int $post_id ): bool {
		$category_id = PrimaryCategory::id( $post_id );
		if ( ! $category_id ) {
			return false;
		}

		$category = get_term( $category_id, 'category' );

		return $category && ! is_wp_error( $category ) && (string) Config::get( 'sections.journal_slug', 'journal' ) === $category->slug;
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
	 * "Series: Reading CVEs, 4 of 4" when the post is in a series, else up to
	 * `archive.row_tags` tag names joined with ", "; `''` with neither (P5-04 archive row).
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function tags_or_series( int $post_id ): string {
		$series = SeriesPosition::for_post( $post_id );

		if ( $series ) {
			return Values::series_tag_label( $series['name'], $series['part'], self::open_ended_total( $series['slug'] ) );
		}

		$limit = (int) Config::get( 'archive.row_tags', 2 );
		$tags  = get_the_tags( $post_id );

		if ( ! is_array( $tags ) || empty( $tags ) ) {
			return '';
		}

		$names = wp_list_pluck( array_slice( $tags, 0, $limit ), 'name' );

		return implode( ', ', $names );
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
