<?php
/**
 * `wp ttm seed [--reset] [--state=<normal|quiet|empty>]`.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

/**
 * Refuses on production; otherwise runs Seeder::run($state).
 */
class SeedCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --reset, --starter-only, --state=<normal|quiet|empty>.
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

		$seeder = new Seeder();

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
