<?php
/**
 * `wp ttm seed [--reset] [--state=<normal|quiet|empty>] [--starter-only] [--no-demo-images]`.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Support\Clock;

/**
 * Refuses on production; otherwise runs Seeder::run($state).
 */
class SeedCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --reset, --starter-only, --state=<normal|quiet|empty>, --no-demo-images, --now=<Y-m-d H:i:s>.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		if ( ! self::allowed( $environment ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Refusing to seed: WP_ENVIRONMENT_TYPE is production.' ],
			];
		}

		$now_filter = null;

		// P2-02: `--now` pins Clock::now() for the run so a demo build is byte-identical when
		// re-run on a different day (SPEC §6.4 "Demo dates"); the filter is removed in `finally`
		// so it never leaks into anything after this single command invocation.
		if ( isset( $assoc['now'] ) ) {
			$pinned = Clock::at( (string) $assoc['now'] );
			if ( ! $pinned ) {
				return [
					'ok'       => false,
					'rows'     => [],
					'messages' => [ 'invalid --now' ],
				];
			}

			$now_filter = static function () use ( $pinned ) {
				return $pinned;
			};
			add_filter( 'ttm_now', $now_filter );
		}

		try {
			// P1-02: --no-demo-images falls back to the rule 45 placeholder even when a fixture
			// names a real demo photograph (e.g. for a fast/offline seed).
			$seeder = new Seeder( empty( $assoc['no-demo-images'] ) );

			if ( ! empty( $assoc['reset'] ) ) {
				$seeder->reset();
			}

			// SPEC §6.6 step 1, §6.7: `--starter-only` runs the theme's starter content alone
			// (categories, Series/Writing/Newsletter/About pages, Sections navigation) and stops --
			// no posts, series, books, verse, or newsletter settings.
			if ( ! empty( $assoc['starter-only'] ) ) {
				$summary = $seeder->run_starter();

				return [
					'ok'       => true,
					'rows'     => [ $summary ],
					'messages' => [ 'Seeded starter content only.' ],
				];
			}

			$state   = (string) ( $assoc['state'] ?? 'normal' );
			$summary = $seeder->run( $state );

			return [
				'ok'       => true,
				'rows'     => [ $summary ],
				'messages' => [ sprintf( 'Seeded state "%s".', $state ) ],
			];
		} finally {
			if ( null !== $now_filter ) {
				remove_filter( 'ttm_now', $now_filter );
			}
		}//end try
	}

	/**
	 * Whether seeding is allowed for a given WP_ENVIRONMENT_TYPE value.
	 *
	 * @param string $environment_type wp_get_environment_type() value.
	 * @return bool
	 */
	public static function allowed( string $environment_type ): bool {
		return Seeder::may_wipe( $environment_type );
	}
}
