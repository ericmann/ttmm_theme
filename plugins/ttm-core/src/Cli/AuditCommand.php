<?php
/**
 * `wp ttm audit [--format=table|csv|json] [--only=<check>]` (SPEC §6.7).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use WP_Post;
use WP_Query;

/**
 * Content-cleanup report: one row per flagged post, no HTTP (rule 16), batched queries (rule 12).
 */
class AuditCommand extends Command {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --only=<check> (comma-separated allowed).
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		$only = isset( $assoc['only'] )
			? array_filter( array_map( 'trim', explode( ',', (string) $assoc['only'] ) ) )
			: null;

		$tag_candidates = $this->series_tag_candidates();
		$index          = $this->link_index();
		$batch          = (int) Config::get( 'cli.batch', 200 );
		$rows           = [];
		$paged          = 1;

		do {
			$query = new WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
				]
			);

			foreach ( $query->posts as $post_id ) {
				$row = $this->audit_post( (int) $post_id, $tag_candidates, $index, $only );
				if ( ! empty( $row['flags'] ) ) {
					$rows[] = $row;
				}
			}

			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ sprintf( '%d post(s) flagged.', count( $rows ) ) ],
		];
	}

	/**
	 * Run every check (or only the requested ones) against a single post.
	 *
	 * @param int                                                  $post_id        Post ID.
	 * @param array<int, string>                                   $tag_candidates Tag term_id => slug for series-tag-candidate.
	 * @param array{host: string|null, paths: array<string, bool>} $index Local link index.
	 * @param string[]|null                                        $only           Restrict to these check names, or null for all.
	 * @return array{id: int, slug: string, flags: string[], detail: array<string, mixed>}
	 */
	private function audit_post( int $post_id, array $tag_candidates, array $index, ?array $only ): array {
		$post = get_post( $post_id );

		$flags  = [];
		$detail = [];

		$checks = [
			'classic'              => static fn (): bool => false === strpos( (string) $post->post_content, '<!-- wp:' ),
			'no-excerpt'           => static fn (): bool => '' === trim( (string) $post->post_excerpt ),
			'no-featured-image'    => static fn (): bool => ! has_post_thumbnail( $post_id ),
			'missing-alt'          => fn (): bool => $this->has_missing_alt( $post_id, $post ),
			'no-primary'           => static fn (): bool => ! PrimaryCategory::id( $post_id ),
			'uncategorized'        => fn (): bool => in_array( 'uncategorized', $this->category_slugs( $post_id ), true ),
			'politics'             => fn (): bool => in_array( (string) Config::get( 'sections.politics_slug', 'politics' ), $this->category_slugs( $post_id ), true ),
			'legacy-footnotes'     => static fn (): bool => false !== strpos( (string) $post->post_content, 'modern-footnotes' ),
			'broken-internal-link' => fn (): bool => $this->has_broken_internal_link( (string) $post->post_content, $index ),
		];

		foreach ( $checks as $name => $check ) {
			if ( null !== $only && ! in_array( $name, $only, true ) ) {
				continue;
			}
			if ( $check() ) {
				$flags[] = $name;
			}
		}

		if ( null === $only || in_array( 'multi-category', $only, true ) ) {
			$slugs = $this->category_slugs( $post_id );
			if ( count( $slugs ) > 1 ) {
				$flags[]              = 'multi-category';
				$detail['categories'] = $slugs;
			}
		}

		if ( null === $only || in_array( 'series-tag-candidate', $only, true ) ) {
			$post_tag_ids = wp_get_post_tags( $post_id, [ 'fields' => 'ids' ] );
			$matches      = array_values( array_intersect( $post_tag_ids, array_keys( $tag_candidates ) ) );
			if ( ! empty( $matches ) ) {
				$flags[]                         = 'series-tag-candidate';
				$detail['series_tag_candidates'] = array_map(
					static fn ( int $id ): string => $tag_candidates[ $id ],
					$matches
				);
			}
		}

		return [
			'id'     => $post_id,
			'slug'   => (string) $post->post_name,
			'flags'  => $flags,
			'detail' => $detail,
		];
	}

	/**
	 * True when the featured image or any inline `<img>` lacks non-empty alt text.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return bool
	 */
	private function has_missing_alt( int $post_id, WP_Post $post ): bool {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
			if ( '' === trim( (string) $alt ) ) {
				return true;
			}
		}

		if ( preg_match_all( '/<img\b[^>]*>/i', (string) $post->post_content, $matches ) ) {
			foreach ( $matches[0] as $tag ) {
				// Group 2 is the alt *value* itself. The previous version trimmed the whole
				// match (group 0, e.g. `alt="tall"`) against the charlist "alt=\"' " -- since
				// every letter of a value like "tall" is also in that charlist, trim() ate the
				// value too and flagged real alt text as missing.
				if ( ! preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $alt_match ) ) {
					return true;
				}
				if ( '' === trim( $alt_match[2] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Assigned category slugs, per WP's own default-category fallback.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private function category_slugs( int $post_id ): array {
		return wp_list_pluck( get_the_category( $post_id ), 'slug' );
	}

	/**
	 * Tag term_id => slug for tags used on at least `cli.series_tag_min` published posts.
	 *
	 * @return array<int, string>
	 */
	private function series_tag_candidates(): array {
		$min   = (int) Config::get( 'cli.series_tag_min', 3 );
		$terms = get_terms(
			[
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
			]
		);

		$candidates = [];
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term->count >= $min ) {
					$candidates[ $term->term_id ] = $term->slug;
				}
			}
		}

		return $candidates;
	}

	/**
	 * Build the in-memory index of every published post/page path and category/tag/series term
	 * path, once, so `broken-internal-link` never needs HTTP (rule 16) or `url_to_postid()`.
	 *
	 * @return array{host: string|null, paths: array<string, bool>}
	 */
	private function link_index(): array {
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$paths = [];
		$batch = (int) Config::get( 'cli.batch', 200 );

		foreach ( [ 'post', 'page' ] as $post_type ) {
			$paged = 1;
			do {
				$query = new WP_Query(
					[
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'posts_per_page' => $batch,
						'paged'          => $paged,
						'fields'         => 'ids',
					]
				);

				foreach ( $query->posts as $id ) {
					$paths[ $this->path_of( (string) get_permalink( $id ) ) ] = true;
				}

				$found = count( $query->posts );
				++$paged;
			} while ( $found === $batch );
		}//end foreach

		foreach ( [ 'category', 'post_tag', 'series' ] as $taxonomy ) {
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$paths[ $this->path_of( $link ) ] = true;
				}
			}
		}

		// Attachment permalinks (the attachment's own page, distinct from the raw file URL
		// under uploads/ that is_ignorable_internal_path() already lets through) -- a post can
		// legitimately link to an image's attachment page.
		$paged = 1;
		do {
			$query = new WP_Query(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
				]
			);

			foreach ( $query->posts as $id ) {
				$paths[ $this->path_of( (string) get_permalink( $id ) ) ] = true;
			}

			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return [
			'host'  => $host,
			'paths' => $paths,
		];
	}

	/**
	 * True when the content has a `href` to this host whose path isn't in the local index and
	 * isn't one of the URL kinds `link_index()` deliberately doesn't enumerate.
	 *
	 * @param string                                               $content Post content.
	 * @param array{host: string|null, paths: array<string, bool>} $index   Local link index.
	 * @return bool
	 */
	private function has_broken_internal_link( string $content, array $index ): bool {
		if ( ! preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return false;
		}

		foreach ( $matches[1] as $href ) {
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( empty( $host ) || $host !== $index['host'] ) {
				continue;
			}

			$path = (string) wp_parse_url( $href, PHP_URL_PATH );
			if ( $this->is_ignorable_internal_path( $path ) ) {
				continue;
			}

			if ( ! array_key_exists( $this->path_of( $href ), $index['paths'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalise a URL to a trailing-slash-free path for index lookups.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function path_of( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return '' === $path ? $path : rtrim( $path, '/' );
	}

	/**
	 * URL kinds `link_index()` doesn't (and, for feeds/pagination/date archives, practically
	 * can't) enumerate every value of -- a link to one of these is never "broken" just because
	 * it isn't in the post/page/term index.
	 *
	 * @param string $path URL path (from `wp_parse_url( $href, PHP_URL_PATH )`).
	 * @return bool
	 */
	private function is_ignorable_internal_path( string $path ): bool {
		if ( '' === $path ) {
			return false;
		}

		$upload_path = (string) wp_parse_url( (string) ( wp_get_upload_dir()['baseurl'] ?? '' ), PHP_URL_PATH );
		if ( '' !== $upload_path && 0 === strpos( $path, $upload_path ) ) {
			return true;
		}

		if ( false !== strpos( $path, '/wp-content/' ) ) {
			return true;
		}

		if ( preg_match( '#(^|/)feed(/|$)#', $path ) ) {
			return true;
		}

		if ( preg_match( '#/page/\d+/?$#', $path ) ) {
			return true;
		}

		if ( preg_match( '#^/\d{4}(/\d{1,2}(/\d{1,2})?)?/?$#', $path ) ) {
			return true;
		}

		return false;
	}
}
