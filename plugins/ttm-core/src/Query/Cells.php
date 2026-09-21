<?php
/**
 * Section "cell" Query Loop filtering (SPEC §6.4, 03 §8, F17).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use WP_Block;
use WP_HTML_Tag_Processor;

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

		$context      = $block->context['query'] ?? [];
		$section      = (string) ( $context['ttmSection'] ?? '' );
		$exclude_lead = ! empty( $context['ttmExcludeLead'] );
		$primary_only = array_key_exists( 'ttmPrimaryOnly', $context ) ? (bool) $context['ttmPrimaryOnly'] : ( '' !== $section );

		if ( '' !== $section ) {
			$query['category_name']       = $section;
			$query['ignore_sticky_posts'] = 1;

			$counts = (array) Config::get( 'cells.counts', [] );
			if ( isset( $counts[ $section ] ) ) {
				$query['posts_per_page'] = (int) $counts[ $section ];
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

		$section = (string) ( $block->attributes['query']['ttmSection'] ?? '' );
		if ( '' === $section ) {
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
}
