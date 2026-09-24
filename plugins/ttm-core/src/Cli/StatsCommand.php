<?php
/**
 * `wp ttm stats:flush`.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Query\Stats;

/**
 * Deletes every `ttm_top_tags_*`/`ttm_category_stats_*`/`ttm_stats_*` transient (SPEC §6.7).
 */
class StatsCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc Associative args (unused).
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args, $assoc );

		$count = Stats::flush_all();

		return [
			'ok'       => true,
			'rows'     => [],
			'messages' => [ sprintf( 'Flushed %d stats transient(s).', $count ) ],
		];
	}
}
