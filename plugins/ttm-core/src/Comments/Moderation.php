<?php
/**
 * Who skips the moderation queue: a commenter with enough approved comments, never on fiction.
 *
 * @package TTM\Core\Comments
 */

declare( strict_types=1 );

namespace TTM\Core\Comments;

use TTM\Core\Config;

/**
 * Runs after core's checks and Akismet (priority 20 on `pre_comment_approved`) and only ever
 * holds a comment they would have approved; spam, trash and held comments pass through.
 */
class Moderation {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'pre_comment_approved', [ self::class, 'filter_approved' ], 20, 2 );
		add_filter( 'akismet_comment_nonce', [ self::class, 'akismet_nonce' ] );
	}

	/**
	 * Pure: whether an otherwise approved comment publishes without review.
	 *
	 * @param string $kind      Threading::kind() of the post.
	 * @param bool   $moderator The commenter is logged in and can moderate comments.
	 * @param int    $approved  The commenter's approved comments so far.
	 * @param int    $threshold `comments.trusted_after`.
	 */
	public static function publishes( string $kind, bool $moderator, int $approved, int $threshold ): bool {
		if ( $moderator ) {
			return true;
		}

		return 'notes' !== $kind && $threshold > 0 && $approved >= $threshold;
	}

	/**
	 * `pre_comment_approved`: hold an approved comment unless publishes() says otherwise.
	 *
	 * @param int|string|\WP_Error $approved     1, 0, 'spam', 'trash' or an error.
	 * @param array<string, mixed> $comment_data Comment data.
	 * @return int|string|\WP_Error
	 */
	public static function filter_approved( $approved, array $comment_data ) {
		if ( 1 !== $approved && '1' !== $approved ) {
			return $approved;
		}

		$user_id = (int) ( $comment_data['user_id'] ?? 0 );
		$email   = (string) ( $comment_data['comment_author_email'] ?? '' );
		$count   = '' === $email ? 0 : (int) get_comments(
			[
				'author_email' => $email,
				'status'       => 'approve',
				'type'         => 'comment',
				'count'        => true,
			]
		);

		$publishes = self::publishes(
			Threading::kind_of( (int) ( $comment_data['comment_post_ID'] ?? 0 ) ),
			$user_id > 0 && user_can( $user_id, 'moderate_comments' ),
			$count,
			(int) Config::get( 'comments.trusted_after', 3 )
		);

		return $publishes ? $approved : 0;
	}

	/**
	 * Akismet's form nonce would be cached into every copy of the page and go stale there, so it
	 * is turned off ('inactive', not false: false means "use the stored option").
	 */
	public static function akismet_nonce(): string {
		return 'inactive';
	}
}
