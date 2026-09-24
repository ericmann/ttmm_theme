<?php
/**
 * `wp ttm primary:assign [--dry-run]`.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;

/**
 * Fills ttm_primary_category for posts that don't have it yet.
 */
class PrimaryCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --dry-run, --from-yoast, --term-map=<path>.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		$dry_run    = ! empty( $assoc['dry-run'] );
		$from_yoast = ! empty( $assoc['from-yoast'] );
		$batch      = (int) Config::get( 'cli.batch', 200 );
		$rows       = [];
		$paged      = 1;
		$used       = 0;
		$skipped    = 0;

		$term_map = null;
		if ( isset( $assoc['term-map'] ) ) {
			$term_map = $this->load_term_map( (string) $assoc['term-map'] );
			if ( null === $term_map ) {
				return [
					'ok'       => false,
					'rows'     => [],
					'messages' => [ sprintf( 'primary:assign --term-map: could not read/parse "%s".', $assoc['term-map'] ) ],
				];
			}
		}

		do {
			$query = new \WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => 'any',
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
				]
			);

			foreach ( $query->posts as $post_id ) {
				// A stored primary the post no longer carries (e.g. the importer's
				// insert-then-set-terms order left a stale "Uncategorized") is treated as
				// missing, same as PrimaryCategory::id() (R1-01).
				$categories = wp_get_post_categories( (int) $post_id );
				$existing   = (int) get_post_meta( $post_id, 'ttm_primary_category', true );
				if ( $existing && in_array( $existing, $categories, true ) ) {
					continue;
				}

				if ( $from_yoast ) {
					// SPEC §6.7: use the Yoast primary category only when the post actually
					// carries that category -- a stale/renamed Yoast value is skipped, not
					// forced, and falls to a plain `primary:assign` pass instead.
					$raw_yoast_id = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
					$yoast_id     = null === $term_map
						? $raw_yoast_id
						: $this->translate_yoast_id( $raw_yoast_id, $term_map );

					if ( ! $yoast_id || ! in_array( $yoast_id, $categories, true ) ) {
						++$skipped;
						continue;
					}

					if ( ! $dry_run ) {
						update_post_meta( $post_id, 'ttm_primary_category', $yoast_id );
					}

					++$used;
					$rows[] = [
						'post_id'  => (int) $post_id,
						'category' => $yoast_id,
					];
					continue;
				}//end if

				$id = PrimaryCategory::id( (int) $post_id );
				if ( ! $id ) {
					continue;
				}

				if ( ! $dry_run ) {
					update_post_meta( $post_id, 'ttm_primary_category', $id );
				}

				$rows[] = [
					'post_id'  => (int) $post_id,
					'category' => $id,
				];
			}//end foreach

			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		if ( $from_yoast ) {
			return [
				'ok'       => true,
				'rows'     => $rows,
				'messages' => [
					sprintf(
						'%s %d post(s) from Yoast, skipped %d.',
						$dry_run ? 'Would use' : 'Used',
						$used,
						$skipped
					),
				],
			];
		}

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [
				$dry_run
					? sprintf( 'Would assign %d post(s).', count( $rows ) )
					: sprintf( 'Assigned %d post(s).', count( $rows ) ),
			],
		];
	}

	/**
	 * `--term-map=<path>`: the WXR's own source-term-id -> slug map (R2-03, SPEC §6.7, §9 Q2),
	 * written by `scripts/live/term-map.mjs`/`import.sh` as `{"<old id>": "<slug>"}`. `null` on
	 * any read/parse failure (missing file, invalid JSON, or not a JSON object) -- the caller
	 * turns that into an `ok: false` error, never a partial/best-effort run.
	 *
	 * @param string $path Path to the term-map JSON file.
	 * @return array<string, string>|null
	 */
	private function load_term_map( string $path ): ?array {
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local CLI-only migration file, never a remote URL.
		if ( false === $contents ) {
			return null;
		}

		$decoded = json_decode( $contents, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$map = [];
		foreach ( $decoded as $key => $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$map[ (string) $key ] = $value;
			}
		}

		return $map;
	}

	/**
	 * Translate a Yoast primary-category meta value -- a term id on the *source* site -- to the
	 * current term id on *this* site, via the term map's slug and `get_term_by()`. `0` when the
	 * source id isn't in the map, the mapped slug no longer exists as a category, or the raw
	 * value was already `0`/absent -- same as "no Yoast primary" to the caller.
	 *
	 * @param int                   $raw_yoast_id The raw `_yoast_wpseo_primary_category` meta value.
	 * @param array<string, string> $term_map     Source term id (string) -> slug.
	 * @return int
	 */
	private function translate_yoast_id( int $raw_yoast_id, array $term_map ): int {
		if ( ! $raw_yoast_id || ! isset( $term_map[ (string) $raw_yoast_id ] ) ) {
			return 0;
		}

		$term = get_term_by( 'slug', $term_map[ (string) $raw_yoast_id ], 'category' );

		return $term instanceof \WP_Term ? (int) $term->term_id : 0;
	}
}
