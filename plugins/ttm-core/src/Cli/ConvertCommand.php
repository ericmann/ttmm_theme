<?php
/**
 * `wp ttm convert:export|convert:import|convert:revert` (SPEC §6.7): the PHP half of the
 * classic-to-block migration pipeline, pairing with `scripts/convert-classic.mjs` (P8-01).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Support\Clock;
use WP_Post;
use WP_Query;

/**
 * Export() is the run() core (matching the class's own "primary" command, per the
 * series:assign/series:rebuild precedent); import()/revert() are separate public cores wired to
 * their own `wp ttm convert:*` commands by Cli\Loader.
 */
class ConvertCommand extends Command {

	/**
	 * `wp ttm convert:export [--out=<file>] [--post=<id>] [--all-classic]`.
	 *
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  Unused.
	 * @param array<string, mixed> $assoc `--out`, `--post`, `--all-classic`.
	 */
	public function run( array $args, array $assoc ): array {
		unset( $args );

		return $this->export( $assoc );
	}

	/**
	 * Write NDJSON `{id, slug, content_raw, footnotes_meta}` for every classic post (content
	 * with no `<!-- wp:` marker at all): either the one named by `--post`, or every classic post
	 * when `--all-classic` is passed.
	 *
	 * @param array<string, mixed> $assoc `--out`, `--post`, `--all-classic`.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	private function export( array $assoc ): array {
		$out = (string) ( $assoc['out'] ?? '' );

		if ( ! isset( $assoc['post'] ) && empty( $assoc['all-classic'] ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: convert:export --all-classic [--out=<file>], or convert:export --post=<id> [--out=<file>].' ],
			];
		}

		$posts = isset( $assoc['post'] )
			? array_filter( [ get_post( (int) $assoc['post'] ) ] )
			: $this->classic_posts();

		$lines = [];
		foreach ( $posts as $post ) {
			if ( ! $this->is_classic( $post ) ) {
				continue;
			}
			$lines[] = wp_json_encode(
				[
					'id'             => $post->ID,
					'slug'           => $post->post_name,
					'content_raw'    => $post->post_content,
					'footnotes_meta' => null,
				]
			);
		}

		$ndjson = implode( "\n", $lines ) . ( empty( $lines ) ? '' : "\n" );

		if ( '' !== $out ) {
			file_put_contents( $out, $ndjson ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI-only local file path, not a remote/front-end read (SPEC §6.7).
		} else {
			\WP_CLI::log( $ndjson );
		}

		return [
			'ok'       => true,
			'rows'     => [],
			'messages' => [ sprintf( 'Exported %d classic post(s).', count( $lines ) ) ],
		];
	}

	/**
	 * `wp ttm convert:import <file> [--dry-run] [--post=<id>] [--allow-freeform]`. `<file>` is
	 * `scripts/convert-classic.mjs`'s NDJSON output: `{id, slug, blocks, footnotes, report}`.
	 *
	 * @param string[]             $args  [0] is the NDJSON file path.
	 * @param array<string, mixed> $assoc `--dry-run`, `--post`, `--allow-freeform`.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function import( array $args, array $assoc ): array {
		$file = $args[0] ?? '';
		if ( '' === $file || ! file_exists( $file ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: convert:import <file> [--dry-run] [--post=<id>] [--allow-freeform]' ],
			];
		}

		$dry_run        = ! empty( $assoc['dry-run'] );
		$allow_freeform = ! empty( $assoc['allow-freeform'] );
		$only_post      = isset( $assoc['post'] ) ? (int) $assoc['post'] : 0;

		$rows     = [];
		$messages = [];
		$ok       = true;

		foreach ( $this->read_ndjson( $file ) as $record ) {
			$post_id = (int) ( $record['id'] ?? 0 );
			if ( $only_post && $post_id !== $only_post ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				$messages[] = "post {$post_id}: not found, skipped.";
				$ok         = false;
				continue;
			}

			$blocks   = (string) ( $record['blocks'] ?? '' );
			$parsed   = parse_blocks( $blocks );
			$freeform = 0;
			$counts   = [];
			foreach ( $parsed as $block ) {
				$name = (string) ( $block['blockName'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
				if ( in_array( $name, [ 'core/freeform', 'core/html' ], true ) ) {
					++$freeform;
				}
			}

			if ( $freeform > 0 && ! $allow_freeform ) {
				$messages[] = "post {$post_id} ({$post->post_name}): {$freeform} freeform/html block(s), refused (use --allow-freeform).";
				$ok         = false;
				continue;
			}

			$rows[] = array_merge(
				[
					'id'   => $post_id,
					'slug' => $post->post_name,
				],
				$counts 
			);

			if ( $dry_run ) {
				$messages[] = "post {$post_id} ({$post->post_name}): dry run, " . wp_json_encode( $counts );
				continue;
			}

			$this->import_one( $post, $blocks, (array) ( $record['footnotes'] ?? [] ) );
			$messages[] = "post {$post_id} ({$post->post_name}): converted, " . wp_json_encode( $counts );
		}//end foreach

		return [
			'ok'       => $ok,
			'rows'     => $rows,
			'messages' => $messages,
		];
	}

	/**
	 * Back up (once), write footnotes, revision, update content, stamp the conversion time.
	 *
	 * @param WP_Post                                      $post      Post to convert.
	 * @param string                                       $blocks    Serialized block markup.
	 * @param array<int, array{id:string, content:string}> $footnotes Footnotes, WP core's own shape.
	 */
	private function import_one( WP_Post $post, string $blocks, array $footnotes ): void {
		if ( '' === (string) get_post_meta( $post->ID, 'ttm_classic_backup', true ) ) {
			update_post_meta( $post->ID, 'ttm_classic_backup', $post->post_content );
		}

		if ( ! empty( $footnotes ) ) {
			// `footnotes` is WordPress core's own post-meta key for the core/footnotes block
			// (registered by WP core itself, not by this plugin) - stored as a JSON string,
			// exactly the shape core's own editor writes: [{id, content}, ...].
			update_post_meta( $post->ID, 'footnotes', wp_json_encode( $footnotes ) );
		}

		wp_save_post_revision( $post->ID );

		kses_remove_filters();
		wp_update_post(
			wp_slash(
				[
					'ID'           => $post->ID,
					'post_content' => $blocks,
				]
			)
		);
		kses_init_filters();

		update_post_meta( $post->ID, 'ttm_converted_at', Clock::now()->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * `wp ttm convert:revert [--post=<id>|--all] [--dry-run]`.
	 *
	 * @param string[]             $args  Unused.
	 * @param array<string, mixed> $assoc `--post`, `--all`, `--dry-run`.
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function revert( array $args, array $assoc ): array {
		unset( $args );

		if ( ! isset( $assoc['post'] ) && empty( $assoc['all'] ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: convert:revert --post=<id>, or convert:revert --all.' ],
			];
		}

		$dry_run = ! empty( $assoc['dry-run'] );
		$posts   = isset( $assoc['post'] )
			? array_filter( [ get_post( (int) $assoc['post'] ) ] )
			: $this->converted_posts();

		$messages = [];
		$rows     = [];

		foreach ( $posts as $post ) {
			$backup = (string) get_post_meta( $post->ID, 'ttm_classic_backup', true );
			if ( '' === $backup ) {
				$messages[] = "post {$post->ID} ({$post->post_name}): no backup, skipped.";
				continue;
			}

			$rows[] = [
				'id'   => $post->ID,
				'slug' => $post->post_name,
			];

			if ( $dry_run ) {
				$messages[] = "post {$post->ID} ({$post->post_name}): dry run, would restore backup.";
				continue;
			}

			kses_remove_filters();
			wp_update_post(
				wp_slash(
					[
						'ID'           => $post->ID,
						'post_content' => $backup,
					]
				)
			);
			kses_init_filters();
			delete_post_meta( $post->ID, 'ttm_converted_at' );

			$messages[] = "post {$post->ID} ({$post->post_name}): reverted.";
		}//end foreach

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => $messages,
		];
	}

	/**
	 * Whether a post's content has no `<!-- wp:` block comment at all.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	private function is_classic( WP_Post $post ): bool {
		return false === strpos( $post->post_content, '<!-- wp:' );
	}

	/**
	 * Every `post`-type post with classic content, batched (rule 12).
	 *
	 * @return WP_Post[]
	 */
	private function classic_posts(): array {
		$batch   = (int) Config::get( 'cli.batch', 200 );
		$results = [];
		$paged   = 1;

		do {
			$query = new WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
					'no_found_rows'  => false,
				]
			);

			foreach ( $query->posts as $post_id ) {
				$post = get_post( (int) $post_id );
				if ( $post && $this->is_classic( $post ) ) {
					$results[] = $post;
				}
			}

			++$paged;
			$found = count( $query->posts );
		} while ( $found === $batch );

