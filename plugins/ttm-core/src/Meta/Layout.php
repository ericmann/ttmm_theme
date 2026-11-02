<?php
/**
 * Which single-post template a post renders with.
 *
 * @package TTM\Core\Meta
 */

declare( strict_types=1 );

namespace TTM\Core\Meta;

use TTM\Core\Config;

/**
 * A template picked in the editor (the post's `_wp_page_template`) wins; otherwise a Journal
 * entry opens on `single-journal` and anything else on `single`. Layout only: comments follow
 * the section (Comments\Threading), not the template.
 */
class Layout {

	/**
	 * Templates the editor may pick for a post (theme.json `customTemplates`).
	 */
	public const CHOOSABLE = [ 'single-journal' ];

	/**
	 * Pure: the template slug for a post.
	 *
	 * @param string $chosen       The post's picked template slug, or '' for the default.
	 * @param string $section_slug The post's primary category slug, or ''.
	 */
	public static function resolve( string $chosen, string $section_slug ): string {
		if ( in_array( $chosen, self::CHOOSABLE, true ) ) {
			return $chosen;
		}

		return '' !== $section_slug && (string) Config::get( 'sections.journal_slug', 'journal' ) === $section_slug
			? 'single-journal'
			: 'single';
	}

	/**
	 * The template slug for a stored post.
	 *
	 * @param int $post_id Post id.
	 */
	public static function of( int $post_id ): string {
		return self::resolve(
			(string) get_page_template_slug( $post_id ),
			(string) PrimaryCategory::slug( $post_id )
		);
	}
}
