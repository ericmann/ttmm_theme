<?php
/**
 * `wp ttm demo:options` and `wp ttm demo:verify [--posts=<n>] [--pages=<n>] [--series=<n>]
 * [--attachments=<n>]` (SPEC §6.3 step 3, §6.4).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;

/**
 * `options()`/`run()` prints the blueprint's `setSiteOptions` payload; `verify()` is the
 * post-`importWxr` assertion the blueprint's last step runs. Reads options and terms directly --
 * imports nothing above `Query` (SPEC §4).
 */
class DemoCommand extends Command {

	/**
	 * `demo:options`. {@inheritDoc}
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc Associative args (unused).
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args, $assoc );

		return $this->options();
	}

	/**
	 * `wp ttm demo:options`: one JSON line, keys in the fixed §6.4 order.
	 *
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function options(): array {
		$books = (array) get_option( 'ttm_books', [] );
		foreach ( $books as &$book ) {
			if ( is_array( $book ) ) {
				$book['cover_id'] = 0;
			}
		}
		unset( $book );

		$map = [
			'blogname'            => get_option( 'blogname' ),
			'blogdescription'     => get_option( 'blogdescription' ),
			'timezone_string'     => get_option( 'timezone_string' ),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'ttm_books'           => $books,
			'ttm_verse'           => get_option( 'ttm_verse', null ),
			'ttm_verse_history'   => get_option( 'ttm_verse_history', null ),
			'ttm_settings'        => [
				'newsletter' => [
					'provider' => 'none',
					'endpoint' => '',
				],
			],
		];

		$json = wp_json_encode( $map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return [
			'ok'       => true,
			'rows'     => [],
			'messages' => [ (string) $json ],
		];
	}

	/**
	 * `wp ttm demo:verify [--posts=<n>] [--pages=<n>] [--series=<n>] [--attachments=<n>]`: asserts
	 * the imported demo site matches the counts the build recorded, and that derived state
	 * (series term meta, primary categories) actually resolved rather than silently defaulting.
	 *
	 * @param string[]             $args  Positional args (unused).
	 * @param array<string, mixed> $assoc --posts=<n>, --pages=<n>, --series=<n>, --attachments=<n>.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function verify( array $args, array $assoc ): array {
		unset( $args );

		$want_posts       = (int) ( $assoc['posts'] ?? 0 );
		$want_pages       = (int) ( $assoc['pages'] ?? 0 );
		$want_series      = (int) ( $assoc['series'] ?? 0 );
		$want_attachments = (int) ( $assoc['attachments'] ?? 0 );

		$post_counts = wp_count_posts( 'post' );
		$got_posts   = (int) ( $post_counts->publish ?? 0 ) + (int) ( $post_counts->future ?? 0 );

		$page_counts = wp_count_posts( 'page' );
		$got_pages   = (int) ( $page_counts->publish ?? 0 );

		$series_rows = SeriesIndex::all();
		$got_series  = count( $series_rows );

		$attachment_counts = wp_count_posts( 'attachment' );
		$got_attachments   = (int) ( $attachment_counts->inherit ?? 0 );

		$messages = [];

		if ( $got_posts !== $want_posts ) {
			$messages[] = "post count mismatch: expected {$want_posts}, got {$got_posts}";
		}

		if ( $got_pages !== $want_pages ) {
			$messages[] = "page count mismatch: expected {$want_pages}, got {$got_pages}";
		}

		if ( 0 === $got_series || $got_series !== $want_series ) {
			$messages[] = "series count mismatch: expected {$want_series}, got {$got_series}";
		}

		if ( $got_attachments !== $want_attachments ) {
			$messages[] = "attachment count mismatch: expected {$want_attachments}, got {$got_attachments}";
		}

		foreach ( get_terms(
			[
				'taxonomy'   => 'series',
				'hide_empty' => false,
			]
		) as $term ) {
			if ( is_wp_error( $term ) ) {
				continue;
			}
			// `get_term_meta()` returns Series::register_meta()'s registered defaults
			// ("in-progress"/"nonfiction") for a term that never actually got either
			// meta row written -- `metadata_exists()` reads whether the row is really
			// there in `wp_termmeta`, bypassing the default so a genuinely missing
			// value (e.g. an import that dropped the injected `<wp:termmeta>`) is
			// still caught.
			$has_form   = metadata_exists( 'term', $term->term_id, 'ttm_form' );
			$has_status = metadata_exists( 'term', $term->term_id, 'ttm_status' );
			if ( ! $has_form || ! $has_status ) {
				$messages[] = "missing term meta: {$term->slug}";
			}
		}

		$default_category_id   = (int) get_option( 'default_category' );
		$default_category_term = $default_category_id ? get_term( $default_category_id, 'category' ) : null;
		$default_slug          = ( $default_category_term && ! is_wp_error( $default_category_term ) ) ? $default_category_term->slug : '';

		foreach ( $this->published_post_ids() as $post_id ) {
			$slug = PrimaryCategory::slug( $post_id );
			if ( '' === $slug ) {
				continue;
			}
			if ( 'uncategorized' === $slug || ( '' !== $default_slug && $slug === $default_slug ) ) {
				$messages[] = "post {$post_id} has an uncategorized primary category";
			}
		}

		if ( ! empty( $messages ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => $messages,
			];
		}

		return [
			'ok'       => true,
			'rows'     => [],
			'messages' => [ "demo:verify: ok (posts {$got_posts}, pages {$got_pages}, series {$got_series}, attachments {$got_attachments})" ],
		];
	}

	/**
	 * Every published post id, batched (rule: no unbounded queries).
	 *
	 * @return int[]
	 */
	private function published_post_ids(): array {
		$batch = (int) Config::get( 'cli.batch', 200 );
		$ids   = [];
		$paged = 1;

		do {
			$query = new \WP_Query(
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
}
