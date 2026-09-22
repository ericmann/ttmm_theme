<?php
/**
 * Section "cell" Query Loop filtering (SPEC §6.4, 03 §8, F17).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Dates;
use WP_Block;
use WP_HTML_Tag_Processor;

/**
 * Filters `core/query` Query Loop blocks carrying a `ttmSection` context, marks an empty
 * rendered section with `is-empty` (F17), and suppresses the dek in a stale-year section (F9).
 */
class Cells {

	/**
	 * Depth counter for "currently inside a stale-year section's `core/query` render" --
	 * entered in `track_stale_scope()` (`render_block_data`, before the query's children
	 * render), left in `mark_empty()` (`render_block_core/query`, after they have). Mirrors
	 * `Blocks\Helpers::$archive_scope` (SPEC §4.2: purely block-render-scope state, no query
	 * dependency, so it lives on the filter pair rather than in `Query\Archive`).
	 *
	 * @var int
	 */
	private static int $stale_scope = 0;

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'query_loop_block_query_vars', [ self::class, 'filter_query_vars' ], 10, 3 );
		add_filter( 'render_block_data', [ self::class, 'track_stale_scope' ] );
		add_filter( 'render_block_core/query', [ self::class, 'mark_empty' ], 10, 3 );
		add_filter( 'render_block_core/post-excerpt', [ self::class, 'suppress_stale_dek' ] );
	}

	/**
	 * Shape a section cell's query: category, primary-only meta, count, exclude-lead,
	 * exclude-Journal, and never let a section's own stickies reorder it.
	 *
	 * @param array<string, mixed> $query Existing query vars.
	 * @param WP_Block             $block The query block.
	 * @param int                  $page  Current page (unused).
	 * @return array<string, mixed>
	 */
	public static function filter_query_vars( array $query, $block, int $page ): array {
		unset( $page );

		$context         = $block->context['query'] ?? [];
		$section         = (string) ( $context['ttmSection'] ?? '' );
		$exclude_lead    = ! empty( $context['ttmExcludeLead'] );
		$primary_only    = array_key_exists( 'ttmPrimaryOnly', $context ) ? (bool) $context['ttmPrimaryOnly'] : ( '' !== $section );
		$same_section    = ! empty( $context['ttmSameSection'] );
		$exclude_current = ! empty( $context['ttmExcludeCurrent'] );

		if ( '' !== $section ) {
			$query['category_name']       = $section;
			$query['ignore_sticky_posts'] = 1;

			if ( self::is_stale_year( $section ) ) {
				// F9: the section's newest post is over a year old -- still show what exists,
				// but only the cells.stale_count most recent (regardless of age; dates get
				// their year via ttm/short-date). The dek itself is dropped by
				// suppress_stale_dek() while $stale_scope is entered, and mark_empty() adds
				// the `is-stale` class once the query has actually rendered these rows.
				$query['posts_per_page'] = (int) Config::get( 'cells.stale_count', 2 );
			} else {
				$counts = (array) Config::get( 'cells.counts', [] );
				if ( isset( $counts[ $section ] ) ) {
					$query['posts_per_page'] = (int) $counts[ $section ];
				}
			}

			if ( $primary_only ) {
				$term = get_term_by( 'slug', $section, 'category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- ttm_primary_category is a single, indexed meta key; bounded by posts_per_page above.
						[
							'key'   => 'ttm_primary_category',
							'value' => $term->term_id,
						],
					];
				}
			}
		}//end if

		if ( $same_section ) {
			$current_id  = (int) get_the_ID();
			$category_id = $current_id ? PrimaryCategory::id( $current_id ) : 0;

			if ( $category_id ) {
				$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- ttm_primary_category is a single, indexed meta key; bounded by posts_per_page.
					[
						'key'   => 'ttm_primary_category',
						'value' => $category_id,
					],
				];
			}

			$query['posts_per_page']      = (int) Config::get( 'article.more_in_section', 3 );
			$query['ignore_sticky_posts'] = 1;
		}

		if ( $exclude_current ) {
			$current_id = (int) get_the_ID();
			if ( $current_id ) {
				$query['post__not_in'] = array_merge( $query['post__not_in'] ?? [], [ $current_id ] );
			}
		}

		if ( $exclude_lead ) {
			$lead_id = Lead::id();
			if ( $lead_id ) {
				$query['post__not_in'] = array_merge( $query['post__not_in'] ?? [], [ $lead_id ] );
			}
		}

		$journal_slug = (string) Config::get( 'sections.journal_slug', 'journal' );

		if ( $section !== $journal_slug ) {
			$journal = get_term_by( 'slug', $journal_slug, 'category' );
			if ( $journal && ! is_wp_error( $journal ) ) {
				$query['category__not_in'] = array_merge( $query['category__not_in'] ?? [], [ $journal->term_id ] );
			}
		}

		// The journal-stream row (single-journal.html) reuses the journal section query but
		// with journal.stream_count instead of cells.counts (which has no "journal" entry);
		// ttmExcludeCurrent is what distinguishes it from the front page's journal-rail, which
		// never has a "current" post to exclude and instead takes its count from
		// journal.rail_count (previously baked into the journal-rail pattern's own `perPage`
		// attribute as a bare 3, rather than driven by Config).
		if ( $journal_slug === $section ) {
			$query['posts_per_page'] = $exclude_current
				? (int) Config::get( 'journal.stream_count', 4 )
				: (int) Config::get( 'journal.rail_count', 3 );
		}

		return $query;
	}

	/**
	 * `render_block_data`: enter stale-section scope before a stale `core/query`'s children
	 * render, so `suppress_stale_dek()` can drop their `post-excerpt` output. Mirrors
	 * `Blocks\Helpers::track_archive_scope()`.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function track_stale_scope( array $parsed_block ): array {
		if ( 'core/query' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$section = (string) ( $parsed_block['attrs']['query']['ttmSection'] ?? '' );

		if ( '' !== $section && self::is_stale_year( $section ) ) {
			++self::$stale_scope;
		}

		return $parsed_block;
	}

	/**
	 * `render_block_core/post-excerpt`: suppress the dek entirely while inside a stale-year
	 * section's query (F9), or when the current post has no manual excerpt of its own (F10) --
	 * the theme's pattern always includes the block; the plugin decides whether it renders
	 * (SPEC §3.1 rule 1: the theme reads plugin data, it doesn't vary itself). Checks the raw
	 * `post_excerpt` field, not `get_the_excerpt()`, so a Journal post's derived excerpt (filled
	 * in by `Query\JournalExcerpt` on the `get_the_excerpt` filter, never on `post_excerpt`
	 * itself) still renders -- only a genuinely dek-less post (no manual excerpt, not Journal)
	 * is suppressed, matching the mock's own "no dek" cells.
	 *
	 * @param string $content Rendered excerpt HTML.
	 * @return string
	 */
	public static function suppress_stale_dek( string $content ): string {
		if ( self::$stale_scope > 0 ) {
			return '';
		}

		$post = get_post();
		if ( $post instanceof \WP_Post && '' === $post->post_excerpt && ! self::is_journal( $post ) ) {
			return '';
		}

		return $content;
	}

	/**
	 * Whether a post's primary category is Journal -- mirrors `Query\JournalExcerpt`'s own
	 * private check (no shared public helper exists for it yet).
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool
	 */
	private static function is_journal( \WP_Post $post ): bool {
		$category_id = PrimaryCategory::id( $post->ID );
		if ( ! $category_id ) {
			return false;
		}

		$category = get_term( $category_id, 'category' );

		return $category && ! is_wp_error( $category ) && (string) Config::get( 'sections.journal_slug', 'journal' ) === $category->slug;
	}

	/**
	 * F17/F9: add `is-empty` to a section cell's wrapper when it rendered no posts, and
	 * `is-stale` when its section is a stale year (F9) -- also leaves the scope
	 * `track_stale_scope()` entered, once this query's children have fully rendered.
	 *
	 * The Query block itself only provides `query` as context to its children (it does not
	 * use it), so the section slug is read from the block's own `query` attribute rather than
	 * from `$block->context`.
	 *
	 * @param string               $content      Rendered block HTML.
	 * @param array<string, mixed> $parsed_block Parsed block (unused).
	 * @param WP_Block             $block        The query block.
	 * @return string
	 */
	public static function mark_empty( string $content, $parsed_block, $block ): string {
		unset( $parsed_block );

		$query_attrs = $block->attributes['query'] ?? [];
		$section     = (string) ( $query_attrs['ttmSection'] ?? '' );
		$is_ttm_cell = '' !== $section || ! empty( $query_attrs['ttmSameSection'] );

		if ( ! $is_ttm_cell ) {
			return $content;
		}

		$is_stale = '' !== $section && self::is_stale_year( $section );

		if ( $is_stale ) {
			self::$stale_scope = max( 0, self::$stale_scope - 1 );
		}

		$is_empty = false === strpos( $content, 'class="wp-block-post ' );

		if ( ! $is_empty && ! $is_stale ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag() ) {
			return $content;
		}

		if ( $is_empty ) {
			$processor->add_class( 'is-empty' );
		}

		if ( $is_stale ) {
			$processor->add_class( 'is-stale' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * F9: whether a category's newest post is older than `cells.stale_year_days` (365 by
	 * default) -- "if < 1 post in a year, drop the dek and show the cells.stale_count most
	 * recent regardless of age". A category with no posts at all is not "stale" (F17's
	 * `is-empty` covers that).
	 *
	 * Reads `Query\Stats::category()`'s cached `newest_date` -- maintained by `Query\Stats` and
	 * flushed on `transition_post_status` -- rather than running its own `WP_Query` (SPEC §3.2
	 * rule 13: derived data lives in a transient, recomputed only on write hooks).
	 *
	 * @param string $section Category slug.
	 * @return bool
	 */
	public static function is_stale_year( string $section ): bool {
		$term = get_term_by( 'slug', $section, 'category' );

		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		$newest_date = Stats::category( (int) $term->term_id )['newest_date'] ?? null;

		if ( null === $newest_date ) {
			return false;
		}

		$date = Clock::at( (string) $newest_date );

		if ( ! $date ) {
			return false;
		}

		$threshold = (int) Config::get( 'cells.stale_year_days', 365 );

		return Dates::days_between( $date, Clock::now() ) > $threshold;
	}
}
