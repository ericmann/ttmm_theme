<?php
/**
 * Discovers and registers every `ttm/` block from plugins/ttm-core/blocks/{name}/block.json.
 *
 * @package TTM\Core\Blocks
 */

declare( strict_types=1 );

namespace TTM\Core\Blocks;

/**
 * Registers blocks, drops editorScript when the build is missing, adds the `ttm` category.
 */
class Registrar {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_blocks' ] );
		add_filter( 'block_type_metadata', [ self::class, 'drop_missing_editor_script' ] );
		add_filter( 'block_categories_all', [ self::class, 'add_category' ] );
	}

	/**
	 * Register every block found under blocks/*\/block.json.
	 */
	public static function register_blocks(): void {
		$files = glob( TTM_CORE_DIR . 'blocks/*/block.json' );

		foreach ( false === $files ? [] : $files as $file ) {
			$metadata = json_decode( (string) file_get_contents( $file ), true );

			if ( ! empty( $metadata['name'] ) && \WP_Block_Type_Registry::get_instance()->is_registered( $metadata['name'] ) ) {
				continue;
			}

			register_block_type( dirname( $file ) );
		}
	}

	/**
	 * Drop `editorScript` for `ttm/*` blocks when their build output is missing, so an
	 * unbuilt checkout doesn't fatal the editor.
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

		if ( ! file_exists( $asset_file ) ) {
			unset( $metadata['editorScript'] );
		}

		return $metadata;
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
