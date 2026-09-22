<?php
/**
 * Discovers and registers every `ttm/` block from plugins/ttm-core/blocks/{name}/block.json.
 *
 * @package TTM\Core\Blocks
 */

declare( strict_types=1 );

namespace TTM\Core\Blocks;

/**
 * Registers blocks; when a block's built editor script is missing, swaps in the committed
 * `ttm-core-editor-fallback` handle instead of dropping `editorScript` outright (SPEC §6.7 --
 * a missing `editorScript` is exactly what produces "Your site doesn't include support for the
 * ttm/… block" in every editor context). Also adds the `ttm` block category.
 */
class Registrar {

	/**
	 * `ttm/*` block names that fell back to `ttm-core-editor-fallback` this request, because
	 * `build/blocks/{slug}/index.asset.php` didn't exist.
	 *
	 * @var string[]
	 */
	private static array $fallback_blocks = [];

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_blocks' ] );
		add_filter( 'block_type_metadata', [ self::class, 'drop_missing_editor_script' ] );
		add_filter( 'block_categories_all', [ self::class, 'add_category' ] );
		// Core only auto-enqueues registered blocks' editor scripts on `enqueue_block_editor_assets`
		// (default-filters.php) -- `wp_enqueue_registered_block_scripts_and_styles()` itself
		// still gates editor-script enqueuing behind `wp_should_load_block_editor_scripts_and_
		// styles()`, which is false on the Customizer screen, so even calling that function
		// there does nothing. The Customizer never loads `window.wp.blocks` (or any block's own
		// script) for a block theme with no block-based widget areas at all -- confirmed live
		// (SPEC §6.7 diagnosis) -- so every `ttm/*` block's own editor script handles are
		// enqueued directly here instead.
		add_action( 'customize_controls_enqueue_scripts', [ self::class, 'enqueue_customizer_block_scripts' ] );
	}

	/**
	 * Explicitly enqueue every registered `ttm/*` block's `editor_script_handles` in the
	 * Customizer, which never does this on its own (see the note in `register()`).
	 *
	 * Enqueuing the scripts alone isn't enough: each block's own `index.js` only calls
	 * `registerBlockType( metadata.name, { edit, save } )` -- no title/category/attributes/
	 * supports -- relying on WordPress's client-side `registerBlockType()` merging that with a
	 * full definition already bootstrapped into the JS registry from PHP. In the post editor and
	 * Site Editor, Gutenberg's own init code does that bootstrap automatically; core's Customizer
	 * widgets screen does the same thing itself (`WP_Customize_Widgets::enqueue_scripts()`,
	 * `wp.blocks.unstable__bootstrapServerSideBlockDefinitions()`) but only when
	 * `wp_use_widgets_block_editor()` is true, which it never is for this theme (no widget areas).
	 * So this block theme's Customizer must do that same bootstrap call itself, or the client call
	 * warns "must have a title" and silently drops the block -- `getBlockType()` never sees it.
	 */
	public static function enqueue_customizer_block_scripts(): void {
		if ( ! function_exists( 'get_block_editor_server_block_settings' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		$settings = get_block_editor_server_block_settings();
		$ttm_settings = [];

		foreach ( $registry->get_all_registered() as $name => $type ) {
			if ( 0 !== strpos( (string) $name, 'ttm/' ) ) {
				continue;
			}

			foreach ( $type->editor_script_handles as $handle ) {
				wp_enqueue_script( $handle );
			}

			if ( isset( $settings[ $name ] ) ) {
				$ttm_settings[ $name ] = $settings[ $name ];
			}
		}

		if ( empty( $ttm_settings ) ) {
			return;
		}

		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( $ttm_settings, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ');'
		);
	}

	/**
	 * `ttm/*` block names that fell back to `ttm-core-editor-fallback` this request
	 * (`Admin\BuildNotice` reads this to decide whether to show its notice).
	 *
	 * @return string[]
	 */
	public static function fallback_blocks(): array {
		return array_values( array_unique( self::$fallback_blocks ) );
	}

	/**
	 * Register every block found under blocks/*\/block.json.
	 */
	public static function register_blocks(): void {
		self::$fallback_blocks = [];

		$files = glob( TTM_CORE_DIR . 'blocks/*/block.json' );

		foreach ( false === $files ? [] : $files as $file ) {
			$metadata = json_decode( (string) file_get_contents( $file ), true );

			if ( ! empty( $metadata['name'] ) && \WP_Block_Type_Registry::get_instance()->is_registered( $metadata['name'] ) ) {
				continue;
			}

			register_block_type( dirname( $file ) );
		}

		self::register_fallback_script();
	}

	/**
	 * `block.json`'s own `"editorScript": "file:./index.js"` resolves relative to block.json's
	 * OWN directory -- `plugins/ttm-core/blocks/{slug}/`, the unbuilt ES-module SOURCE tree, never
	 * `build/blocks/{slug}/` -- so registering straight off that metadata always serves the raw
	 * `import …` source file to every editor context (confirmed live: "Cannot use import statement
	 * outside a module" for every `ttm/*` block, in both the Site Editor and the Customizer, even
	 * with `build/` present). Fixed here by swapping `editorScript` for an explicitly registered
	 * script handle pointing at the actual built file/deps/version from `index.asset.php`, instead
	 * of leaving WordPress to resolve the `file:` path itself.
	 *
	 * When the build output is missing altogether, fall back to `ttm-core-editor-fallback` instead
	 * (never dropped outright -- an absent `editorScript` is what produces the "doesn't include
	 * support for" error in every editor context).
	 *
	 * @param array<string, mixed> $metadata Parsed block.json.
	 * @return array<string, mixed>
	 */
	public static function drop_missing_editor_script( array $metadata ): array {
		if ( empty( $metadata['name'] ) || 0 !== strpos( (string) $metadata['name'], 'ttm/' ) ) {
			return $metadata;
		}

		$block_slug = substr( (string) $metadata['name'], strlen( 'ttm/' ) );
		$asset_file = TTM_CORE_DIR . "build/blocks/{$block_slug}/index.asset.php";
		$script_file = TTM_CORE_DIR . "build/blocks/{$block_slug}/index.js";

		if ( ! file_exists( $asset_file ) || ! file_exists( $script_file ) ) {
			self::$fallback_blocks[]  = (string) $metadata['name'];
			$metadata['editorScript'] = 'ttm-core-editor-fallback';

			return $metadata;
		}

		// $asset_file is TTM_CORE_DIR . a fixed "build/blocks/{slug}/index.asset.php" suffix
		// built from the block's own registered name, never from request input.
		// forbidden-patterns:allow-variable-include (SPEC rule 15).
		$asset = include $asset_file;
		$handle = "ttm-{$block_slug}-editor-script";

		wp_register_script(
			$handle,
			TTM_CORE_URL . "build/blocks/{$block_slug}/index.js",
			(array) ( $asset['dependencies'] ?? [] ),
			(string) ( $asset['version'] ?? TTM_CORE_VERSION ),
			true
		);

		$metadata['editorScript'] = $handle;

		return $metadata;
	}

	/**
	 * Register the fallback editor script (only when at least one block needed it this
	 * request) with the list of block names it should register client-side.
	 */
	private static function register_fallback_script(): void {
		if ( empty( self::$fallback_blocks ) ) {
			return;
		}

		wp_register_script(
			'ttm-core-editor-fallback',
			TTM_CORE_URL . 'assets/editor-fallback.js',
			[ 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n' ],
			TTM_CORE_VERSION,
			true
		);

		wp_add_inline_script(
			'ttm-core-editor-fallback',
			'window.ttmCoreBlocks = ' . wp_json_encode( array_values( array_unique( self::$fallback_blocks ) ) ) . ';',
			'before'
		);
	}

	/**
	 * Add the "These Things Matter" block category.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing categories.
	 * @return array<int, array<string, mixed>>
	 */
	public static function add_category( array $categories ): array {
		$categories[] = [
			'slug'  => 'ttm',
			'title' => __( 'These Things Matter', 'ttm-core' ),
		];

		return $categories;
	}
}
