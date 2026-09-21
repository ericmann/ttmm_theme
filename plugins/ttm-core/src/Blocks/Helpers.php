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
				'<div class="ttm-archive-year"><h2 class="ttm-archive-year__label tnum">%1$d</h2><ul class="wp-block-post-template ttm-archive-year__rows">%2$s</ul></div>',
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
