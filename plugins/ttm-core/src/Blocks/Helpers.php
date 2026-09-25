<?php
/**
 * Shared helpers for `ttm/*` block render.php files (SPEC §6.1).
 *
 * @package TTM\Core\Blocks
 */

declare( strict_types=1 );

namespace TTM\Core\Blocks;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Meta\SeriesPosition;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Dates;
use TTM\Core\Support\Text;

/**
 * Pure-ish helpers: wrapper attributes, preview state, kicker/date/status formatting.
 */
class Helpers {

	/**
	 * Nesting depth inside `ttm/archive-by-year` (P5-02): incremented by
	 * `track_archive_scope()` on `render_block_data` when that block is encountered (before
	 * its inner `core/query`/`core/post-template` render), decremented by the block's own
	 * `render.php` after its content is built. `group_by_year()` (on
	 * `render_block_core/post-template`) only regroups rows into year sections while this is
	 * > 0. Lives here (not in `Query\Archive`) because it is purely block-render-scope state
	 * with no query dependency (SPEC §4.2).
	 *
	 * @var int
	 */
	public static int $archive_scope = 0;

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'render_block_data', [ self::class, 'track_archive_scope' ] );
		add_filter( 'render_block_core/post-template', [ self::class, 'group_by_year' ], 10, 1 );
		add_filter( 'render_block_core/post-featured-image', [ self::class, 'featured_caption' ], 10, 3 );
		add_filter( 'render_block_core/post-terms', [ self::class, 'style_tag_terms' ], 10, 2 );
		add_filter( 'render_block_core/post-excerpt', [ self::class, 'excerpt_markup' ], 10, 3 );
		add_filter( 'render_block_core/post-author-name', [ self::class, 'author_prefix' ], 10, 2 );
		add_filter( 'render_block_core/group', [ self::class, 'link_rows' ], 10, 3 );
		add_filter( 'render_block_core/search', [ self::class, 'style_search' ], 10, 1 );
	}

	/**
	 * Decision "Whole-row links": a `core/group` whose className contains `ttm-journal-row` or
	 * `ttm-archive-row`, rendered for a post, becomes that post's single anchor -- the outer
	 * `<div` opens as `<a href="{permalink}"` and the closing `</div>` becomes `</a>` (rule 33:
	 * one anchor per row; the inner `core/post-title` is `isLink: false`).
	 *
	 * @param string               $block_content Rendered group HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @param WP_Block|null        $instance      Block instance (postId context when available).
	 * @return string
	 */
	public static function link_rows( string $block_content, array $block = [], $instance = null ): string {
		$class = (string) ( $block['attrs']['className'] ?? '' );
		if ( ! preg_match( '/(^|\s)ttm-(journal|archive)-row(\s|$)/', $class ) ) {
			return $block_content;
		}

		// core/group declares no postId context; inside a post-template loop the queried post
		// is the current one.
		$post_id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}
		if ( ! $post_id ) {
			return $block_content;
		}

		// Rule 33: one anchor per row. A row that still carries its own link (a template not yet
		// switched to `isLink: false`) is left alone rather than nested inside a second anchor.
		if ( false !== stripos( $block_content, '<a ' ) ) {
			return $block_content;
		}

		$permalink = (string) get_permalink( $post_id );
		if ( '' === $permalink ) {
			return $block_content;
		}

		$open  = strpos( $block_content, '<div' );
		$close = strrpos( $block_content, '</div>' );
		if ( false === $open || false === $close || $close <= $open ) {
			return $block_content;
		}

		$html = substr_replace( $block_content, '</a>', $close, strlen( '</div>' ) );

		return substr_replace( $html, '<a href="' . esc_url( $permalink ) . '"', $open, strlen( '<div' ) );
	}

	/**
	 * `render_block_core/post-excerpt`: core runs every excerpt through `wp_trim_words()`, which
	 * strips tags, so a manual excerpt's inline `<code>`/`<em>`/`<strong>` never reaches the
	 * page. When the post has a manual excerpt, put its (kses-limited) markup back into the
	 * rendered paragraph (SPEC §6.2 "Article header": dek inline code).
	 *
	 * R1-08: scoped to `is-style-dek-l` (the article header's dek only, `article-header.php`)
	 * -- unscoped, this rewrote every excerpt site-wide (front-page lead dek, "More in" rows,
	 * archive rows, journal stream rows), not just the article header it was added for.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @param \WP_Block|null       $instance      Block instance (postId context), when given.
	 * @return string
	 */
	public static function excerpt_markup( string $block_content, array $block = [], $instance = null ): string {
		$class_name = (string) ( $block['attrs']['className'] ?? '' );
		if ( ! preg_match( '/(^|\s)is-style-dek-l(\s|$)/', $class_name ) ) {
			return $block_content;
		}

		$post_id = 0;
		if ( is_object( $instance ) && isset( $instance->context['postId'] ) ) {
			$post_id = (int) $instance->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}
		if ( ! $post_id || '' === $block_content ) {
			return $block_content;
		}

		$manual = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
		if ( '' === $manual || false === strpos( $manual, '<' ) ) {
			return $block_content;
		}

		$allowed = [
			'code'   => [],
			'em'     => [],
			'strong' => [],
		];
		$markup  = wp_kses( $manual, $allowed );

		return (string) preg_replace(
			'#(<p class="wp-block-post-excerpt__excerpt">).*?(</p>)#s',
			'$1' . str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], $markup ) . '$2',
			$block_content,
			1
		);
	}

	/**
	 * Decision "Search block classes": adds `input` to `.wp-block-search__input` and
	 * `btn btn-secondary` to `.wp-block-search__button` -- the theme's own input/button
	 * treatments, since `core/search` has no block-style API for either.
	 *
	 * @param string $block_content Rendered search block HTML.
	 * @return string
	 */
	public static function style_search( string $block_content ): string {
		// Scoped to the `<input`/`<button` tags themselves: the wrapping `<form>` also carries
		// a `wp-block-search__button-outside`-style class whose "button" prefix a looser regex
		// would also match.
		$block_content = (string) preg_replace(
			'/(<input\b[^>]*\bclass=")([^"]*)"/',
			'$1$2 input"',
			$block_content,
			1
		);

		return (string) preg_replace(
			'/(<button\b[^>]*\bclass=")([^"]*)"/',
			'$1$2 btn btn-secondary"',
			$block_content,
			1
		);
	}

	/**
	 * `render_block_core/post-author-name`: core has no prefix attribute, so the pattern's
	 * declared `prefix` ("By ") is prepended to the name inside the wrapper (SPEC §6.2
	 * byline "By {author_name}"; the string stays in the pattern, translatable there).
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public static function author_prefix( string $block_content, array $block = [] ): string {
		$prefix = (string) ( $block['attrs']['prefix'] ?? '' );
		if ( '' === $prefix || '' === $block_content ) {
			return $block_content;
		}

		return (string) preg_replace(
			'/(<div class="[^"]*wp-block-post-author-name[^"]*"[^>]*>)/',
			'$1' . esc_html( $prefix ),
			$block_content,
			1
		);
	}

	/**
	 * `render_block_core/post-terms`: when the block carries `is-style-tags`, every `<a>`
	 * becomes a `.tag.tag-neutral` chip and core's separator spans are dropped (SPEC §6.2
	 * "Article header", Decision "Byline tags").
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public static function style_tag_terms( string $block_content, array $block = [] ): string {
		$class_name = (string) ( $block['attrs']['className'] ?? '' );
		if ( '' === $block_content || ! preg_match( '/(^|\s)is-style-tags(\s|$)/', $class_name ) ) {
			return $block_content;
		}

		$block_content = (string) preg_replace( '#<span class="wp-block-post-terms__separator">.*?</span>#s', '', $block_content );

		return (string) preg_replace_callback(
			'/<a\b([^>]*)>/i',
			static function ( array $m ): string {
				$attrs = $m[1];
				if ( preg_match( '/\sclass="([^"]*)"/', $attrs, $c ) ) {
					return '<a' . str_replace( $c[0], ' class="' . trim( $c[1] . ' tag tag-neutral' ) . '"', $attrs ) . '>';
				}

				return '<a class="tag tag-neutral"' . $attrs . '>';
			},
			$block_content
		);
	}

	/**
	 * `render_block_core/post-featured-image`: on a singular view, append the attachment's
	 * caption as `figcaption.ttm-hero__caption` before the closing `</figure>` (SPEC §6.2
	 * "Hero", Decision "Featured-image caption"). No caption, not singular, or no figure ->
	 * unchanged.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @param \WP_Block|null       $instance      Block instance (postId context), when given.
	 * @return string
	 */
	public static function featured_caption( string $block_content, array $block = [], $instance = null ): string {
		unset( $block );

		if ( '' === $block_content || ! is_singular() ) {
			return $block_content;
		}

		$post_id = 0;
		if ( is_object( $instance ) && isset( $instance->context['postId'] ) ) {
			$post_id = (int) $instance->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}

		$attachment_id = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
		if ( ! $attachment_id ) {
			return $block_content;
		}

		$caption = trim( (string) wp_get_attachment_caption( $attachment_id ) );
		if ( '' === $caption ) {
			return $block_content;
		}

		$closing = strrpos( $block_content, '</figure>' );
		if ( false === $closing ) {
			return $block_content;
		}

		$figcaption = '<figcaption class="ttm-hero__caption">' . esc_html( $caption ) . '</figcaption>';

		return substr( $block_content, 0, $closing ) . $figcaption . substr( $block_content, $closing );
	}

	/**
	 * `render_block_data`: enter `ttm/archive-by-year` scope before its inner blocks render.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function track_archive_scope( array $parsed_block ): array {
		if ( 'ttm/archive-by-year' === ( $parsed_block['blockName'] ?? '' ) ) {
			++self::$archive_scope;
		}

		return $parsed_block;
	}

	/**
	 * `render_block_core/post-template`: inside `ttm/archive-by-year` only, split the rendered
	 * `<li>` rows into year sections (F15: a single-post year is still its own group).
	 *
	 * @param string $content Rendered `<ul>…</ul>` post-template HTML.
	 * @return string
	 */
	public static function group_by_year( string $content ): string {
		if ( self::$archive_scope <= 0 ) {
			return $content;
		}

		$chunks = preg_split( '/(?=<li\b)/', $content );
		if ( ! is_array( $chunks ) || count( $chunks ) < 2 ) {
			return $content;
		}

		array_shift( $chunks );
		// The opening `<ul …>`; each year gets its own `<ul>` instead.

		$last            = count( $chunks ) - 1;
		$chunks[ $last ] = (string) preg_replace( '/<\/ul>\s*$/', '', $chunks[ $last ] );

		$order  = [];
		$groups = [];

		foreach ( $chunks as $li ) {
			if ( ! preg_match( '/\bpost-(\d+)\b/', $li, $matches ) ) {
				continue;
			}

			$year = (int) get_post_time( 'Y', false, (int) $matches[1] );

			if ( ! isset( $groups[ $year ] ) ) {
				$groups[ $year ] = [];
				$order[]         = $year;
			}

			$groups[ $year ][] = $li;
		}

		$html = '';
		foreach ( $order as $year ) {
			$html .= sprintf(
				'<div class="ttm-archive-year"><p class="ttm-archive-year__label tnum">%1$d</p><ul class="wp-block-post-template ttm-archive-year__rows">%2$s</ul></div>',
				$year,
				implode( '', $groups[ $year ] )
			);
		}

		return $html;
	}

	/**
	 * Block wrapper attributes: `ttm-<name>` + any extra classes, `data-ttm-block="<name>"`.
	 *
	 * @param string               $name    Block name without the `ttm/` prefix.
	 * @param string[]             $classes Extra classes.
	 * @param array<string, mixed> $extra   Extra attributes (merged in by get_block_wrapper_attributes()).
	 * @return string
	 */
	public static function wrapper( string $name, array $classes = [], array $extra = [] ): string {
		$all_classes = array_merge( [ 'ttm-' . $name ], $classes );

		return get_block_wrapper_attributes(
			array_merge(
				[
					'class'          => implode( ' ', $all_classes ),
					'data-ttm-block' => $name,
				],
				$extra
			)
		);
	}

	/**
	 * Whether the current request is rendering inside the block editor (canvas or REST preview).
	 *
	 * @return bool
	 */
	public static function is_editor_preview(): bool {
		if ( is_admin() ) {
			return true;
		}

		$context = isset( $_GET['context'] ) ? sanitize_key( wp_unslash( $_GET['context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor-preview detection.

		return defined( 'REST_REQUEST' ) && REST_REQUEST && 'edit' === $context;
	}

	/**
	 * The effective preview state: `normal` outside the editor, else the block's own attribute.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function preview_state( array $attributes ): string {
		if ( ! self::is_editor_preview() ) {
			return 'normal';
		}

		return (string) ( $attributes['previewState'] ?? 'normal' );
	}

	/**
	 * "Technology · Series: Hardening WordPress, part 3 of 6".
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function kicker( int $post_id ): string {
		$parts = [];

		$category_id = PrimaryCategory::id( $post_id );
		if ( $category_id ) {
			$parts[] = get_cat_name( $category_id );
		}

		$series = self::series_position( $post_id );
		if ( $series ) {
			$parts[] = sprintf(
				/* translators: 1: series name, 2: part number, 3: total parts */
				__( 'Series: %1$s, part %2$d of %3$d', 'ttm-core' ),
				$series['name'],
				$series['part'],
				$series['total']
			);
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Reading time, formatted with `%d` for the minute count.
	 *
	 * @param int    $post_id Post id.
	 * @param string $format  sprintf() format containing one `%d`.
	 * @return string
	 */
	public static function reading_time( int $post_id, string $format ): string {
		$words   = (int) get_post_meta( $post_id, 'ttm_word_count', true );
		$minutes = Text::reading_minutes( $words, (int) Config::get( 'reading.words_per_minute', 230 ) );

		return sprintf( $format, $minutes );
	}

	/**
	 * `{name, slug, part, total, url}` for the series a post belongs to, or null.
	 *
	 * @param int $post_id Post id.
	 * @return array{name:string, slug:string, part:int, total:int, url:string}|null
	 */
	public static function series_position( int $post_id ): ?array {
		return SeriesPosition::for_post( $post_id );
	}

	/**
	 * "In progress" / "Complete" / "On hiatus".
	 *
	 * @param string $status ttm_status value.
	 * @return string
	 */
	public static function status_word( string $status ): string {
		switch ( $status ) {
			case 'complete':
				return __( 'Complete', 'ttm-core' );
			case 'hiatus':
				return __( 'On hiatus', 'ttm-core' );
			default:
				return __( 'In progress', 'ttm-core' );
		}
	}

	/**
	 * A MySQL datetime formatted with Dates::short() relative to now.
	 *
	 * @param string $mysql_date MySQL datetime string.
	 * @return string
	 */
	public static function date_short( string $mysql_date ): string {
		$date = Clock::at( $mysql_date );
		if ( ! $date ) {
			return '';
		}

		return Dates::short( $date, Clock::now() );
	}

	/**
	 * `wp_get_attachment_image()` with `loading="lazy"` unless fetchpriority is "high".
	 *
	 * @param int                  $attachment_id Attachment id.
	 * @param string               $size_key      Registered image size.
	 * @param array<string, mixed> $attrs         Extra `<img>` attributes.
	 * @return string
	 */
	public static function image( int $attachment_id, string $size_key, array $attrs = [] ): string {
		if ( ! $attachment_id ) {
			return '';
		}

		if ( ( $attrs['fetchpriority'] ?? '' ) !== 'high' ) {
			$attrs['loading'] = $attrs['loading'] ?? 'lazy';
		}

		return (string) wp_get_attachment_image( $attachment_id, $size_key, false, $attrs );
	}
}