		return $results;
	}

	/**
	 * Every post carrying `ttm_converted_at` (candidates for `convert:revert --all`), batched.
	 *
	 * @return WP_Post[]
	 */
	private function converted_posts(): array {
		$batch   = (int) Config::get( 'cli.batch', 200 );
		$results = [];
		$paged   = 1;

		do {
			$query = new WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
					'no_found_rows'  => false,
					'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded by posts_per_page.
						[
							'key'     => 'ttm_classic_backup',
							'compare' => 'EXISTS',
						],
					],
				]
			);

			foreach ( $query->posts as $post_id ) {
				$post = get_post( (int) $post_id );
				if ( $post ) {
					$results[] = $post;
				}
			}

			++$paged;
			$found = count( $query->posts );
		} while ( $found === $batch );

		return $results;
	}

	/**
	 * NDJSON: one decoded JSON object per non-blank line.
	 *
	 * @param string $path File path.
	 * @return array<int, array<string, mixed>>
	 */
	private function read_ndjson( string $path ): array {
		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- CLI-only local file path, not a remote/front-end read (SPEC §6.7).
		$records  = [];

		foreach ( explode( "\n", $contents ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$decoded = json_decode( $line, true );
			if ( is_array( $decoded ) ) {
				$records[] = $decoded;
			}
		}

		return $records;
	}
}
