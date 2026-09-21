<?php
/**
 * `wp ttm verse fetch|inspect|log` (SPEC §6.7).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Support\Clock;
use TTM\Core\Verse\Fetcher;

/**
 * Dispatches on the first positional arg: fetch [--force], inspect [--raw], log.
 */
class VerseCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  `[fetch|inspect|log]`.
	 * @param array<string, mixed> $assoc `--force`, `--raw`.
	 */
	public function run( array $args, array $assoc ): array {
		$sub = $args[0] ?? '';

		switch ( $sub ) {
			case 'fetch':
				return $this->fetch( ! empty( $assoc['force'] ) );
			case 'inspect':
				return $this->inspect( ! empty( $assoc['raw'] ) );
			case 'log':
				return $this->log();
			default:
				return [
					'ok'       => false,
					'rows'     => [],
					'messages' => [ 'Specify a subcommand: fetch, inspect, or log.' ],
				];
		}
	}

	/**
	 * `wp ttm verse fetch [--force]`.
	 *
	 * @param bool $force Fetch even when today's verse is already stored.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function fetch( bool $force ): array {
		$result = Fetcher::fetch( $force );

		return [
			'ok'       => $result['ok'],
			'rows'     => [],
			'messages' => [ $result['message'] ],
		];
	}

	/**
	 * `wp ttm verse inspect [--raw]`: dump the raw page-1 payload, or `Fetcher::parse()` of it.
	 *
	 * @param bool $raw Print the raw payload instead of the parsed item.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function inspect( bool $raw ): array {
		$response = Fetcher::request();

		if ( is_wp_error( $response ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ $response->get_error_message() ],
			];
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $payload ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Invalid JSON payload.' ],
			];
		}

		if ( $raw ) {
			return [
				'ok'       => true,
				'rows'     => [],
				'messages' => [ (string) wp_json_encode( $payload, JSON_PRETTY_PRINT ) ],
			];
		}

		$parsed = Fetcher::parse( $payload, Clock::now() );

		return [
			'ok'       => null !== $parsed,
			'rows'     => [],
			'messages' => [ (string) wp_json_encode( $parsed, JSON_PRETTY_PRINT ) ],
		];
	}

	/**
	 * `wp ttm verse log`.
	 *
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function log(): array {
		$log = get_option( 'ttm_verse_log', [] );
		$log = is_array( $log ) ? $log : [];

		if ( empty( $log ) ) {
			return [
				'ok'       => true,
				'rows'     => [],
				'messages' => [ 'No log entries.' ],
			];
		}

		return [
			'ok'       => true,
			'rows'     => $log,
			'messages' => [],
		];
	}
}
