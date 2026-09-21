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
use WP_Query;

/**
 * Filters `core/query` Query Loop blocks carrying a `ttmSection` context, and marks an
 * empty rendered section with `is-empty` (F17).
 */
class Cells {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'query_loop_block_query_vars', [ self::class, 'filter_query_vars' ], 10, 3 );
		add_filter( 'render_block_core/query', [ self::class, 'mark_empty' ], 10, 3 );
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
				// but only the 2 most recent (regardless of age; dates get their year via
				// ttm/short-date), and CSS drops the dek via the `is-stale` class mark_empty()
				// adds once the query has actually rendered these 2 rows.
				$query['posts_per_page'] = 2;
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
	 * F17: add `is-empty` to a section cell's wrapper when it rendered no posts.
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

		if ( false !== strpos( $content, 'class="wp-block-post ' ) ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag() ) {
			return $content;
		}

		$processor->add_class( 'is-empty' );

		return $processor->get_updated_html();
	}

	/**
	 * F9: whether a category's newest post is older than `cells.stale_year_days` (365 by
	 * default) -- "if < 1 post in a year, drop the dek and show the 2 most recent regardless
	 * of age". A category with no posts at all is not "stale" (F17's `is-empty` covers that).
	 *
	 * Public: `themes/ttm-theme/inc/patterns.php` calls this (guarded by `class_exists()`,
	 * SPEC §9) to decide, per request, whether to compile the `post-excerpt` block into that
	 * section's `ttm/section-cell-{slug}` pattern at all -- the theme reads plugin data, it
	 * doesn't query for it itself (rule 1).
	 *
	 * @param string $section Category slug.
	 * @return bool
	 */
	public static function is_stale_year( string $section ): bool {
		$query = new WP_Query(
			[
				'category_name'       => $section,
				'posts_per_page'      => 1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => 1,
			]
		);

		if ( empty( $query->posts ) ) {
			return false;
		}

		$newest = get_post( (int) $query->posts[0] );
		$date   = $newest ? Clock::at( $newest->post_date ) : null;

		if ( ! $date ) {
			return false;
		}

		$threshold = (int) Config::get( 'cells.stale_year_days', 365 );

		return Dates::days_between( $date, Clock::now() ) > $threshold;
	}
}
