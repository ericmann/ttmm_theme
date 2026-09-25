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

		// wp ttm seed [--reset] [--state=<normal|quiet|empty>] [--starter-only] [--no-demo-images].
		\WP_CLI::add_command( 'ttm seed', self::wrap( new SeedCommand() ) );
		\WP_CLI::add_command( 'ttm stats:flush', self::wrap( new StatsCommand() ) );
		\WP_CLI::add_command( 'ttm demo:options', self::wrap( new DemoCommand() ) );
		\WP_CLI::add_command(
			'ttm demo:verify',
			static function ( array $args, array $assoc ): void {
				self::output_lines( ( new DemoCommand() )->verify( $args, $assoc ) );
			}
		);
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
		\WP_CLI::add_command(
			'ttm audit',
			static function ( array $args, array $assoc ): void {
				self::output_audit( ( new AuditCommand() )->run( $args, $assoc ), $assoc );
			}
		);
		\WP_CLI::add_command( 'ttm migrate:politics', self::wrap( new MigrateCommand() ) );
		\WP_CLI::add_command(
			'ttm migrate:redirects',
			static function ( array $args, array $assoc ): void {
				self::output_lines( ( new MigrateCommand() )->redirects( $args, $assoc ) );
			}
		);
		\WP_CLI::add_command(
			'ttm migrate:close-comments',
			static function ( array $args, array $assoc ): void {
				self::output( ( new MigrateCommand() )->close_comments( $args, $assoc ) );
			}
		);
		\WP_CLI::add_command( 'ttm migrate:syndication', self::wrap( new SyndicationCommand() ) );
		\WP_CLI::add_command(
			'ttm migrate:excerpts',
			static function ( array $args, array $assoc ): void {
				self::output( ( new MigrateCommand() )->excerpts( $args, $assoc ) );
			}
		);
		\WP_CLI::add_command(
			'ttm migrate:images',
			static function ( array $args, array $assoc ): void {
				self::output( ( new MigrateCommand() )->images( $args, $assoc ) );
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
		}//end if

		if ( ! $result['ok'] ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Print only a result's messages (no rows table), for commands whose messages are already
	 * the literal output the operator wants (e.g. `migrate:redirects`' nginx/JSON lines).
	 *
	 * @param array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]} $result Command result.
	 */
	private static function output_lines( array $result ): void {
		foreach ( $result['messages'] as $message ) {
			\WP_CLI::log( $message );
		}

		if ( ! $result['ok'] ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * `ttm audit`'s own formatter: its rows carry a `flags` array and a `detail` array, which
	 * `--format=table|csv` render as a joined string / JSON string, and `--format=json` keeps as-is.
	 * `--summary` rows are `{flag, count}` and always render as a `| flag | count |` markdown
	 * table (SPEC §6.7), regardless of `--format`.
	 *
	 * @param array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]} $result Command result.
	 * @param array<string, mixed>                                                        $assoc  --format=table|csv|json, --summary.
	 */
	private static function output_audit( array $result, array $assoc ): void {
		foreach ( $result['messages'] as $message ) {
			\WP_CLI::log( $message );
		}

		if ( ! empty( $assoc['summary'] ) ) {
			\WP_CLI::log( '| flag | count |' );
			\WP_CLI::log( '| --- | --- |' );
			foreach ( $result['rows'] as $row ) {
				\WP_CLI::log( sprintf( '| %s | %d |', $row['flag'], $row['count'] ) );
			}

			if ( ! $result['ok'] ) {
				\WP_CLI::halt( 1 );
			}
			return;
		}

		$format = (string) ( $assoc['format'] ?? 'table' );

		if ( 'json' === $format ) {
			\WP_CLI::log( (string) wp_json_encode( $result['rows'] ) );
		} elseif ( ! empty( $result['rows'] ) ) {
			$rows = array_map(
				static function ( array $row ): array {
					return [
						'id'     => $row['id'],
						'slug'   => $row['slug'],
						'flags'  => implode( ',', $row['flags'] ),
						'detail' => (string) wp_json_encode( $row['detail'] ),
					];
				},
				$result['rows']
			);

			\WP_CLI\Utils\format_items( 'csv' === $format ? 'csv' : 'table', $rows, [ 'id', 'slug', 'flags', 'detail' ] );
		}

		if ( ! $result['ok'] ) {
			\WP_CLI::halt( 1 );
		}
	}
}
