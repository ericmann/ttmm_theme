<?php
/**
 * `wp ttm migrate:politics|migrate:redirects|migrate:close-comments` (SPEC §6.7, Q3).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
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
