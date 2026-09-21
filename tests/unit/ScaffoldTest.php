<?php
/**
 * Smoke tests for the repository scaffold. Replace/extend as real code lands.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

class ScaffoldTest extends TestCase {
	public function test_plugin_bootstrap_declares_api_version(): void {
		$src = (string) file_get_contents( TTM_CORE_DIR . 'ttm-core.php' );
		$this->assertStringContainsString( "define( 'TTM_CORE_API', 1 )", $src );
		$this->assertMatchesRegularExpression( '/Text Domain:\s+ttm-core/', $src );
	}

	public function test_theme_style_header_and_theme_json_are_present(): void {
		$style = (string) file_get_contents( TTM_THEME_DIR . 'style.css' );
		$this->assertMatchesRegularExpression( '/Text Domain:\s+ttm-theme/', $style );
		$json = json_decode( (string) file_get_contents( TTM_THEME_DIR . 'theme.json' ), true );
		$this->assertIsArray( $json );
		$this->assertSame( 3, $json['version'] );
	}

	/**
	 * R1-15: the wp_safe_remote_* call site for the newsletter's custom-url provider is
	 * Newsletter/Provider/CustomUrl.php, not Newsletter/Handler.php (Handler only decides
	 * whether to forward -- see scripts/forbidden-patterns.sh rule 16's own allow-list).
	 */
	public function test_claude_md_names_custom_url_as_remote_post_site(): void {
		$claude_md = (string) file_get_contents( dirname( TTM_CORE_DIR, 2 ) . '/CLAUDE.md' );

		$this->assertStringContainsString( 'Newsletter/Provider/CustomUrl.php', $claude_md );
		$this->assertStringNotContainsString( 'Newsletter/Handler.php', $claude_md );
	}

	/**
	 * Every `plugins/ttm-core/src/<Dir>/<Class>.php` must have its class name spelled out on
	 * CLAUDE.md's `## Module map` line for `<Dir>/` (top-level files -- `Config.php`,
	 * `Plugin.php` -- just need to appear anywhere in the module-map block, since they aren't
	 * grouped under a `<Dir>/` marker at all).
	 */
	public function test_claude_md_module_map_names_every_src_class(): void {
		$claude_md = (string) file_get_contents( dirname( TTM_CORE_DIR, 2 ) . '/CLAUDE.md' );
		$block     = $this->module_map_block( $claude_md );
		$src       = rtrim( TTM_CORE_DIR, '/' ) . '/src';

		$missing    = [];
		$root_files = glob( $src . '/*.php' );

		foreach ( false === $root_files ? [] : $root_files as $file ) {
			$class = basename( $file, '.php' );
			if ( false === strpos( $block, $class ) ) {
				$missing[] = "root/{$class}";
			}
		}

		$sub_dirs = glob( $src . '/*', GLOB_ONLYDIR );

		foreach ( false === $sub_dirs ? [] : $sub_dirs as $dir_path ) {
			$dir       = basename( $dir_path );
			$dir_lines = array_filter(
				explode( "\n", $block ),
				static fn ( string $line ): bool => false !== strpos( $line, "{$dir}/" )
			);

			$dir_files = glob( $dir_path . '/*.php' );

			foreach ( false === $dir_files ? [] : $dir_files as $file ) {
				$class = basename( $file, '.php' );
				$found = false;
				foreach ( $dir_lines as $line ) {
					// `Cli/` elides its *Command subclasses behind a literal "*Command" --
					// only the base Command class and the other Cli classes are spelled out.
					if ( '*Command' !== $class && str_ends_with( $class, 'Command' ) && false !== strpos( $line, '*Command' ) ) {
						$found = true;
						break;
					}
					if ( false !== strpos( $line, $class ) ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$missing[] = "{$dir}/{$class}";
				}
			}
		}

		$this->assertSame( [], $missing, 'CLAUDE.md module map is missing: ' . implode( ', ', $missing ) );
	}

	/**
	 * The `## Module map` section of CLAUDE.md, up to the next `##` heading.
	 *
	 * @param string $claude_md Full file contents.
	 * @return string
	 */
	private function module_map_block( string $claude_md ): string {
		if ( ! preg_match( '/## Module map\n(.*?)\n## /s', $claude_md, $matches ) ) {
			$this->fail( 'CLAUDE.md has no ## Module map section' );
		}

		return $matches[1];
	}

	public function test_brain_monkey_is_wired(): void {
		\Brain\Monkey\Functions\when( 'esc_html' )->returnArg();
		$this->assertSame( 'ok', esc_html( 'ok' ) );
	}
}
