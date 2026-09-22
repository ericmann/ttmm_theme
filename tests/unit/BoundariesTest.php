<?php
/**
 * Enforces the SPEC §4.2 module dependency direction: every `use TTM\Core\…` line in
 * `plugins/ttm-core/src/<dir>/*.php` must refer to the same directory or an earlier row of
 * the §4.2 table (the arrow points down the table), with the explicit exception that
 * `Cache/`, `Verse/` and `Newsletter/` never import `Blocks/` even though some of them are a
 * later row.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class BoundariesTest extends TestCase {

	/**
	 * SPEC §4 table order, top to bottom. A directory may import its own directory or any
	 * directory earlier in this list. Top-level files (`Config.php`, `Plugin.php`) are grouped
	 * with `.` (the src root) since they have no directory of their own. `Cli/` is exempt (may
	 * import anything) and deliberately absent from this list.
	 *
	 * @var string[]
	 */
	private const ROW_ORDER = [
		'.',
		'Support',
		'Taxonomy',
		'Meta',
		'Query',
		'Fiction',
		'Cache',
		'Verse',
		'Newsletter',
		'Bindings',
		'Blocks',
		'Editor',
		'Templates',
		'Nav',
		'Rest',
		'Compat',
		'Admin',
	];

	/**
	 * SPEC §4's "May import" column, exactly: the directories each row may reference (its own
	 * directory is always implicitly allowed and not repeated here). `Blocks/` and the final
	 * tier (`Editor`, `Templates`, `Nav`, `Rest`, `Compat`, `Admin`) may import everything
	 * above them, spelled out rather than left to rank order alone.
	 *
	 * @var array<string, string[]>
	 */
	private const MAY_IMPORT = [
		'.'          => [],
		'Support'    => [ '.' ],
		'Taxonomy'   => [ 'Support', '.' ],
		'Meta'       => [ 'Support', '.', 'Taxonomy' ],
		'Query'      => [ 'Support', '.', 'Meta', 'Taxonomy' ],
		'Fiction'    => [ 'Query', 'Taxonomy', 'Support', '.' ],
		'Cache'      => [ '.', 'Support' ],
		'Verse'      => [ 'Support', '.', 'Cache' ],
		'Newsletter' => [ '.', 'Support' ],
		'Bindings'   => [ 'Query', 'Support', 'Meta', '.', 'Verse' ],
		'Blocks'     => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings' ],
		'Editor'     => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings', 'Blocks' ],
		'Templates'  => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings', 'Blocks' ],
		'Nav'        => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings', 'Blocks' ],
		'Rest'       => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings', 'Blocks' ],
		'Compat'     => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings', 'Blocks' ],
		'Admin'      => [ '.', 'Support', 'Taxonomy', 'Meta', 'Query', 'Fiction', 'Cache', 'Verse', 'Newsletter', 'Bindings', 'Blocks' ],
	];

	/**
	 * Directories that never import `Blocks/`, regardless of row order (SPEC §4.2).
	 *
	 * @var string[]
	 */
	private const NEVER_IMPORTS_BLOCKS = [ 'Cache', 'Verse', 'Newsletter' ];

	/**
	 * Upward imports the stricter `MAY_IMPORT` map would otherwise flag, kept as-is because
	 * refactoring `Cache/Verse/Newsletter` internals is out of scope this flight (non-goal).
	 * `[source directory] => [target directory => reason]`.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const KNOWN_EXCEPTIONS = [
		'Cache'      => [
			'Meta'  => 'Cache\\Headers reads PrimaryCategory for the category-scoped cache key (phase 1 shipped behaviour).',
			'Query' => 'Cache\\Purge reads Query\\SeriesIndex to invalidate series pages (phase 1 shipped behaviour).',
		],
		'Verse'      => [
			'Admin' => 'Verse\\Admin extends the shared Admin\\Page settings screen (phase 1 shipped behaviour).',
		],
		'Newsletter' => [
			'Admin' => 'Newsletter\\Settings extends the shared Admin\\Page settings screen (phase 1 shipped behaviour).',
		],
		'Fiction'    => [
			'Admin' => 'Fiction\\Books extends the shared Admin\\Page settings screen (phase 1 shipped behaviour).',
		],
	];

	/**
	 * The only plugin classes theme PHP may reference directly (SPEC §3.1 rule 1): `Config`
	 * itself, and the `Compat\Theme` notice class (the plugin-side half of the API-version
	 * contract). Everything else must be reached through `ttm/*` blocks or bindings.
	 *
	 * @var string[]
	 */
	private const THEME_ALLOWED_PLUGIN_REFS = [ 'Config', 'Compat\Theme' ];

	public function test_theme_php_references_only_config_from_the_plugin(): void {
		$theme      = rtrim( TTM_THEME_DIR, '/' );
		$violations = [];

		foreach ( $this->php_files_recursive( $theme ) as $file ) {
			$contents = (string) file_get_contents( $file );

			if ( ! preg_match_all( '/\\\\?TTM\\\\Core\\\\([A-Za-z0-9_]+(?:\\\\[A-Za-z0-9_]+)*)/', $contents, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $reference ) {
				$allowed = false;

				foreach ( self::THEME_ALLOWED_PLUGIN_REFS as $ref ) {
					if ( $reference === $ref || 0 === strpos( $reference, $ref . '\\' ) || 0 === strpos( $reference, $ref . '::' ) ) {
						$allowed = true;
						break;
					}
				}

				if ( ! $allowed ) {
					$violations[] = sprintf( '%s references TTM\\Core\\%s', $file, $reference );
				}
			}
		}

		$this->assertSame( [], $violations, "Theme PHP referencing plugin symbols other than Config/Compat\\Theme:\n" . implode( "\n", $violations ) );
	}

	/**
	 * Every `.php` file anywhere under $dir.
	 *
	 * @param string $dir Absolute directory path.
	 * @return string[]
	 */
	private function php_files_recursive( string $dir ): array {
		$files    = [];
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $iterator as $file ) {
			if ( 'php' === strtolower( $file->getExtension() ) ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Whether `$source` importing `$target` is a documented, out-of-scope exception (§4.2).
	 *
	 * @param string $source Importing directory.
	 * @param string $target Imported directory.
	 * @return bool
	 */
	private function is_known_exception( string $source, string $target ): bool {
		return isset( self::KNOWN_EXCEPTIONS[ $source ][ $target ] );
	}

	/**
	 * SPEC §4's "May import" column, enforced exactly (not just "any earlier row" -- a
	 * directory may only import the specific directories its row lists, plus itself and any
	 * `KNOWN_EXCEPTIONS`). A synthetic violation fixture (a `Support/` file that `use`s
	 * `Query\Lead`, which is not in `Support`'s allowed list) fails; the real tree passes.
	 */
	public function test_may_import_column_is_enforced_per_directory(): void {
		$src = rtrim( TTM_CORE_DIR, '/' ) . '/src';

		$real_tree = [];
		foreach ( $this->php_files( $src ) as $file ) {
			$dir               = $this->top_level_dir( $src, $file );
			$real_tree[ $file ] = [ $dir, $this->use_targets( $file ) ];
		}

		$this->assertSame( [], $this->may_import_violations( $real_tree ), 'The real tree violates the SPEC §4 May-import column.' );

		// Synthetic fixture: Support/ may only import Config, so a Query\Lead use is a violation.
		$fixture = [
			'fixture/Support/Broken.php' => [ 'Support', [ 'Query' ] ],
		];
		$this->assertNotSame( [], $this->may_import_violations( $fixture ), 'The synthetic Support/ -> Query/ fixture should have been flagged.' );
	}

	/**
	 * SPEC §4 May-import-column violations across `$tree`.
	 *
	 * @param array<string, array{0: string, 1: string[]}> $tree file => [ directory, imported directories ].
	 * @return string[]
	 */
	private function may_import_violations( array $tree ): array {
		$violations = [];

		foreach ( $tree as $file => [ $dir, $used_dirs ] ) {
			if ( 'Cli' === $dir || ! array_key_exists( $dir, self::MAY_IMPORT ) ) {
				continue; // Cli/ is exempt (may import anything); unmapped dirs are out of scope.
			}

			$allowed = self::MAY_IMPORT[ $dir ];

			foreach ( $used_dirs as $used_dir ) {
				if ( $used_dir === $dir || in_array( $used_dir, $allowed, true ) || $this->is_known_exception( $dir, $used_dir ) ) {
					continue;
				}

				if ( ! array_key_exists( $used_dir, self::MAY_IMPORT ) ) {
					continue; // Unmapped target (e.g. Cli/): not part of the §4 table.
				}

				$violations[] = sprintf( '%s (%s/) imports %s/, which is not in its May-import list', $file, $dir, $used_dir );
			}
		}

		return $violations;
	}

	/**
	 * The `use` scan above misses an upward dependency spelled out inline (`\TTM\Core\Blocks\
	 * Helpers::wrapper(...)`) instead of imported at the top of the file -- this walks every
	 * `plugins/ttm-core/src/**\/*.php` file's body (docblocks, `//` comments and `use` lines
	 * stripped first, since a reference *inside* one of those isn't a dependency) for a
	 * leading-backslash `\TTM\Core\...` reference and applies the same §4.2 row-order rule.
	 */
	public function test_inline_fully_qualified_references_obey_the_spec_table(): void {
		$src        = rtrim( TTM_CORE_DIR, '/' ) . '/src';
		$violations = [];

		foreach ( $this->php_files( $src ) as $file ) {
			$dir = $this->top_level_dir( $src, $file );

			if ( ! in_array( $dir, self::ROW_ORDER, true ) ) {
				continue;
			}

			$dir_rank = array_search( $dir, self::ROW_ORDER, true );

			foreach ( $this->inline_use_targets( $file ) as $used_dir ) {
				if ( in_array( $dir, self::NEVER_IMPORTS_BLOCKS, true ) && 'Blocks' === $used_dir ) {
					$violations[] = sprintf( '%s (%s/) must never reference Blocks/ inline (used %s)', $file, $dir, $used_dir );
					continue;
				}

				if ( ! in_array( $used_dir, self::ROW_ORDER, true ) ) {
					continue;
				}

				if ( $this->is_known_exception( $dir, $used_dir ) ) {
					continue;
				}

				$used_rank = array_search( $used_dir, self::ROW_ORDER, true );

				if ( $used_rank > $dir_rank ) {
					$violations[] = sprintf(
						'%s (%s/, row %d) references %s/ (row %d) inline — upward reference',
						$file,
						$dir,
						$dir_rank,
						$used_dir,
						$used_rank
					);
				}
			}
		}

		$this->assertSame( [], $violations, "Upward inline SPEC §4.2 references found:\n" . implode( "\n", $violations ) );
	}

	/**
	 * Every distinct `TTM\Core\<Dir>\…` directory referenced inline as `\TTM\Core\...` in the
	 * file's body, with docblocks, `//` comments and `use` lines stripped first.
	 *
	 * @param string $file Absolute file path.
	 * @return string[]
	 */
	private function inline_use_targets( string $file ): array {
		$contents = (string) file_get_contents( $file );
		$contents = (string) preg_replace( '#/\*.*?\*/#s', '', $contents );

		$lines = [];
		foreach ( explode( "\n", $contents ) as $line ) {
			if ( 0 === strpos( ltrim( $line ), 'use ' ) ) {
				continue;
			}

			$comment_at = strpos( $line, '//' );
			if ( false !== $comment_at ) {
				$line = substr( $line, 0, $comment_at );
			}

			$lines[] = $line;
		}

		$clean = implode( "\n", $lines );
		$targets = [];

		if ( ! preg_match_all( '/\\\\TTM\\\\Core\\\\([A-Za-z0-9_\\\\]+)/', $clean, $matches ) ) {
			return $targets;
		}

		foreach ( $matches[1] as $used ) {
			$segments  = explode( '\\', trim( $used, '\\' ) );
			$targets[] = count( $segments ) > 1 ? $segments[0] : '.';
		}

		return array_values( array_unique( $targets ) );
	}

	/**
	 * Rule 32: `Bindings\Values::pagination_label()` is the single source of the two
	 * translatable pagination strings -- a `Query\Archive::format_label()`-style duplicate
	 * (fixed in R2-03) would fail this.
	 */
	public function test_pagination_label_strings_have_one_source(): void {
		$src = rtrim( TTM_CORE_DIR, '/' ) . '/src';

		foreach ( [ 'Older (%s) →', '← Newer (%s)' ] as $needle ) {
			$count = 0;

			foreach ( $this->php_files( $src ) as $file ) {
				$count += substr_count( (string) file_get_contents( $file ), $needle );
			}

			$this->assertSame( 1, $count, "'{$needle}' should occur exactly once under plugins/ttm-core/src" );
		}
	}

	public function test_no_directory_imports_a_later_row_of_the_spec_table(): void {
		$src        = rtrim( TTM_CORE_DIR, '/' ) . '/src';
		$violations = [];

		foreach ( $this->php_files( $src ) as $file ) {
			$dir = $this->top_level_dir( $src, $file );

			if ( ! in_array( $dir, self::ROW_ORDER, true ) ) {
				continue; // Unmapped directory: not part of the §4.2 table.
			}

			$dir_rank = array_search( $dir, self::ROW_ORDER, true );

			foreach ( $this->use_targets( $file ) as $used_dir ) {
				if ( in_array( $dir, self::NEVER_IMPORTS_BLOCKS, true ) && 'Blocks' === $used_dir ) {
					$violations[] = sprintf( '%s (%s/) must never import Blocks/ (used %s)', $file, $dir, $used_dir );
					continue;
				}

				if ( ! in_array( $used_dir, self::ROW_ORDER, true ) ) {
					continue; // Unmapped target directory: not part of the §4.2 table.
				}

				if ( $this->is_known_exception( $dir, $used_dir ) ) {
					continue;
				}

				$used_rank = array_search( $used_dir, self::ROW_ORDER, true );

				if ( $used_rank > $dir_rank ) {
					$violations[] = sprintf(
						'%s (%s/, row %d) imports %s/ (row %d) — upward import',
						$file,
						$dir,
						$dir_rank,
						$used_dir,
						$used_rank
					);
				}
			}
		}

		$this->assertSame( [], $violations, "Upward SPEC §4.2 imports found:\n" . implode( "\n", $violations ) );
	}

	/**
	 * Every `.php` file directly under $src or one level below it.
	 *
	 * @param string $src Absolute path to `plugins/ttm-core/src`.
	 * @return string[]
	 */
	private function php_files( string $src ): array {
		$top_level = glob( $src . '/*.php' );
		$files     = false === $top_level ? [] : $top_level;

		$subdirs = glob( $src . '/*', GLOB_ONLYDIR );

		foreach ( false === $subdirs ? [] : $subdirs as $subdir ) {
			$nested = glob( $subdir . '/*.php' );
			$files  = array_merge( $files, false === $nested ? [] : $nested );
		}

		return $files;
	}

	/**
	 * The file's directory relative to $src, or '.' for a file directly under $src.
	 *
	 * @param string $src  Absolute path to `plugins/ttm-core/src`.
	 * @param string $file Absolute file path.
	 * @return string
	 */
	private function top_level_dir( string $src, string $file ): string {
		$relative = ltrim( substr( $file, strlen( $src ) ), '/' );
		$parts    = explode( '/', $relative );

		return count( $parts ) > 1 ? $parts[0] : '.';
	}

	/**
	 * Every distinct `TTM\Core\<Dir>\…` directory referenced by a `use` line in the file
	 * ('.' for a direct `TTM\Core\ClassName` with no further namespace segment).
	 *
	 * @param string $file Absolute file path.
	 * @return string[]
	 */
	private function use_targets( string $file ): array {
		$contents = (string) file_get_contents( $file );
		$targets  = [];

		if ( ! preg_match_all( '/^use\s+TTM\\\\Core\\\\([^;]+);/m', $contents, $matches ) ) {
			return $targets;
		}

		foreach ( $matches[1] as $used ) {
			$segments  = explode( '\\', trim( $used ) );
			$targets[] = count( $segments ) > 1 ? $segments[0] : '.';
		}

		return array_values( array_unique( $targets ) );
	}
}
