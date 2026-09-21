<?php
/**
 * A post's position within its series, read from the `ttm_series_index` option
 * (SPEC §4.2: Meta/ may not import Query/, so this reads the derived-data option directly
 * rather than going through Query\SeriesIndex — the option is the shared contract, per the
 * "derived data is read from options/transients" rule).
 *
 * @package TTM\Core\Meta
 */

declare( strict_types=1 );

namespace TTM\Core\Meta;

/**
 * Read-only lookup of a post's series position.
 */
class SeriesPosition {

	/**
	 * The option `Query\SeriesIndex::rebuild()` writes to. Duplicated here (rather than
	 * imported) because Meta/ may not import Query/ (SPEC §4.2).
	 */
	private const OPTION = 'ttm_series_index';

	/**
	 * `{name, slug, part, total, url}` for the series a post belongs to, or null.
	 *
	 * @param int $post_id Post id.
	 * @return array{name:string, slug:string, part:int, total:int, url:string}|null
	 */
	public static function for_post( int $post_id ): ?array {
		foreach ( (array) get_option( self::OPTION, [] ) as $row ) {
			foreach ( (array) ( $row['parts'] ?? [] ) as $entry ) {
				if ( (int) $entry['post_id'] === $post_id ) {
					return [
						'name'  => (string) $row['name'],
						'slug'  => (string) $row['slug'],
						'part'  => (int) $entry['part'],
						'total' => (int) $row['total'],
						'url'   => home_url( '/series/' . $row['slug'] . '/' ),
					];
				}
			}
		}

		return null;
	}
}
