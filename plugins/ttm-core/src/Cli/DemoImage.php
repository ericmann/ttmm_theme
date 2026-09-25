<?php
/**
 * Sideloads a demo photograph (`docs/fixtures/demo/images/demo-*.jpg`) from the mounted
 * fixtures directory, with rule 53 credits written into the attachment description
 * (P1-02, SPEC §6.2).
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Support\Clock;

/**
 * A local-only sideload: `media_handle_sideload()` reads a file this repository already
 * shipped (`docs/fixtures/demo/images/`), never a URL or request input.
 */
class DemoImage {

	private const NAME_RE = '/^demo-[a-z0-9-]+\.jpg$/';

	/**
	 * Whether `$value` is a valid demo image file name.
	 *
	 * @param string $value Candidate file name.
	 * @return bool
	 */
	public static function is_demo_name( string $value ): bool {
		return 1 === preg_match( self::NAME_RE, $value );
	}

	/**
	 * The demo images directory. Filterable (`ttm_demo_images_dir`) so tests can point it at a
	 * synthetic fixture tree instead of the repo's own `docs/fixtures/demo/images/`.
	 *
	 * @return string
	 */
	public static function dir(): string {
		return (string) apply_filters(
			'ttm_demo_images_dir',
			Seeder::fixtures_root_dir() . '/demo/images'
		);
	}

	/**
	 * `docs/fixtures/demo/CREDITS.json`, decoded and keyed by `file`. `[]` when the file is
	 * absent or not valid JSON (rule 53's fields are read from it, never invented here).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function credits(): array {
		$path = dirname( self::dir() ) . '/CREDITS.json';
		if ( ! is_file( $path ) ) {
			return [];
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- a local, committed fixture file, not a remote fetch.
		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$by_file = [];
		foreach ( $decoded as $row ) {
			if ( is_array( $row ) && isset( $row['file'] ) ) {
				$by_file[ (string) $row['file'] ] = $row;
			}
		}

		return $by_file;
	}

	/**
	 * The attachment description: a translatable credit line built from one `credits()` row.
	 * `''` when `$row` is empty (no credit to show).
	 *
	 * @param array<string, mixed> $row One `credits()` row.
	 * @return string
	 */
	public static function credit_line( array $row ): string {
		if ( empty( $row ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: photograph title, 2: creator name, 3: licence (e.g. CC0), 4: Openverse source URL. */
			__( 'Photograph: %1$s by %2$s (%3$s), via Openverse: %4$s', 'ttm-core' ),
			(string) ( $row['title'] ?? '' ),
			(string) ( $row['creator'] ?? '' ),
			strtoupper( (string) ( $row['license'] ?? '' ) ),
			(string) ( $row['foreign_landing_url'] ?? '' )
		);
	}

	/**
	 * Sideload `$file` from `dir()` onto `$post_id`, with `$alt` as the attachment title and
	 * alt text, `$caption` as `post_excerpt`, and the rule 53 credit line as `post_content`.
	 *
	 * @param string $file    Demo image file name (`is_demo_name()`).
	 * @param int    $post_id Post to attach to.
	 * @param string $alt     Alt text / attachment title.
	 * @param string $caption Attachment caption (`post_excerpt`).
	 * @return int Attachment id, or 0 when the name is invalid, the file is missing, or the
	 *             sideload fails.
	 */
	public static function attach( string $file, int $post_id, string $alt, string $caption ): int {
		if ( ! self::is_demo_name( $file ) ) {
			return 0;
		}

		$source = self::dir() . '/' . $file;
		if ( ! is_file( $source ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( $file );
		copy( $source, $tmp ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_copy -- copying a local fixture into the tempnam wp_tempnam() just created, for media_handle_sideload().

		$credit_row = self::credits()[ $file ] ?? [];

		$attachment_id = media_handle_sideload(
			[
				'name'     => $file,
				'tmp_name' => $tmp,
			],
			$post_id,
			null,
			[
				'post_title'   => $alt,
				'post_excerpt' => $caption,
				'post_content' => self::credit_line( $credit_row ),
				'post_date'    => Clock::now()->format( 'Y-m-d H:i:s' ),
			]
		);

		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		$attachment_id = (int) $attachment_id;
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		update_post_meta( $attachment_id, '_ttm_seed', 1 );

		return $attachment_id;
	}
}
