<?php
/**
 * Registers `wp ttm …` commands. No-op unless running under WP-CLI.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

/**
 * Wraps each Command core for WP_CLI::add_command.
 */
class Loader {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'ttm seed', self::wrap( new SeedCommand() ) );
		\WP_CLI::add_command( 'ttm verse', self::wrap( new VerseCommand() ) );
		\WP_CLI::add_command( 'ttm recount', self::wrap( new RecountCommand() ) );
		\WP_CLI::add_command( 'ttm primary:assign', self::wrap( new PrimaryCommand() ) );
		\WP_CLI::add_command( 'ttm series:assign', self::wrap( new SeriesCommand() ) );
		\WP_CLI::add_command(
			'ttm series:rebuild',
			static function (): void {
				self::output( ( new SeriesCommand() )->rebuild() );
			}
		);
	}

	/**
	 * Wrap a Command core as a WP-CLI callable.
	 *
	 * @param Command $command Command core.
	 * @return callable
	 */
	private static function wrap( Command $command ): callable {
		return static function ( array $args, array $assoc ) use ( $command ): void {
			self::output( $command->run( $args, $assoc ) );
		};
	}

	/**
	 * Print a command result and exit 1 on failure.
	 *
	 * @param array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]} $result Command result.
	 */
	private static function output( array $result ): void {
		foreach ( $result['messages'] as $message ) {
			\WP_CLI::log( $message );
		}

		if ( ! empty( $result['rows'] ) ) {
			\WP_CLI\Utils\format_items( 'table', $result['rows'], array_keys( $result['rows'][0] ) );
		}

		if ( ! $result['ok'] ) {
			\WP_CLI::halt( 1 );
		}
	}
}
