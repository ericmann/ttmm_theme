<?php
/**
 * Deterministic seed content library, fed from docs/fixtures/seed/*.json.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * Seeds categories, pages, navigation, posts and images; idempotent by slug.
 */
class Seeder {

	private const SEED_META = '_ttm_seed';

	/**
	 * The fixtures directory: the wp-env mapping if present, else the repo path directly.
	 *
	 * @return string
	 */
	public static function fixtures_dir(): string {
		$mapped = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/ttm-fixtures/seed' : '';
		if ( $mapped && is_dir( $mapped ) ) {
			return $mapped;
		}

		return dirname( TTM_CORE_DIR, 3 ) . '/docs/fixtures/seed';
	}

	/**
	 * Load and decode a fixture JSON file.
	 *
	 * @param string $name File name, e.g. "posts.json".
	 * @return array<int, array<string, mixed>>
	 */
	private function load( string $name ): array {
		$path = self::fixtures_dir() . '/' . $name;
		if ( ! file_exists( $path ) ) {
			return [];
		}

		return (array) json_decode( (string) file_get_contents( $path ), true );
	}

	/**
	 * Run the full seed for a given state. Only "normal" varies fixture selection today.
	 *
	 * @param string $state Seed state (e.g. "normal", "quiet", "empty").
	 * @return array{categories:int, pages:int, posts:int, navigation:int}
	 */
	public function run( string $state ): array {
		unset( $state );

		$categories = $this->seed_categories();
		$pages      = $this->seed_pages();
		$navigation = $this->seed_navigation();
		$posts      = $this->seed_posts();

		return [
			'categories' => count( $categories ),
			'pages'      => count( $pages ),
			'posts'      => count( $posts ),
			'navigation' => $navigation ? 1 : 0,
		];
	}

	/**
	 * Create the seven sections plus politics (child of opinion). Idempotent by slug.
	 *
	 * @return int[] Term ids, keyed by slug.
	 */
	public function seed_categories(): array {
		$rows = $this->load( 'categories.json' );
		$ids  = [];

		// Two passes: top-level first, so `parent` slugs resolve.
		foreach ( $rows as $row ) {
			if ( ! empty( $row['parent'] ) ) {
				continue;
			}
			$ids[ $row['slug'] ] = $this->upsert_category( $row, $ids );
		}
		foreach ( $rows as $row ) {
			if ( empty( $row['parent'] ) ) {
				continue;
			}
			$ids[ $row['slug'] ] = $this->upsert_category( $row, $ids );
		}

		return $ids;
	}

	/**
	 * Create or reuse one category term.
	 *
	 * @param array<string, mixed> $row Fixture row.
	 * @param int[]                $ids Slugs resolved so far, for `parent`.
	 * @return int
	 */
	private function upsert_category( array $row, array $ids ): int {
		$existing = get_term_by( 'slug', $row['slug'], 'category' );
		if ( $existing ) {
			return (int) $existing->term_id;
		}

		$args = [ 'description' => $row['description'] ?? '' ];
		if ( ! empty( $row['parent'] ) && isset( $ids[ $row['parent'] ] ) ) {
			$args['parent'] = $ids[ $row['parent'] ];
		}

		$created = wp_insert_term( $row['name'], 'category', array_merge( $args, [ 'slug' => $row['slug'] ] ) );
		$term_id = (int) $created['term_id'];
		update_term_meta( $term_id, self::SEED_META, 1 );

		return $term_id;
	}

	/**
	 * Create the four pages. Idempotent by slug.
	 *
	 * @return int[] Post ids.
	 */
	public function seed_pages(): array {
		$rows = $this->load( 'pages.json' );
		$ids  = [];

		foreach ( $rows as $row ) {
			$existing = get_page_by_path( $row['slug'], OBJECT, 'page' );
			if ( $existing ) {
				$ids[] = $existing->ID;
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $row['slug'],
					'post_title'   => $row['title'],
					'post_content' => $row['content'],
				]
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_wp_page_template', $row['template'] . '.html' );
				update_post_meta( $post_id, self::SEED_META, 1 );
				$ids[] = $post_id;
			}
		}//end foreach

