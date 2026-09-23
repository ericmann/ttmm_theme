<?php
/**
 * `wp ttm migrate:politics|migrate:redirects|migrate:close-comments|migrate:excerpts|migrate:images`
 * (SPEC §6.7, Q3).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Support\Html;
use TTM\Core\Support\Text;
use WP_Query;
use WP_Term;

/**
 * Politics()/run() is the class's primary command (matching the file's own name, per the
 * series:assign/convert:export precedent); redirects() and close_comments() are separate public
 * cores wired to their own `wp ttm migrate:*` commands by Cli\Loader.
 */
class MigrateCommand extends Command {

	/**
	 * `migrate:politics [--to=child|tag] [--dry-run]`.
	 *
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --to=child|tag, --dry-run.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		$to = (string) ( $assoc['to'] ?? 'child' );
		if ( ! in_array( $to, [ 'child', 'tag' ], true ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: migrate:politics [--to=child|tag] [--dry-run]' ],
			];
		}

		$dry_run  = ! empty( $assoc['dry-run'] );
		$politics = $this->politics_term();

		if ( ! $politics ) {
			return [
				'ok'       => true,
				'rows'     => [],
				'messages' => [ 'No Politics category found; nothing to do (already migrated?).' ],
			];
		}

		return 'tag' === $to
			? $this->politics_tag( $politics, $dry_run )
			: $this->politics_child( $politics, $dry_run );
	}

	/**
	 * `migrate:redirects [--format=nginx|json]`.
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --format=nginx|json.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function redirects( array $args, array $assoc ): array {
		unset( $args );

		$format = (string) ( $assoc['format'] ?? 'json' );
		$rows   = $this->stored_redirects();

		if ( 'nginx' === $format ) {
			$messages = array_map(
				static fn ( array $row ): string => sprintf( 'rewrite ^%s(.*)$ %s$1 permanent;', $row['from'], $row['to'] ),
				$rows
			);

			return [
				'ok'       => true,
				'rows'     => $rows,
				'messages' => $messages,
			];
		}

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ (string) wp_json_encode( $rows ) ],
		];
	}

	/**
	 * `migrate:close-comments [--dry-run]`: closes comments/pingbacks on every post and page,
	 * batched, and sets the site defaults to closed too.
	 *
	 * Updates `comment_status`/`ping_status` directly via `$wpdb` (clearing the post cache
	 * itself) rather than `wp_update_post()`: that would fire `transition_post_status` once
	 * per post, and `Cache\Purge::on_transition()` fires `ttm_purge_urls` on every one of
	 * those -- a purge storm for what's really one bulk change. `ttm_purge_urls` fires exactly
	 * once here, after the whole batch, covering every affected URL (SPEC rule 11).
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --dry-run.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function close_comments( array $args, array $assoc ): array {
		unset( $args );

		global $wpdb;

		$dry_run = ! empty( $assoc['dry-run'] );
		$batch   = (int) Config::get( 'cli.batch', 200 );
		$rows    = [];
		$urls    = [ home_url( '/' ) ];

		foreach ( [ 'post', 'page' ] as $post_type ) {
			$paged = 1;
			do {
				$query = new WP_Query(
					[
						'post_type'      => $post_type,
						'post_status'    => 'any',
						'posts_per_page' => $batch,
						'paged'          => $paged,
						'fields'         => 'ids',
					]
				);

				foreach ( $query->posts as $post_id ) {
					$post = get_post( $post_id );
					if ( 'closed' === $post->comment_status && 'closed' === $post->ping_status ) {
						continue;
					}

					$rows[] = [ 'post_id' => (int) $post_id ];

					if ( ! $dry_run ) {
						$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- clean_post_cache() below covers caching; see docblock for why this bypasses wp_update_post().
							$wpdb->posts,
							[
								'comment_status' => 'closed',
								'ping_status'    => 'closed',
							],
							[ 'ID' => $post_id ]
						);
						clean_post_cache( $post_id );

						$permalink = get_permalink( $post_id );
						if ( is_string( $permalink ) ) {
							$urls[] = $permalink;
						}
					}
				}//end foreach

				$found = count( $query->posts );
				++$paged;
			} while ( $found === $batch );
		}//end foreach

		if ( ! $dry_run ) {
			update_option( 'default_comment_status', 'closed' );
			update_option( 'default_ping_status', 'closed' );

			if ( ! empty( $rows ) ) {
				$this->purge( $urls );
			}
		}

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [
				$dry_run
					? sprintf( 'Would close comments/pings on %d post(s).', count( $rows ) )
					: sprintf( 'Closed comments/pings on %d post(s).', count( $rows ) ),
			],
		];
	}

	/**
	 * `migrate:excerpts --from=yoast [--dry-run]` (SPEC §6.7): posts with an empty
	 * `post_excerpt` and a non-empty `_yoast_wpseo_metadesc` get it as the excerpt, truncated
	 * at a sentence boundary within `excerpt_length` words. Never overwrites an existing
	 * excerpt; Journal-primary posts are excluded (their excerpt is derived, not migrated).
	 *
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --from=yoast, --dry-run.
	 */
	public function excerpts( array $args, array $assoc ): array {
		unset( $args );

		$from = (string) ( $assoc['from'] ?? '' );
		if ( 'yoast' !== $from ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: migrate:excerpts --from=yoast [--dry-run]' ],
			];
		}

