<?php
/**
 * Post form (article|chapter|story) derivation (SPEC §5.2).
 *
 * @package TTM\Core\Meta
 */

declare( strict_types=1 );

namespace TTM\Core\Meta;

use TTM\Core\Config;
use WP_Post;

/**
 * Derives and writes a post's `ttm_form` unless manually locked.
 */
class Form {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'save_post_post', [ self::class, 'on_save' ], 25, 2 );
	}

	/**
	 * Pure: series form ≠ nonfiction -> chapter; in Writing with no series -> story; else article.
	 *
	 * @param string|null $series_form The post's series term's ttm_form, or null when no series.
	 * @param bool        $in_writing  Whether the post is in the Writing section.
	 * @return string
	 */
	public static function derive( ?string $series_form, bool $in_writing ): string {
		if ( null !== $series_form && 'nonfiction' !== $series_form ) {
			return 'chapter';
		}

		if ( null === $series_form && $in_writing ) {
			return 'story';
		}

		return 'article';
	}

	/**
	 * Whether this save originates from the editor (a human publishing/updating a post),
	 * rather than WP-CLI or an import -- both of which can plausibly publish a real essay
	 * into Writing that isn't fiction (Decision "Editor-only story derivation"). `true` unless
	 * `WP_CLI`/`WP_IMPORTING` is defined and set; filterable so tests and other write paths
	 * can force either answer.
	 *
	 * @return bool
	 */
	public static function is_editor_save(): bool {
		$default = ! ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) );

		return (bool) apply_filters( 'ttm_form_editor_save', $default );
	}

	/**
	 * Write ttm_form unless ttm_form_locked is true. The `story` derivation (a Writing post
	 * with no series) only fires on an editor save; article/chapter are always written --
	 * only "a Writing post with no series is fiction" is the assumption that doesn't hold for
	 * CLI/import saves (Decision "Editor-only story derivation").
	 *
	 * @param int       $post_id     Post ID.
	 * @param WP_Post   $post        Post object.
	 * @param bool|null $from_editor Override for `is_editor_save()` (tests / other call sites).
	 */
	public static function on_save( int $post_id, WP_Post $post, ?bool $from_editor = null ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( get_post_meta( $post_id, 'ttm_form_locked', true ) ) {
			return;
		}

		$series_form = null;
		$series      = wp_get_post_terms( $post_id, 'series' );
		if ( ! empty( $series ) && ! is_wp_error( $series ) ) {
			$term_form   = get_term_meta( $series[0]->term_id, 'ttm_form', true );
			$series_form = '' !== $term_form ? $term_form : 'nonfiction';
		}

		$writing_slug = (string) Config::get( 'sections.writing_slug', 'writing' );
		$categories   = wp_get_post_categories( $post_id, [ 'fields' => 'slugs' ] );
		$in_writing   = in_array( $writing_slug, $categories, true );

		$derived = self::derive( $series_form, $in_writing );

		if ( 'story' === $derived && ! ( $from_editor ?? self::is_editor_save() ) ) {
			return;
		}

		update_post_meta( $post_id, 'ttm_form', $derived );
	}
}
