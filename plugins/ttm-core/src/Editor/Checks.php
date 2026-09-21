<?php
/**
 * Supplies the data the editor's pure pre-publish checks (checks.js) need.
 *
 * @package TTM\Core\Editor
 */

declare( strict_types=1 );

namespace TTM\Core\Editor;

use TTM\Core\Config;
use TTM\Core\Query\SeriesIndex;

/**
 * Server-side data for editor/checks.js, injected via Sidebar's inline script.
 */
class Checks {

	/**
	 * `{ [seriesId]: { [part]: postId } }` for every series, from the series index.
	 *
	 * @return array<int, array<int, int>>
	 */
	public static function series_parts(): array {
		$map = [];

		foreach ( SeriesIndex::all() as $row ) {
			$parts = [];
			foreach ( $row['parts'] as $part ) {
				if ( $part['part'] > 0 ) {
					$parts[ $part['part'] ] = $part['post_id'];
				}
			}
			$map[ $row['id'] ] = $parts;
		}

		return $map;
	}

	/**
	 * `{ [categoryId]: count }` of ttm_featured_in_section posts per category, and the configured limit.
	 *
	 * @return array{counts: array<int, int>, limit: int}
	 */
	public static function most_read(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT tt.term_id AS cat, COUNT(*) AS cnt
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'post' AND p.post_status = 'publish'
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
			WHERE pm.meta_key = 'ttm_featured_in_section' AND pm.meta_value = '1'
			GROUP BY tt.term_id",
			ARRAY_A
		);

		$counts = [];
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['cat'] ] = (int) $row['cnt'];
		}

		return [
			'counts' => $counts,
			'limit'  => (int) Config::get( 'archive.most_read_limit', 3 ),
		];
	}
}
