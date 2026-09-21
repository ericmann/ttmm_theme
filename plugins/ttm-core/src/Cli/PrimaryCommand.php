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
	 * @param array<string, mixed> $assoc --dry-run.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		$dry_run = ! empty( $assoc['dry-run'] );
		$batch   = (int) Config::get( 'cli.batch', 200 );
		$rows    = [];
		$paged   = 1;

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
				$existing = get_post_meta( $post_id, 'ttm_primary_category', true );
				if ( $existing ) {
					continue;
				}

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
			}

			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

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
