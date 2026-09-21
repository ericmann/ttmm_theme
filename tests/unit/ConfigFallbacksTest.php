<?php
/**
 * SPEC §3.1 rule 24 (amended): every `Config::get( '<key>', <literal> )` fallback literal
 * equals `Config::defaults()[key]`, and every key referenced exists in defaults(). Scans
 * plugins/ttm-core/src/**\/*.php and plugins/ttm-core/blocks/*\/render.php for single-line
 * calls; array fallbacks (`[]`) are checked for key existence only.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

use TTM\Core\Config;

class ConfigFallbacksTest extends TestCase {

	/**
	 * Recursively list `.php` files under `$dir`.
	 *
	 * @param string $dir Directory to scan.
	 * @return string[]
	 */
	private static function php_files( string $dir ): array {
		$files = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Every `Config::get( '<key>', <literal> )` call found in the codebase, one line at a
	 * time, as `[file, line, key, literal]`.
	 *
	 * @return array<int, array{0: string, 1: int, 2: string, 3: string}>
	 */
	private static function config_get_calls(): array {
		$root = dirname( __DIR__, 2 ) . '/plugins/ttm-core';

		$files = self::php_files( $root . '/src' );

		foreach ( glob( $root . '/blocks/*/render.php' ) as $render_file ) {
			$files[] = $render_file;
		}

		$calls = [];

		foreach ( $files as $file ) {
			$lines = file( $file );
			foreach ( $lines as $i => $line ) {
				if ( 1 !== preg_match(
					"/Config::get\\(\\s*'([a-zA-Z0-9_.]+)'\\s*,\\s*(.+?)\\s*\\)/",
					$line,
					$matches
				) ) {
					continue;
				}

				$calls[] = [ $file, $i + 1, $matches[1], $matches[2] ];
			}
		}

		return $calls;
	}

	/**
	 * Decode a fallback literal (string/int/float/bool) to a PHP value, or null when it is
	 * not a literal this test understands (e.g. a variable or empty array `[]`, which is
	 * checked for key existence only).
	 *
	 * @param string $literal Raw fallback source text.
	 * @return array{0: bool, 1: mixed} [is a decodable literal, decoded value].
	 */
	private static function decode_literal( string $literal ): array {
		if ( preg_match( "/^'((?:[^'\\\\]|\\\\.)*)'$/", $literal, $m ) ) {
			return [ true, stripslashes( $m[1] ) ];
		}

		if ( 'true' === $literal ) {
			return [ true, true ];
		}

		if ( 'false' === $literal ) {
			return [ true, false ];
		}

		if ( preg_match( '/^-?\d+\.\d+$/', $literal ) ) {
			return [ true, (float) $literal ];
		}

		if ( preg_match( '/^-?\d+$/', $literal ) ) {
			return [ true, (int) $literal ];
		}

		return [ false, null ];
	}

	public function test_every_config_get_key_exists_in_defaults(): void {
		$defaults = Config::defaults();
		$calls    = self::config_get_calls();

		$this->assertNotEmpty( $calls, 'Expected to find Config::get() calls under plugins/ttm-core.' );

		foreach ( $calls as [ $file, $line, $key ] ) {
			$this->assertArrayHasKey(
				$key,
				$defaults,
				"Config::get( '{$key}', … ) at {$file}:{$line} has no Config::defaults() entry"
			);
		}
	}

	public function test_every_config_get_fallback_matches_defaults(): void {
		$defaults = Config::defaults();

		foreach ( self::config_get_calls() as [ $file, $line, $key, $literal ] ) {
			if ( '[]' === $literal ) {
				continue; // Array fallbacks: key existence only (checked above).
			}

			[ $is_literal, $value ] = self::decode_literal( $literal );

			if ( ! $is_literal ) {
				continue; // Not a scalar literal (e.g. a variable) — nothing to compare.
			}

			$this->assertArrayHasKey( $key, $defaults, "Config::get( '{$key}', … ) at {$file}:{$line} has no Config::defaults() entry" );
			$default_repr = is_scalar( $defaults[ $key ] ) ? (string) $defaults[ $key ] : gettype( $defaults[ $key ] );
			$this->assertTrue(
				$value == $defaults[ $key ], // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- rule 24: fallback literal `==` its default (int/string cast differences allowed).
				"Config::get( '{$key}', {$literal} ) at {$file}:{$line} does not match Config::defaults()['{$key}'] = {$default_repr}"
			);
		}
	}
}
