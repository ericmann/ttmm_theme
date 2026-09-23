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
use TTM\Core\Support\Html;
use WP_Post;
use WP_Query;

/**
 * Content-cleanup report: one row per flagged post, no HTTP (rule 16), batched queries (rule 12).
 */
class AuditCommand extends Command {

	/**
	 * Known non-content shortcode names `shortcode` flags (SPEC §6.7): plain prose in square
	 * brackets like `[architect]` is never flagged, only these.
	 */
	private const KNOWN_SHORTCODES = '/\[(ref|mfn|cci|cc_[a-z]+|cc|caption|audio|seoslides)\b/i';

	/**
	 * Post types counted by `--summary`'s `inert-rows` line (SPEC §6.7, Decision Q5): rows that
	 * are never content, so never worth a per-post audit flag of their own.
	 */
	private const INERT_POST_TYPES = [ 'feedback', 'custom_css', 'wp_template', 'wp_global_styles', 'nav_menu_item' ];

	/**
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --only=<check> (comma-separated allowed), --summary.
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

		if ( ! empty( $assoc['summary'] ) ) {
			return $this->summary( $rows );
		}

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ sprintf( '%d post(s) flagged.', count( $rows ) ) ],
		];
	}

	/**
	 * `audit --summary` (SPEC §6.7): one row per flag with its count across every flagged post,
	 * plus an `inert-rows` row (Decision Q5: `feedback`/`custom_css`/`wp_template`/
	 * `wp_global_styles`/`nav_menu_item` posts, never audited per-post since they're never
	 * content). `Loader::output_audit()` renders this shape as a markdown table.
	 *
	 * @param array<int, array{id: int, slug: string, flags: string[], detail: array<string, mixed>}> $rows Per-post audit rows.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function summary( array $rows ): array {
		$counts = [];
		foreach ( $rows as $row ) {
			foreach ( $row['flags'] as $flag ) {
				$counts[ $flag ] = ( $counts[ $flag ] ?? 0 ) + 1;
			}
		}
		ksort( $counts );

		$summary_rows = [];
		foreach ( $counts as $flag => $count ) {
			$summary_rows[] = [
				'flag'  => $flag,
				'count' => $count,
			];
		}
		$summary_rows[] = [
			'flag'  => 'inert-rows',
			'count' => $this->inert_rows_count(),
		];

		return [
			'ok'       => true,
			'rows'     => $summary_rows,
			'messages' => [ sprintf( '%d flag(s) across %d flagged post(s).', count( $counts ), count( $rows ) ) ],
		];
	}

	/**
	 * Bounded count of every post in `INERT_POST_TYPES`, any status.
	 *
	 * @return int
	 */
	private function inert_rows_count(): int {
		$query = new WP_Query(
			[
				'post_type'      => self::INERT_POST_TYPES,
				'post_status'    => 'any',
				'posts_per_page' => (int) Config::get( 'cli.batch', 200 ),
				'fields'         => 'ids',
			]
		);

		return (int) $query->found_posts;
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
			'remote-image'         => fn (): bool => $this->has_remote_image( (string) $post->post_content, $index['host'] ),
			'post-format-aside'    => static fn (): bool => has_post_format( 'aside', $post ),
			'no-tags'              => static fn (): bool => empty( wp_get_post_tags( $post_id, [ 'fields' => 'ids' ] ) ),
			'writing-no-form'      => fn (): bool => $this->is_writing_no_form( $post_id ),
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

		if ( null === $only || in_array( 'shortcode', $only, true ) ) {
			$names = $this->shortcode_names( (string) $post->post_content );
			if ( ! empty( $names ) ) {
				$flags[]              = 'shortcode';
				$detail['shortcodes'] = $names;
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
	 * True when any `<img src>` in the content resolves to a host other than the site's own.
	 *
	 * @param string      $content Post content.
	 * @param string|null $host    Site host (from the link index).
	 * @return bool
	 */
	private function has_remote_image( string $content, ?string $host ): bool {
		foreach ( Html::image_srcs( $content ) as $src ) {
			$img_host = wp_parse_url( $src, PHP_URL_HOST );
			if ( ! empty( $img_host ) && $img_host !== $host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every known shortcode name (`KNOWN_SHORTCODES`) found in the content, deduped and
	 * lower-cased -- prose in square brackets that isn't one of these (e.g. `[architect]`) never
	 * matches (SPEC §6.7).
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	private function shortcode_names( string $content ): array {
		if ( ! preg_match_all( self::KNOWN_SHORTCODES, $content, $matches ) ) {
			return [];
		}

		return array_values( array_unique( array_map( 'strtolower', $matches[1] ) ) );
	}

	/**
	 * True when a post's primary category is the Writing section, it carries no `series` term,
	 * and it has no `ttm_form` meta row at all (SPEC §6.7: distinct from an explicit `'article'`
	 * value, which `register_post_meta()`'s own default would otherwise mask).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_writing_no_form( int $post_id ): bool {
		$writing_slug = (string) Config::get( 'sections.writing_slug', 'writing' );
		if ( PrimaryCategory::slug( $post_id ) !== $writing_slug ) {
			return false;
		}

		$series = wp_get_post_terms( $post_id, 'series', [ 'fields' => 'ids' ] );
		if ( ! empty( $series ) ) {
			return false;
		}

		return ! metadata_exists( 'post', $post_id, 'ttm_form' );
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
