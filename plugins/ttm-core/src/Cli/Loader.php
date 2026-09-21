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
		\WP_CLI::add_command( 'ttm convert:export', self::wrap( new ConvertCommand() ) );
		\WP_CLI::add_command(
			'ttm convert:import',
			static function ( array $args, array $assoc ): void {
				self::output( ( new ConvertCommand() )->import( $args, $assoc ) );
			}
		);
		\WP_CLI::add_command(
			'ttm convert:revert',
			static function ( array $args, array $assoc ): void {
				self::output( ( new ConvertCommand() )->revert( $args, $assoc ) );
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
			// Rows don't always share the same keys (e.g. convert:import's per-post block-type
			// columns vary by post) - format_items() requires one fixed field list, so use the
			// union of every row's keys and backfill the rest, rather than just the first row's.
			$fields = [];
			foreach ( $result['rows'] as $row ) {
				$fields = array_unique( array_merge( $fields, array_keys( $row ) ) );
			}

			$rows = array_map(
				static function ( array $row ) use ( $fields ): array {
					foreach ( $fields as $field ) {
						$row[ $field ] = $row[ $field ] ?? '';
					}
					return $row;
				},
				$result['rows']
			);

			\WP_CLI\Utils\format_items( 'table', $rows, $fields );
		}

		if ( ! $result['ok'] ) {
			\WP_CLI::halt( 1 );
		}
	}
}
