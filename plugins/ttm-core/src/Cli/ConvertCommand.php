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

			$remaining_shortcodes = (array) ( $record['report']['shortcodes'] ?? [] );
			$shortcodes_note      = $this->shortcodes_note( $remaining_shortcodes );

			if ( $dry_run ) {
				$messages[] = "post {$post_id} ({$post->post_name}): dry run, " . wp_json_encode( $counts ) . $shortcodes_note;
				continue;
			}

			$footnotes = (array) ( $record['footnotes'] ?? [] );
			$this->import_one( $post, $blocks, $footnotes );

			$note = "post {$post_id} ({$post->post_name}): converted, " . wp_json_encode( $counts ) . $shortcodes_note;
			if ( ! $this->footnotes_verified( $post_id, $footnotes ) ) {
				$note .= ' [footnotes-mismatch]';
			}
			// `report.textEqual` is `scripts/convert-classic.mjs`'s own before/after comparison
			// (SPEC §6.8's "extended" text-equality check, incl. stripping [caption]/[gallery]/
			// [audio]'s own bracket syntax): it already accounts for every substitution the
			// pre-pass makes (a [ref] note's text moving out of the body into the footnotes
			// list, a shortcode becoming a block, etc). Re-deriving an independent comparison in
			// PHP against the raw classic content -- tried initially -- can't reproduce that and
			// produced false mismatches on nearly every post; surfacing the JS-computed value is
			// correct and simpler.
			if ( isset( $record['report']['textEqual'] ) && ! $record['report']['textEqual'] ) {
				$note .= ' [text-mismatch]';
			}
			$messages[] = $note;
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
			// `rawHandler`'s own `<sup data-fn>` marker recognition is editor-only (a RichText
			// format, applied by a human typing a footnote); it never inserts the accompanying
			// `core/footnotes` block that actually reads the `footnotes` meta and renders the
			// note list (SPEC §6.8). Append it once here so the markers convert-classic.mjs
			// already wrote have somewhere to resolve to.
			if ( false === strpos( $blocks, 'wp:footnotes' ) ) {
				$blocks = rtrim( $blocks ) . "\n\n<!-- wp:footnotes /-->\n";
			}
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

		if ( ! empty( $footnotes ) ) {
			// `footnotes` is WordPress core's own post-meta key for the core/footnotes block
			// (registered by WP core itself, not by this plugin) - stored as a JSON string,
			// exactly the shape core's own editor writes: [{id, content}, ...]. WP core hooks
			// its own `sanitize_post_meta_footnotes` filter (`_wp_filter_post_meta_footnotes()`,
			// wp-includes/blocks.php) unconditionally, which `json_decode()`s the incoming
			// string and returns `''` outright if that fails -- and `update_metadata()` itself
			// unslashes the value before that filter ever runs. `wp_json_encode()`'s own
			// backslash-escaped quotes (`\"` around an href, say) are exactly what
			// `wp_unslash()` (a plain `stripslashes()`) strips, breaking the JSON and silently
			// discarding every footnote whose content has an escaped character in it (found via
			// a real, near-total mismatch on the export; see docs/feedback/phase-4/LIVE-TRIAGE.md).
			// `wp_slash()` first cancels that out, the same way WP core's own REST meta
			// controller does before every `update_metadata()` call.
			update_post_meta( $post->ID, 'footnotes', wp_slash( wp_json_encode( $footnotes ) ) );
		}

		update_post_meta( $post->ID, 'ttm_converted_at', Clock::now()->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Whether every footnote's text appears at least once in the post's rendered content
	 * (`the_content`, SPEC §6.8) -- catches a marker that never resolved (no matching
	 * `core/footnotes` block or meta) as well as a note whose text got mangled in transit.
	 * Vacuously true when there are no footnotes. "At least once", not "exactly once": a short
	 * footnote's own text (e.g. a proper noun) legitimately also appears verbatim earlier in
	 * the body prose that led up to the reference, which isn't a real mismatch -- found via a
	 * real false positive on the export; see docs/feedback/phase-4/LIVE-TRIAGE.md.
	 *
	 * @param int                                          $post_id   Post id (already updated).
	 * @param array<int, array{id:string, content:string}> $footnotes Footnotes, WP core's own shape.
	 * @return bool
	 */
	private function footnotes_verified( int $post_id, array $footnotes ): bool {
		if ( empty( $footnotes ) ) {
			return true;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// `core/footnotes`' render callback only receives `postId` block context when a real
		// "current post" is set up (WP core's own context provider reads `get_the_ID()`) --
		// without this, `apply_filters( 'the_content', … )` alone renders it as empty (verified
		// against this project's WP core version).
		global $post;
		$outer_post = $post;
		$post       = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- setup_postdata() requires the global, restored below.
		setup_postdata( $post );

		$rendered = (string) apply_filters( 'the_content', $post->post_content );

		$post = $outer_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the caller's global.
		wp_reset_postdata();

		$plain = $this->normalized_text( $rendered );

		foreach ( $footnotes as $footnote ) {
			// `wptexturize()` and `convert_smilies()` both also run on `the_content` (after the
			// footnotes block renders the raw meta text) -- e.g. a literal "..." becomes a
			// single "…" character, and ":-)" becomes an `<img class="wp-smiley">` with no
			// matching visible text at all -- so the expected text needs the same two passes:
			// wptexturize's rewrites still compare as plain text either way, and a smiley
			// disappearing from *both* sides equally (`normalized_text()` strips the `<img>`
			// same as any other tag) still means the two sides agree, rather than the raw ":-)"
			// searching for literal text that can now never appear (found via a real mismatch
			// on the export; see docs/feedback/phase-4/LIVE-TRIAGE.md).
			$text = $this->normalized_text( convert_smilies( wptexturize( (string) ( $footnote['content'] ?? '' ) ) ) );
			if ( '' === $text ) {
				continue;
			}
			if ( 0 === substr_count( $plain, $text ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Plain, entity-decoded, whitespace-collapsed text for a loose content comparison --
	 * `wptexturize()` (part of `the_content`) rewrites plain `...` to a typographic ellipsis
	 * entity, so without decoding, a footnote's own raw content never matches its own rendered
	 * text (found via a real mismatch on the export; see docs/feedback/phase-4/LIVE-TRIAGE.md).
	 *
	 * @param string $html HTML fragment.
	 * @return string
	 */
	private function normalized_text( string $html ): string {
		$decoded = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/', ' ', $decoded ) );
	}

	/**
	 * The dry-run/converted message suffix listing any shortcode the pre-pass deliberately left
	 * in place (SPEC §6.8, `report.shortcodes`), or `''` when none.
	 *
	 * @param array<int, array{name: string, count: int}> $remaining Remaining shortcode names/counts.
	 * @return string
	 */
	private function shortcodes_note( array $remaining ): string {
		if ( empty( $remaining ) ) {
			return '';
		}

		$parts = array_map(
			static fn ( array $entry ): string => sprintf( '%s(%d)', $entry['name'], $entry['count'] ),
			$remaining
		);

		return ', remaining shortcodes: ' . implode( ', ', $parts );
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
