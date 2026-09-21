<?php
/**
 * Category/tag/search archive query shaping and pagination labels (SPEC §6.4, §6.3).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Bindings\Values;
use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use WP_Query;
use WP_Term;

/**
 * `pre_get_posts` on the main front-end query only; also relabels the core query-pagination
 * next/previous blocks when their query inherits the main query (Decisions), and groups
 * `ttm/archive-by-year`'s inner post-template rows into year sections (F15).
 */
class Archive {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'pre_get_posts', [ self::class, 'shape' ] );
		add_filter( 'render_block_core/query-pagination-next', [ self::class, 'label_next' ], 10, 3 );
		add_filter( 'render_block_core/query-pagination-previous', [ self::class, 'label_previous' ], 10, 3 );
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
			++Helpers::$archive_scope;
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
		if ( Helpers::$archive_scope <= 0 ) {
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
	 * Shape the main front-end query: archive per-page, Journal per-page, `?tag=` narrowing,
	 * and Journal exclusion from the main feed.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function shape( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$journal_slug = (string) Config::get( 'sections.journal_slug', 'journal' );

		if ( $query->is_feed() && ! $query->is_archive() ) {
			if ( ! Config::get( 'journal_in_main_feed' ) ) {
				$journal = get_term_by( 'slug', $journal_slug, 'category' );
				if ( $journal instanceof WP_Term ) {
					$query->set( 'category__not_in', array_merge( (array) $query->get( 'category__not_in' ), [ $journal->term_id ] ) );
				}
			}
			return;
		}

		if ( $query->is_category() ) {
			$term     = get_queried_object();
			$per_page = (int) Config::get( 'archive.per_page', 12 );

			if ( $term instanceof WP_Term && $journal_slug === $term->slug ) {
				$per_page = (int) Config::get( 'journal.archive_per_page', 20 );
			}

			$query->set( 'posts_per_page', $per_page );

			$tag = isset( $_GET['tag'] ) ? sanitize_title( wp_unslash( $_GET['tag'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only archive filter, no state change.
			if ( '' !== $tag ) {
				$query->set( 'tag', $tag );
			}
			return;
		}

		if ( $query->is_search() || $query->is_tag() || $query->is_date() ) {
			$query->set( 'posts_per_page', (int) Config::get( 'archive.per_page', 12 ) );
		}
	}

	/**
	 * `render_block_core/query-pagination-next`: relabel "Older (2014–2022) →" when the
	 * pagination's query inherits the main query.
	 *
	 * @param string               $content      Rendered block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block (unused).
	 * @param \WP_Block            $block        The pagination-next block.
	 * @return string
	 */
	public static function label_next( string $content, $parsed_block, $block ): string {
		unset( $parsed_block );

		return self::relabel( $content, $block, 'older' );
	}

	/**
	 * `render_block_core/query-pagination-previous`: relabel "← Newer (2023–2026)".
	 *
	 * @param string               $content      Rendered block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block (unused).
	 * @param \WP_Block            $block        The pagination-previous block.
	 * @return string
	 */
	public static function label_previous( string $content, $parsed_block, $block ): string {
		unset( $parsed_block );

		return self::relabel( $content, $block, 'newer' );
	}

	/**
	 * Swap a pagination link's text for the year-range label, only when the query inherits the
	 * main query (a custom, non-inheriting query keeps core's own "Older"/"Newer" markup).
	 *
	 * @param string    $content Rendered block HTML.
	 * @param \WP_Block $block  The pagination-next/previous block.
	 * @param string    $dir     `older` or `newer`.
	 * @return string
	 */
	private static function relabel( string $content, $block, string $dir ): string {
		if ( empty( $block->context['query']['inherit'] ) ) {
			return $content;
		}

		$label = self::resolve_label( $dir );
		if ( '' === $label ) {
			return $content;
		}

		if ( preg_match( '/(<a[^>]*>)(.*?)(<\/a>)/s', $content, $matches ) ) {
			return $matches[1] . esc_html( $label ) . $matches[3];
		}

		return $content;
	}

	/**
	 * The pagination label for a direction, from the main query's year range on the target
	 * page (one bounded, ids-only `WP_Query` with the same query vars). `''` when there is no
	 * such page.
	 *
	 * @param string $dir `older` (next page) or `newer` (previous page).
	 * @return string
	 */
	public static function resolve_label( string $dir ): string {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query ) {
			return '';
		}

		$paged   = (int) $wp_query->get( 'paged' );
		$current = max( 1, 0 === $paged ? 1 : $paged );
		$target  = 'newer' === $dir ? $current - 1 : $current + 1;

		if ( $target < 1 ) {
			return '';
		}

		$max_pages = (int) $wp_query->max_num_pages;
		if ( $max_pages > 0 && $target > $max_pages ) {
			return '';
		}

		$args                  = $wp_query->query_vars;
		$args['paged']         = $target;
		$args['fields']        = 'ids';
		$args['no_found_rows'] = true;

		$target_query = new WP_Query( $args );

		if ( empty( $target_query->posts ) ) {
			return '';
		}

		$years = [];
		foreach ( $target_query->posts as $post_id ) {
			$years[] = (int) get_the_date( 'Y', $post_id );
		}

		return Values::pagination_label( $dir, min( $years ), max( $years ) );
	}
}
