<?php
/**
 * Opens comments on three seeded posts that no other e2e screen uses and gives each a set of
 * comments (`npm run test:e2e` runs it after `wp ttm seed --reset`; idempotent). Every name and
 * address is synthetic.
 *
 * - php-85-readonly-classes: a busy Discussion, with a thread three deep and an author reply;
 * - journal-post-3: seven Reflections;
 * - quiet-ledger-ch-2: Reader notes, one answered.
 *
 * Run: wp eval-file wp-content/ttm-tests/e2e/fixtures/comments.php
 *
 * @package TTM\Tests\E2E
 */

declare( strict_types=1 );

foreach (
	[
		'comment_registration'         => 0,
		'show_avatars'                 => 0,
		'thread_comments'              => 1,
		'close_comments_for_old_posts' => 0,
		'page_comments'                => 0,
		'comment_order'                => 'asc',
	] as $ttm_option => $ttm_value
) {
	update_option( $ttm_option, $ttm_value );
}

$ttm_post = static function ( string $slug ): int {
	$found = get_page_by_path( $slug, OBJECT, 'post' );
	if ( ! $found ) {
		WP_CLI::error( "No seeded post {$slug}" );
	}
	wp_update_post(
		[
			'ID'             => $found->ID,
			'comment_status' => 'open',
		]
	);
	foreach ( get_comments(
		[
			'post_id' => $found->ID,
			'status'  => 'all',
			'fields'  => 'ids',
		] 
	) as $ttm_old ) {
		wp_delete_comment( (int) $ttm_old, true );
	}
	return (int) $found->ID;
};

$ttm_comment = static function ( int $post_id, string $name, string $text, int $parent_id = 0, int $user_id = 0 ): int {
	return (int) wp_insert_comment(
		[
			'comment_post_ID'      => $post_id,
			'comment_author'       => $name,
			'comment_author_email' => sanitize_title( $name ) . '@example.test',
			'comment_content'      => $text,
			'comment_parent'       => $parent_id,
			'user_id'              => $user_id,
			'comment_approved'     => 1,
		]
	);
};

$ttm_article = $ttm_post( 'php-85-readonly-classes' );
$ttm_author  = (int) get_post_field( 'post_author', $ttm_article );
$ttm_first   = $ttm_comment( $ttm_article, 'Dana Reyes', 'Readonly classes finally made our value objects honest. The clone-with dance is still awkward, though.' );
$ttm_answer  = $ttm_comment( $ttm_article, (string) get_the_author_meta( 'display_name', $ttm_author ), 'Agreed on cloning. A withers helper on the base class has been the least bad option so far.', $ttm_first, $ttm_author );
$ttm_comment( $ttm_article, 'Dana Reyes', 'That is roughly where we landed too.', $ttm_answer );
$ttm_comment( $ttm_article, 'Sam Okafor', 'Did static analysis catch the property writes for you before runtime did?', $ttm_first );
foreach ( range( 1, 6 ) as $ttm_i ) {
	$ttm_comment( $ttm_article, "Reader {$ttm_i}", "A short note from reader {$ttm_i} about readonly classes and the upgrade path." );
}

$ttm_journal = $ttm_post( 'journal-post-3' );
foreach ( range( 1, 7 ) as $ttm_i ) {
	$ttm_comment( $ttm_journal, "Walker {$ttm_i}", "Reflection {$ttm_i}: this one stayed with me." );
}

$ttm_chapter = $ttm_post( 'quiet-ledger-ch-2' );
$ttm_note    = $ttm_comment( $ttm_chapter, 'Ivy Chen', 'The ledger entry at the end of this chapter gave me chills.' );
$ttm_comment( $ttm_chapter, 'Marco Ruiz', 'Same. I went back to chapter one to find where it was set up.', $ttm_note );
$ttm_comment( $ttm_chapter, 'Ana Silva', 'Loving the pacing.' );

WP_CLI::success( 'Comments seeded.' );
