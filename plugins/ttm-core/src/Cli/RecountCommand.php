<?php
/**
 * `wp ttm recount [--all] [--post=<id>]`.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Support\Text;

/**
 * Recomputes ttm_word_count for one post or every post.
 */
class RecountCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --all, --post=<id>.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		if ( isset( $assoc['post'] ) ) {
			$post_id = absint( $assoc['post'] );
			$post    = get_post( $post_id );
			if ( ! $post ) {
				return [
					'ok'       => false,
					'rows'     => [],
					'messages' => [ "Post {$post_id} not found." ],
				];
			}

			return $this->recount( [ $post_id ] );
		}

		if ( ! empty( $assoc['all'] ) ) {
			return $this->recount( $this->all_post_ids() );
		}

		return [
			'ok'       => false,
			'rows'     => [],
			'messages' => [ 'Specify --all or --post=<id>.' ],
		];
	}

	/**
	 * Recount a set of post ids.
	 *
	 * @param int[] $post_ids Post ids.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function recount( array $post_ids ): array {
		$rows = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$count = Text::word_count( $post->post_content );
			update_post_meta( $post_id, 'ttm_word_count', $count );
			$rows[] = [
				'post_id' => $post_id,
				'words'   => $count,
			];
		}

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ sprintf( 'Recounted %d post(s).', count( $rows ) ) ],
		];
	}

	/**
	 * Every `post` id, batched.
	 *
	 * @return int[]
	 */
	private function all_post_ids(): array {
		$batch = (int) Config::get( 'cli.batch', 200 );
		$ids   = [];
		$paged = 1;

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
			$ids   = array_merge( $ids, $query->posts );
			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return $ids;
	}
}
