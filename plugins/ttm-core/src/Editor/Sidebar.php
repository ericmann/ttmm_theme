<?php
/**
 * Enqueues the editor bundle (sidebar panel) for `post` only.
 *
 * @package TTM\Core\Editor
 */

declare( strict_types=1 );

namespace TTM\Core\Editor;

use TTM\Core\Config;

/**
 * Editor-only enqueue; never loads on the front end (SPEC rule 6).
 */
class Sidebar {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue `build/index.js` and inject `window.ttmEditorData`.
	 */
	public static function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		$asset_file = TTM_CORE_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		// forbidden-patterns:allow-variable-include -- $asset_file is TTM_CORE_DIR . a fixed literal suffix, never from request input (SPEC rule 15).
		$asset = require $asset_file;

		wp_enqueue_script(
			'ttm-core-editor',
			TTM_CORE_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_add_inline_script(
			'ttm-core-editor',
			'window.ttmEditorData = ' . wp_json_encode( self::editor_data() ) . ';',
			'before'
		);

		wp_set_script_translations( 'ttm-core-editor', 'ttm-core' );
	}

	/**
	 * `{sections, journalId, writingId, seriesForms}` for the editor bundle.
	 *
	 * @return array<string, mixed>
	 */
	private static function editor_data(): array {
		$order    = (array) Config::get( 'sections.order', [] );
		$sections = [];

		foreach ( $order as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$sections[] = [
					'id'   => $term->term_id,
					'slug' => $term->slug,
					'name' => $term->name,
				];
			}
		}

		$journal   = get_term_by( 'slug', (string) Config::get( 'sections.journal_slug', 'journal' ), 'category' );
		$writing   = get_term_by( 'slug', (string) Config::get( 'sections.writing_slug', 'writing' ), 'category' );
		$most_read = Checks::most_read();

		return [
			'sections'       => $sections,
			'journalId'      => $journal && ! is_wp_error( $journal ) ? $journal->term_id : 0,
			'writingId'      => $writing && ! is_wp_error( $writing ) ? $writing->term_id : 0,
			'seriesForms'    => [],
			'seriesParts'    => Checks::series_parts(),
			'mostReadCounts' => $most_read['counts'],
			'mostReadLimit'  => $most_read['limit'],
		];
	}
}