		$dry_run      = ! empty( $assoc['dry-run'] );
		$batch        = (int) Config::get( 'cli.batch', 200 );
		$max_words    = (int) Config::get( 'excerpt_length', 55 );
		$journal_slug = (string) Config::get( 'sections.journal_slug', 'journal' );
		$rows         = [];
		$paged        = 1;

		do {
			$query = new WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => 'any',
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
				]
			);

			foreach ( $query->posts as $post_id ) {
				$post_id = (int) $post_id;
				$post    = get_post( $post_id );

				if ( ! $post || '' !== trim( (string) $post->post_excerpt ) ) {
					continue;
				}

				if ( PrimaryCategory::slug( $post_id ) === $journal_slug ) {
					continue;
				}

				$meta_desc = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
				if ( '' === trim( $meta_desc ) ) {
					continue;
				}

				$excerpt = Text::truncate_sentences( $meta_desc, $max_words );

				if ( ! $dry_run ) {
					wp_update_post(
						[
							'ID'           => $post_id,
							'post_excerpt' => $excerpt,
						]
					);
				}

				$rows[] = [
					'post_id' => $post_id,
					'excerpt' => $excerpt,
				];
			}//end foreach

			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [
				$dry_run
					? sprintf( 'Would fill %d excerpt(s) from Yoast.', count( $rows ) )
					: sprintf( 'Filled %d excerpt(s) from Yoast.', count( $rows ) ),
			],
		];
	}

	/**
	 * `migrate:images [--hosts=<comma-list>] [--post=<id>] [--dry-run]` (SPEC §6.7): for every
	 * published post (or just `--post`), Photon (`iN.wp.com/<host>/<path>`) `<img src>` URLs
	 * whose `<host>` equals `migration.photon_origin` are rewritten to `https://<host>/<path>`
	 * with no network fetch; `<img src>` URLs on any other host in `--hosts`/`migration.image_hosts`
	 * are sideloaded into the media library and both the `src` and any wrapping `<a href>` to the
	 * same URL are rewritten to the new attachment URL. A fetch failure leaves that `src`
	 * untouched. `ttm_classic_backup` is written once, first writer wins (shared with
	 * convert:import/revert, rule 49); `ttm_images_rewritten` records the per-post count.
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --hosts, --post, --dry-run.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function images( array $args, array $assoc ): array {
		unset( $args );

		$dry_run = ! empty( $assoc['dry-run'] );
		$hosts   = isset( $assoc['hosts'] )
			? array_values( array_filter( array_map( 'trim', explode( ',', (string) $assoc['hosts'] ) ) ) )
			: (array) Config::get( 'migration.image_hosts', [] );
		$origin  = (string) Config::get( 'migration.photon_origin', 'eric.mann.blog' );
		$timeout = (int) Config::get( 'migration.image_timeout', 20 );

		$post_ids = isset( $assoc['post'] )
			? [ (int) $assoc['post'] ]
			: $this->published_posts();

		$rows = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$content   = $post->post_content;
			$rewritten = 0;

			foreach ( Html::image_srcs( $content ) as $src ) {
				$photon_url = Html::photon_origin_url( $src, $origin );
				if ( null !== $photon_url ) {
					$content = Html::replace_url( $content, $src, $photon_url );
					++$rewritten;
					continue;
				}

				$host = (string) wp_parse_url( $src, PHP_URL_HOST );
				if ( '' === $host || ! in_array( $host, $hosts, true ) ) {
					continue;
				}

				if ( $dry_run ) {
					++$rewritten;
					continue;
				}

				$new_url = $this->sideload( $src, $post_id, $timeout );
				if ( null === $new_url ) {
					continue;
				}

				$content = Html::replace_url( $content, $src, $new_url );
				++$rewritten;
			}//end foreach

			if ( 0 === $rewritten ) {
				continue;
			}

			$rows[] = [
				'post_id'   => $post_id,
				'rewritten' => $rewritten,
			];

			if ( $dry_run ) {
				continue;
			}

			if ( '' === (string) get_post_meta( $post_id, 'ttm_classic_backup', true ) ) {
				update_post_meta( $post_id, 'ttm_classic_backup', $post->post_content );
			}

			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => $content,
				]
			);
			update_post_meta( $post_id, 'ttm_images_rewritten', $rewritten );
		}//end foreach

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [
				$dry_run
					? sprintf( 'Would rewrite images on %d post(s).', count( $rows ) )
					: sprintf( 'Rewrote images on %d post(s).', count( $rows ) ),
			],
		];
	}

	/**
	 * Sideload a remote image into the media library, with `http_request_timeout` overridden
	 * only for the duration of this call (SPEC §6.7). Null on failure — the caller leaves the
	 * original `src` untouched.
	 *
	 * @param string $url     Remote image URL.
	 * @param int    $post_id Post to attach the sideloaded image to.
	 * @param int    $timeout Request timeout, seconds.
	 * @return string|null
	 */
	private function sideload( string $url, int $post_id, int $timeout ): ?string {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$set_timeout = static fn (): int => $timeout;

		add_filter( 'http_request_timeout', $set_timeout );
		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_media_sideload_image -- CLI-only migration, see class docblock.
		remove_filter( 'http_request_timeout', $set_timeout );

		if ( is_wp_error( $attachment_id ) ) {
			return null;
		}

		$attachment_url = wp_get_attachment_url( (int) $attachment_id );

		return is_string( $attachment_url ) ? $attachment_url : null;
	}

	/**
	 * Batched ids of every published post.
	 *
	 * @return int[]
	 */
	private function published_posts(): array {
		$batch = (int) Config::get( 'cli.batch', 200 );
		$ids   = [];
		$paged = 1;

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
			$ids   = array_merge( $ids, $query->posts );
			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return $ids;
	}

	/**
	 * `--to=child`: reparent Politics under Opinion (creating Opinion if needed), add Opinion
	 * to every Politics post (they keep Politics too -- it's a child now, not a replacement),
	 * and set each post's `ttm_primary_category` to Opinion so `PrimaryCategory::slug()`
	 * resolves to `'opinion'` (SPEC Q3: Politics posts are Opinion posts, kicker-wise; only
	 * `Bindings\Sources::in_politics()`'s any-position check still finds "politics" on them).
	 * Idempotent: a second run sees Politics already parented under Opinion and does nothing
	 * further (including to individual posts).
	 *
	 * @param WP_Term $politics The Politics category term.
	 * @param bool    $dry_run  Whether to only report the plan.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function politics_child( WP_Term $politics, bool $dry_run ): array {
		$opinion = get_term_by( 'slug', 'opinion', 'category' );
		if ( $opinion && (int) $politics->parent === (int) $opinion->term_id ) {
			return [
				'ok'       => true,
				'rows'     => [],
				'messages' => [ 'Politics is already a child of Opinion; nothing to do.' ],
			];
		}

		$posts = $this->posts_in_category( $politics->term_id );

		if ( $dry_run ) {
			return [
				'ok'       => true,
				'rows'     => array_map( static fn ( int $id ): array => [ 'post_id' => $id ], $posts ),
				'messages' => [
					sprintf( 'Would create/reuse "Opinion" and reparent Politics (term %d) under it.', $politics->term_id ),
					sprintf( 'Would add Opinion to %d Politics post(s) and set it as their primary category.', count( $posts ) ),
				],
			];
		}

		$opinion_id = $this->ensure_opinion_term();
		wp_update_term( $politics->term_id, 'category', [ 'parent' => $opinion_id ] );

		$rows = [];
		foreach ( $posts as $post_id ) {
			$categories = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
			if ( ! in_array( $opinion_id, $categories, true ) ) {
				$categories[] = $opinion_id;
				wp_set_post_categories( $post_id, array_values( array_unique( $categories ) ) );
			}
			update_post_meta( $post_id, 'ttm_primary_category', $opinion_id );

			$rows[] = [ 'post_id' => $post_id ];
		}

		$this->record_redirects(
			[
				[
					'from' => '/category/politics/',
					'to'   => '/category/opinion/politics/',
				],
				[
					'from' => '/category/politics/feed/',
					'to'   => '/category/opinion/politics/feed/',
				],
			]
		);

		$this->purge(
			array_filter(
				[
					home_url( '/' ),
					$this->category_link_or_null( $opinion_id ),
					$this->category_link_or_null( $politics->term_id ),
				]
			)
		);

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ sprintf( 'Politics is now a child of Opinion (%d post(s) updated).', count( $rows ) ) ],
		];
	}

	/**
	 * `--to=tag`: tag every Politics post "politics", move it to Opinion, then delete the
	 * Politics category. Idempotent: once the category is gone, `politics_term()` returns null
	 * and `run()` reports "nothing to do" on the next invocation.
	 *
	 * @param WP_Term $politics The Politics category term.
	 * @param bool    $dry_run  Whether to only report the plan.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function politics_tag( WP_Term $politics, bool $dry_run ): array {
		$posts = $this->posts_in_category( $politics->term_id );

		if ( $dry_run ) {
			return [
				'ok'       => true,
				'rows'     => array_map( static fn ( int $id ): array => [ 'post_id' => $id ], $posts ),
				'messages' => [ sprintf( 'Would tag %d post(s) "politics", move them to Opinion, and delete the Politics category.', count( $posts ) ) ],
			];
		}

		$opinion_id    = $this->ensure_opinion_term();
		$tag           = get_term_by( 'slug', 'politics', 'post_tag' );
		$tag_id        = $tag ? (int) $tag->term_id : (int) wp_insert_term( 'Politics', 'post_tag', [ 'slug' => 'politics' ] )['term_id'];
		$politics_link = $this->category_link_or_null( $politics->term_id );

		$rows = [];
		foreach ( $posts as $post_id ) {
			wp_set_post_tags( $post_id, [ $tag_id ], true );

			$categories   = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
			$categories   = array_values( array_diff( $categories, [ $politics->term_id ] ) );
			$categories[] = $opinion_id;
			wp_set_post_categories( $post_id, array_values( array_unique( $categories ) ) );

			$rows[] = [ 'post_id' => $post_id ];
		}

		wp_delete_term( $politics->term_id, 'category' );

		$this->record_redirects(
			[
				[
					'from' => '/category/politics/',
					'to'   => '/category/opinion/',
				],
				[
					'from' => '/category/politics/feed/',
					'to'   => '/category/opinion/feed/',
				],
			]
		);

		$this->purge( array_filter( [ home_url( '/' ), $this->category_link_or_null( $opinion_id ), $politics_link ] ) );

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ sprintf( 'Moved %d post(s) to Opinion (tagged "politics") and deleted the Politics category.', count( $rows ) ) ],
		];
	}

	/**
	 * The Politics category term, or null once migrated away (deleted, tag mode) or absent.
	 *
	 * @return WP_Term|null
	 */
	private function politics_term(): ?WP_Term {
		$slug = (string) Config::get( 'sections.politics_slug', 'politics' );
		$term = get_term_by( 'slug', $slug, 'category' );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Find or create the "Opinion" category (SPEC Q3: name "Opinion", slug from `sections.order`).
	 *
	 * @return int
	 */
	private function ensure_opinion_term(): int {
		$term = get_term_by( 'slug', 'opinion', 'category' );
		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( 'Opinion', 'category', [ 'slug' => 'opinion' ] );

		return (int) $created['term_id'];
	}

	/**
	 * Batched post ids currently in a category, any status.
	 *
	 * @param int $term_id Category term id.
	 * @return int[]
	 */
	private function posts_in_category( int $term_id ): array {
		$batch = (int) Config::get( 'cli.batch', 200 );
		$ids   = [];
		$paged = 1;

		do {
			$query = new WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => 'any',
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
					'cat'            => $term_id,
				]
			);
			$ids   = array_merge( $ids, $query->posts );
			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return $ids;
	}

	/**
	 * Merge redirect rows into option `ttm_redirects`, deduped and overwritten by `from`.
	 *
	 * @param array<int, array{from: string, to: string}> $entries New/updated redirect rows.
	 */
	private function record_redirects( array $entries ): void {
		$existing = get_option( 'ttm_redirects', [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$by_from = [];
		foreach ( $existing as $row ) {
			if ( is_array( $row ) && isset( $row['from'] ) ) {
				$by_from[ $row['from'] ] = $row;
			}
		}
		foreach ( $entries as $row ) {
			$by_from[ $row['from'] ] = $row;
		}

		update_option( 'ttm_redirects', array_values( $by_from ) );
	}

	/**
	 * The stored `ttm_redirects` option, or an empty array.
	 *
	 * @return array<int, array{from: string, to: string}>
	 */
	private function stored_redirects(): array {
		$rows = get_option( 'ttm_redirects', [] );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * A category's link, or null if it can't be resolved (e.g. already deleted).
	 *
	 * @param int $term_id Category term id.
	 * @return string|null
	 */
	private function category_link_or_null( int $term_id ): ?string {
		$link = get_category_link( $term_id );

		return is_string( $link ) ? $link : null;
	}

	/**
	 * Fire `ttm_purge_urls` for the given URLs (SPEC §6.7: "all writes fire ttm_purge_urls for
	 * the front page and both category URLs").
	 *
	 * @param array<int, string> $urls URLs to purge.
	 */
	private function purge( array $urls ): void {
		do_action( 'ttm_purge_urls', array_values( array_unique( $urls ) ) );
	}
}
