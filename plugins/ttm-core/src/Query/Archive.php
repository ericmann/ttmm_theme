<?php
/**
 * Category/tag/search archive query shaping and pagination labels (SPEC §6.4, §6.3).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use WP_Query;
use WP_Term;

/**
 * `pre_get_posts` on the main front-end query only, plus the pure year-range lookup the
 * query-pagination relabel uses. The relabel filters themselves (and the "Older"/"← Newer"
 * strings) live in `Bindings\Sources`/`Bindings\Values` — `Bindings\Values` is
 * the single source of those strings (SPEC §4.2: `Bindings/` may import `Query/`, not the other
 * way around, so the formatting belongs on that side of the boundary). The
 * `ttm/archive-by-year` inner post-template grouping (F15) lives in `Blocks\Helpers` — it is
 * purely block-render-scope state with no query dependency, and SPEC §4.2 forbids `Query/`
 * importing `Blocks/`.
 */
class Archive {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'pre_get_posts', [ self::class, 'shape' ] );
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
	 * The year range for a pagination direction's target page, from one bounded, ids-only
	 * `WP_Query` reusing the main query's vars. `null` when there is no such page. Pure in the
	 * sense that it hands back data, not a formatted string -- `Bindings\Sources` turns this
	 * into "Older (2014–2022) →" / "← Newer (2023)" via `Bindings\Values::pagination_label()`,
	 * the single source of those two strings (SPEC §4.2: `Query/` may not import `Bindings/`,
	 * so the formatting cannot live here).
	 *
	 * @param string $dir `older` (next page) or `newer` (previous page).
	 * @return array{from:int,to:int}|null
	 */
	public static function year_range( string $dir ): ?array {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query ) {
			return null;
		}

		$paged   = (int) $wp_query->get( 'paged' );
		$current = max( 1, 0 === $paged ? 1 : $paged );
		$target  = 'newer' === $dir ? $current - 1 : $current + 1;

		if ( $target < 1 ) {
			return null;
		}

		$max_pages = (int) $wp_query->max_num_pages;
		if ( $max_pages > 0 && $target > $max_pages ) {
			return null;
		}

		$args                  = $wp_query->query_vars;
		$args['paged']         = $target;
		$args['fields']        = 'ids';
		$args['no_found_rows'] = true;

		$target_query = new WP_Query( $args );

		if ( empty( $target_query->posts ) ) {
			return null;
		}

		$years = [];
		foreach ( $target_query->posts as $post_id ) {
			$years[] = (int) get_the_date( 'Y', $post_id );
		}

		return [
			'from' => min( $years ),
			'to'   => max( $years ),
		];
	}
}
