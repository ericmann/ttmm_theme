<?php
/**
 * `wp ttm migrate:syndication [--dry-run] [--post=<id>]` (SPEC Q-M4; spike P8-05).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\PostMeta;
use WP_Query;

/**
 * Populates `ttm_syndication` from Jetpack Social/Publicize's `_publicize_done_external` meta
 * where it exists. `get_post_meta()` already unpacks a serialized value safely, and this class
 * never parses raw meta itself (rule 15): a value that arrives as anything other than an array
 * (e.g. a raw string) is simply skipped rather than decoded by hand. See docs/spikes/P8-05.md for
 * which Jetpack meta keys were examined.
 */
class SyndicationCommand extends Command {

	/**
	 * Publicize service name => `ttm_syndication` network key.
	 *
	 * @var array<string, string>
	 */
	private const SERVICE_MAP = [
		'twitter'  => 'x',
		'x'        => 'x',
		'mastodon' => 'mastodon',
		'bluesky'  => 'bluesky',
	];

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --dry-run, --post=<id>.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		$dry_run  = ! empty( $assoc['dry-run'] );
		$post_ids = isset( $assoc['post'] ) ? [ (int) $assoc['post'] ] : $this->all_post_ids();

		$rows = [];
		foreach ( $post_ids as $post_id ) {
			$existing = get_post_meta( $post_id, 'ttm_syndication', true );
			if ( ! empty( $existing ) ) {
				continue;
			}

			$clean = PostMeta::sanitize_syndication( $this->candidate_urls( $post_id ) );
			if ( empty( $clean ) ) {
				continue;
			}

			$rows[] = array_merge( [ 'post_id' => $post_id ], $clean );

			if ( ! $dry_run ) {
				update_post_meta( $post_id, 'ttm_syndication', $clean );
			}
		}

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [
				$dry_run
					? sprintf( 'Would populate ttm_syndication on %d post(s).', count( $rows ) )
					: sprintf( 'Populated ttm_syndication on %d post(s).', count( $rows ) ),
			],
		];
	}

	/**
	 * Candidate {network: url} pairs read from `_publicize_done_external`
	 * (shape `{service: {id: url}}`), unmapped services and non-array shapes ignored.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>
	 */
	private function candidate_urls( int $post_id ): array {
		$value = get_post_meta( $post_id, '_publicize_done_external', true );
		if ( ! is_array( $value ) ) {
			return [];
		}

		$found = [];
		foreach ( $value as $service => $entries ) {
			$network = self::SERVICE_MAP[ strtolower( (string) $service ) ] ?? null;
			if ( null === $network || isset( $found[ $network ] ) || ! is_array( $entries ) ) {
				continue;
			}

			foreach ( $entries as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$found[ $network ] = $url;
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Batched post ids, any status.
	 *
	 * @return int[]
	 */
	private function all_post_ids(): array {
		$batch = (int) Config::get( 'cli.batch', 200 );
		$ids   = [];
		$paged = 1;

		do {
			$query = new WP_Query(
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
