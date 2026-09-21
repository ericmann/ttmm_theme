<?php
/**
 * Base class for testable CLI command cores.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

/**
 * Subclasses implement run() as a pure-ish, directly-testable core; Loader wraps it for WP-CLI.
 */
abstract class Command {

	/**
	 * Run the command.
	 *
	 * @param string[]             $args  Positional args.
	 * @param array<string, mixed> $assoc Associative (--flag) args.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	abstract public function run( array $args, array $assoc ): array;
}
