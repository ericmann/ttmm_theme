<?php
/**
 * Front-page lead selection and cache (SPEC 03 §7, §5.3, F22).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Dates;
use WP_Post;
use WP_Query;

/**
 * `{id, reason}`: `sticky`, `technology`, `sitewide`, or `none`. Cached in the `ttm_lead_id`
 * transient, flushed on any post's publish transition.
 */
class Lead {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'transition_post_status', [ self::class, 'on_transition' ], 10, 3 );
	}

	/**
	 * Flush the cache when a post enters or leaves `publish`.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'post' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		delete_transient( 'ttm_lead_id' );
	}

	/**
	 * The cached lead id.
	 *
	 * @return int
	 */
	public static function id(): int {
		return self::compute()['id'];
	}

	/**
	 * `{id, reason}`, cached in the `ttm_lead_id` transient for `lead.cache_seconds`.
	 *
	 * @return array{id: int, reason: string}
	 */
	public static function compute(): array {
		$cached = get_transient( 'ttm_lead_id' );
		if ( is_array( $cached ) && isset( $cached['id'], $cached['reason'] ) ) {
			return $cached;
		}

		$result = self::select();

		set_transient( 'ttm_lead_id', $result, (int) Config::get( 'lead.cache_seconds', 300 ) );

		return $result;
	}

	/**
	 * The uncached selection (SPEC 03 §7 / F22).
	 *
	 * @return array{id: int, reason: string}
	 */
	private static function select(): array {
		$sticky = self::sticky_candidate();
		if ( $sticky ) {
			return self::finalize( $sticky, 'sticky' );
		}

		$technology = self::technology_candidate();
		if ( $technology && self::is_within_days( $technology, (int) Config::get( 'lead.stale_days', 30 ) ) ) {
			return self::finalize( $technology->ID, 'technology' );
		}

		$sitewide = self::sitewide_candidate();
		if ( $sitewide ) {
			return self::finalize( $sitewide->ID, 'sitewide' );
		}

		return self::finalize( 0, 'none' );
	}

	/**
	 * The newest sticky, published post within `lead.sticky_days`.
	 *
	 * @return int Post id, or 0.
	 */
	private static function sticky_candidate(): int {
		$sticky_ids = get_option( 'sticky_posts' );
		if ( ! is_array( $sticky_ids ) || empty( $sticky_ids ) ) {
			return 0;
		}

		$window = (int) Config::get( 'lead.sticky_days', 30 );

		$query = new WP_Query(
			[
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'post__in'            => array_map( 'absint', $sticky_ids ),
				'orderby'             => 'date',
				'order'               => 'DESC',
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => 1,
			]
		);

		$post = $query->posts[0] ?? null;

		if ( $post instanceof WP_Post && self::is_within_days( $post, $window ) ) {
			return $post->ID;
		}

		return 0;
	}

	/**
	 * The newest published post whose primary category is Technology.
	 *
	 * @return WP_Post|null
	 */
	private static function technology_candidate(): ?WP_Post {
		$term = get_term_by( 'slug', (string) Config::get( 'sections.technology_slug', 'technology' ), 'category' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$query = new WP_Query(
			[
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'meta_key'            => 'ttm_primary_category',
				'meta_value'          => $term->term_id,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => 1,
			]
		);

		return $query->posts[0] ?? null;
	}

	/**
	 * The newest published post whose primary category is not Journal.
	 *
	 * @return WP_Post|null
	 */
	private static function sitewide_candidate(): ?WP_Post {
		$journal_id = self::journal_term_id();

		$args = [
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'orderby'             => 'date',
			'order'               => 'DESC',
			'posts_per_page'      => 1,
			'ignore_sticky_posts' => 1,
		];

		if ( $journal_id ) {
			$args['category__not_in'] = [ $journal_id ];
		}

		$query = new WP_Query( $args );

		return $query->posts[0] ?? null;
	}

	/**
	 * Whether a post's date is within `$days` of Clock::now().
	 *
	 * @param WP_Post $post Post.
	 * @param int     $days Window, in days.
	 * @return bool
	 */
	private static function is_within_days( WP_Post $post, int $days ): bool {
		$posted = Clock::at( $post->post_date );
		if ( ! $posted ) {
			return false;
		}

		$age = Dates::days_between( $posted, Clock::now() );

		return $age >= 0 && $age <= $days;
	}

	/**
	 * The Journal category's term id, or 0.
	 *
	 * @return int
	 */
	private static function journal_term_id(): int {
		$journal = get_term_by( 'slug', (string) Config::get( 'sections.journal_slug', 'journal' ), 'category' );

		return $journal && ! is_wp_error( $journal ) ? $journal->term_id : 0;
	}

	/**
	 * Apply the `ttm_lead_post_id` filter to a computed id, and shape the result.
	 *
	 * @param int    $id     Selected post id.
	 * @param string $reason Selection reason.
	 * @return array{id: int, reason: string}
	 */
	private static function finalize( int $id, string $reason ): array {
		return [
			'id'     => (int) apply_filters( 'ttm_lead_post_id', $id, $reason ),
			'reason' => $reason,
		];
	}
}
