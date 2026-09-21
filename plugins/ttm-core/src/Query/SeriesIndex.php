<?php
/**
 * Computes and stores the `ttm_series_index` option (SPEC §5.3).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Support\Clock;
use WP_Post;

/**
 * One row per `series` term; rebuilt (debounced, once per request) on every write hook.
 */
class SeriesIndex {

	private const OPTION = 'ttm_series_index';

	/**
	 * Debounce flag: rebuild once per request, on shutdown.
	 *
	 * @var bool
	 */
	private static bool $scheduled = false;

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'save_post_post', [ self::class, 'schedule_rebuild' ], 40 );
		add_action( 'deleted_post', [ self::class, 'schedule_rebuild' ] );
		add_action( 'transition_post_status', [ self::class, 'on_transition' ], 10, 3 );
		add_action( 'created_series', [ self::class, 'schedule_rebuild' ] );
		add_action( 'edited_series', [ self::class, 'schedule_rebuild' ] );
		add_action( 'delete_series', [ self::class, 'schedule_rebuild' ] );
	}

	/**
	 * Transition_post_status callback: only reschedule for `post`.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		unset( $new_status, $old_status );
		if ( 'post' === $post->post_type ) {
			self::schedule_rebuild();
		}
	}

	/**
	 * Flag a rebuild for `shutdown`, once per request.
	 */
	public static function schedule_rebuild(): void {
		if ( self::$scheduled ) {
			return;
		}
		self::$scheduled = true;
		add_action( 'shutdown', [ self::class, 'maybe_rebuild' ] );
	}

	/**
	 * Shutdown callback.
	 */
	public static function maybe_rebuild(): void {
		self::rebuild();
	}

	/**
	 * Rebuild the index immediately and store it.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function rebuild(): array {
		self::$scheduled = false;

		$rows = [];
		foreach ( get_terms(
			[
				'taxonomy'   => 'series',
				'hide_empty' => false,
			] 
		) as $term ) {
			$rows[] = self::build_row( $term );
		}

		$before = get_option( self::OPTION, [] );
		$rows   = apply_filters( self::OPTION, $rows );

		if ( wp_json_encode( $rows ) !== wp_json_encode( $before ) ) {
			update_option( self::OPTION, $rows, false );
			do_action( 'ttm_purge_urls', [ home_url( '/' ), home_url( '/series/' ) ] );
		}

		return $rows;
	}

	/**
	 * Build one row for a series term.
	 *
	 * @param \WP_Term $term Series term.
	 * @return array<string, mixed>
	 */
	private static function build_row( \WP_Term $term ): array {
		$parts = self::collect_parts( $term->term_id );

		usort(
			$parts,
			static function ( array $a, array $b ): int {
				return 0 !== ( $a['part'] <=> $b['part'] ) ? ( $a['part'] <=> $b['part'] ) : strcmp( $a['date'], $b['date'] );
			}
		);

		$published  = count( array_filter( $parts, static fn ( array $p ): bool => 'publish' === $p['status'] ) );
		$term_total = (int) get_term_meta( $term->term_id, 'ttm_total_parts', true );
		$status     = (string) get_term_meta( $term->term_id, 'ttm_status', true );
		$form       = (string) get_term_meta( $term->term_id, 'ttm_form', true );

		return [
			'id'             => $term->term_id,
			'slug'           => $term->slug,
			'name'           => $term->name,
			'status'         => '' !== $status ? $status : 'in-progress',
			'form'           => '' !== $form ? $form : 'nonfiction',
			'published'      => $published,
			'total'          => $term_total > 0 ? $term_total : $published,
			'categories'     => self::categories_for( $parts ),
			'last_update'    => self::last_update_for( $parts ),
			'next_date'      => get_term_meta( $term->term_id, 'ttm_next_date', true ),
			'first_post_id'  => $parts[0]['post_id'] ?? 0,
			'latest_post_id' => end( $parts )['post_id'] ?? 0,
			'parts'          => $parts,
		];
	}

	/**
	 * Query every post in the series term across all non-trash statuses, in batches.
	 *
	 * @param int $term_id Series term id.
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_parts( int $term_id ): array {
		$batch = (int) Config::get( 'series.index_batch', 500 );
		$parts = [];
		$paged = 1;

		do {
			$query = new \WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
					'no_found_rows'  => false,
					'tax_query'      => [
						[
							'taxonomy' => 'series',
							'terms'    => [ $term_id ],
						],
					],
				]
			);

			foreach ( $query->posts as $post_id ) {
				$post = get_post( (int) $post_id );
				if ( ! $post ) {
					continue;
				}
				$title   = get_post_meta( $post->ID, 'ttm_part_title', true );
				$parts[] = [
					'post_id' => $post->ID,
					'part'    => (int) get_post_meta( $post->ID, 'ttm_series_part', true ),
					'title'   => '' !== $title ? $title : $post->post_title,
					'status'  => $post->post_status,
					'date'    => $post->post_date,
				];
			}

			++$paged;
			$found = count( $query->posts );
		} while ( $found === $batch );

		return $parts;
	}

	/**
	 * Distinct primary-category ids of published parts, ordered by nav order.
	 *
	 * @param array<int, array<string, mixed>> $parts Parts.
	 * @return int[]
	 */
	private static function categories_for( array $parts ): array {
		$order   = (array) Config::get( 'sections.order', [] );
		$by_slug = [];

		foreach ( $parts as $part ) {
			if ( 'publish' !== $part['status'] ) {
				continue;
			}
			$id = PrimaryCategory::id( $part['post_id'] );
			if ( ! $id ) {
				continue;
			}
			$slug             = PrimaryCategory::slug( $part['post_id'] );
			$by_slug[ $slug ] = $id;
		}

		$sorted = [];
		foreach ( $order as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$sorted[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}

		return array_values( array_merge( $sorted, array_values( $by_slug ) ) );
	}

	/**
	 * The newest published part's `post_date`, so `last_update` is stable across rebuilds
	 * (R1-03: previously stamped `Clock::now()` on every rebuild, which made "sorted by
	 * update" meaningless -- every row tied at the current rebuild's timestamp). A series
	 * with no published part yet uses its newest part of any status; a series with no parts
	 * at all falls back to now.
	 *
	 * @param array<int, array<string, mixed>> $parts Parts.
	 * @return string
	 */
	private static function last_update_for( array $parts ): string {
		$published = array_filter( $parts, static fn ( array $part ): bool => 'publish' === $part['status'] );
		$pool      = ! empty( $published ) ? $published : $parts;

		if ( empty( $pool ) ) {
			return Clock::now()->format( 'Y-m-d H:i:s' );
		}

		$dates = array_column( $pool, 'date' );
		sort( $dates );

		return (string) end( $dates );
	}

	/**
	 * All rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		return get_option( self::OPTION, [] );
	}

	/**
	 * A row by series term id.
	 *
	 * @param int $term_id Series term id.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $term_id ): ?array {
		foreach ( self::all() as $row ) {
			if ( (int) $row['id'] === $term_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * A row by series slug.
	 *
	 * @param string $slug Series slug.
	 * @return array<string, mixed>|null
	 */
	public static function by_slug( string $slug ): ?array {
		foreach ( self::all() as $row ) {
			if ( $row['slug'] === $slug ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * The row containing a given post id, if any.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, mixed>|null
	 */
	public static function for_post( int $post_id ): ?array {
		foreach ( self::all() as $row ) {
			foreach ( $row['parts'] as $part ) {
				if ( (int) $part['post_id'] === $post_id ) {
					return $row;
				}
			}
		}

		return null;
	}

	/**
	 * Sort rows by last_update, newest first.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sorted_by_update( array $rows ): array {
		usort( $rows, static fn ( array $a, array $b ): int => strcmp( $b['last_update'], $a['last_update'] ) );

		return $rows;
	}
}
