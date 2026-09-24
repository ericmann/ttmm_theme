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
	 * @param array<string, mixed> $assoc --dry-run, --from-yoast.
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
					$yoast_id = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
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
}