		return $ids;
	}

	/**
	 * Create the "Sections" navigation menu. Idempotent by title.
	 *
	 * @return int 0 if it already existed or nothing to create.
	 */
	public function seed_navigation(): int {
		$existing = get_posts(
			[
				'post_type'      => 'wp_navigation',
				'title'          => 'Sections',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $existing ) ) {
			return 0;
		}

		$order = (array) Config::get( 'sections.order', [] );
		$links = '';
		foreach ( $order as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$term_link = get_term_link( $term );
			if ( is_wp_error( $term_link ) ) {
				continue;
			}
			$links .= sprintf(
				'<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->',
				esc_attr( $term->name ),
				esc_url( $term_link )
			);
		}
		$links .= '<!-- wp:navigation-link {"label":"Series","url":"' . esc_url( home_url( '/series/' ) ) . '","kind":"custom"} /-->';

		$post_id = wp_insert_post(
			[
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Sections',
				'post_content' => $links,
			]
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, self::SEED_META, 1 );
			return $post_id;
		}

		return 0;
	}

	/**
	 * Create the seeded posts. Idempotent by slug.
	 *
	 * @return int[] Post ids.
	 */
	public function seed_posts(): array {
		$rows = $this->load( 'posts.json' );
		$ids  = [];

		foreach ( $rows as $row ) {
			$existing = get_page_by_path( $row['slug'], OBJECT, 'post' );
			if ( $existing ) {
				$ids[] = $existing->ID;
				continue;
			}

			$date = Clock::now()->modify( '-' . (int) $row['days_ago'] . ' days' )->format( 'Y-m-d H:i:s' );

			$post_id = wp_insert_post(
				[
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_name'     => $row['slug'],
					'post_title'    => $row['title'],
					'post_content'  => $row['content'],
					'post_excerpt'  => $row['excerpt'] ?? '',
					'post_date'     => $date,
					'post_date_gmt' => get_gmt_from_date( $date ),
					'tags_input'    => $row['tags'] ?? [],
				]
			);

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			$category_ids = [];
			foreach ( $row['categories'] as $slug ) {
				$term = get_term_by( 'slug', $slug, 'category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$category_ids[] = $term->term_id;
				}
			}
			if ( $category_ids ) {
				wp_set_post_categories( $post_id, $category_ids );
			}

			if ( ! empty( $row['featured_image'] ) ) {
				$attachment_id = $this->image( $row['title'], 'ttm-tile' );
				if ( $attachment_id ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}

			if ( ! empty( $row['most_read'] ) ) {
				update_post_meta( $post_id, 'ttm_featured_in_section', true );
			}
			if ( ! empty( $row['syndication'] ) ) {
				update_post_meta( $post_id, 'ttm_syndication', $row['syndication'] );
			}
			if ( ! empty( $row['location'] ) ) {
				update_post_meta( $post_id, 'ttm_location', $row['location'] );
			}

			update_post_meta( $post_id, self::SEED_META, 1 );
			$ids[] = $post_id;
		}//end foreach

		return $ids;
	}

	/**
	 * Generate a solid-colour PNG at the given images.sizes dimensions and attach it.
	 *
	 * @param string $label    Alt text / file base name.
	 * @param string $size_key Key into Config images.sizes.
	 * @return int Attachment id, or 0 when GD is unavailable.
	 */
	public function image( string $label, string $size_key ): int {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$sizes              = (array) Config::get( 'images.sizes', [] );
		[ $width, $height ] = $sizes[ $size_key ] ?? [ 800, 600, true ];

		$image = imagecreatetruecolor( $width, $height );
		$color = imagecolorallocate( $image, 210, 48, 19 );
		imagefill( $image, 0, 0, $color );

		ob_start();
		imagepng( $image );
		$data = ob_get_clean();
		imagedestroy( $image );

		$filename = sanitize_title( $label ) . '.png';
		$upload   = wp_upload_bits( $filename, null, $data );
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => 'image/png',
				'post_title'     => $label,
				'post_status'    => 'inherit',
			],
			$upload['file']
		);

		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $label );
		update_post_meta( $attachment_id, self::SEED_META, 1 );

		return (int) $attachment_id;
	}

	/**
	 * Delete every object carrying the seed meta.
	 */
	public function reset(): void {
		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::SEED_META ) );
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		$term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s", self::SEED_META ) );
		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id );
			if ( $term && ! is_wp_error( $term ) ) {
				wp_delete_term( (int) $term_id, $term->taxonomy );
			}
		}
	}
}
