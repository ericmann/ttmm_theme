<?php
/**
 * When comments close: reader notes on fiction after a month, reflections on a journal entry
 * after two weeks, a discussion after a year.
 *
 * @package TTM\Core\Comments
 */

declare( strict_types=1 );

namespace TTM\Core\Comments;

use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * Replaces core's single "close comments on posts older than N days" setting with one window per
 * kind (Threading::kind()). Core's checkbox is held off so the two never stack; the windows are
 * `comments.close_days_notes`, `comments.close_days_reflections` and `comments.close_days`.
 */
class Closing {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'pre_option_close_comments_for_old_posts', [ self::class, 'core_closing_off' ] );
		add_filter( 'comments_open', [ self::class, 'filter_open' ], 20, 2 );
	}

	/**
	 * Core's own age check stays off; filter_open() does it per kind.
	 */
	public static function core_closing_off(): string {
		return '0';
	}

	/**
	 * Pure: days a kind stays open (0 = never closes).
	 *
	 * @param string $kind A Threading::kind() value.
	 */
	public static function days( string $kind ): int {
		if ( 'notes' === $kind ) {
			return (int) Config::get( 'comments.close_days_notes', 30 );
		}

		if ( 'reflections' === $kind ) {
			return (int) Config::get( 'comments.close_days_reflections', 14 );
		}

		return (int) Config::get( 'comments.close_days', 365 );
	}

	/**
	 * Pure: whether a post published at `$published` has closed by `$now`.
	 *
	 * @param int $published Publish time, Unix seconds.
	 * @param int $now       Now, Unix seconds.
	 * @param int $days      days() for the post's kind.
	 */
	public static function has_closed( int $published, int $now, int $days ): bool {
		return $days > 0 && $now - $published > $days * DAY_IN_SECONDS;
	}

	/**
	 * `comments_open`: close a published post's comments once its kind's window has passed.
	 *
	 * @param bool         $open    Whether comments are open.
	 * @param int|\WP_Post $post_id Post id or object.
	 */
	public static function filter_open( $open, $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $open || ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return (bool) $open;
		}

		$published = get_post_timestamp( $post );
		if ( false === $published ) {
			return true;
		}

		return ! self::has_closed( $published, Clock::now()->getTimestamp(), self::days( Threading::kind_of( (int) $post->ID ) ) );
	}
}
