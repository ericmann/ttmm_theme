<?php
/**
 * Unit tests for TTM\Core\Comments\Moderation::publishes().
 *
 * @package TTM\Tests\Unit\Comments
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Comments;

use TTM\Core\Comments\Moderation;
use TTM\Tests\Unit\TestCase;

class ModerationTest extends TestCase {

	public function test_new_commenters_wait_for_review(): void {
		$this->assertFalse( Moderation::publishes( 'discussion', false, 0, 3 ) );
		$this->assertFalse( Moderation::publishes( 'discussion', false, 2, 3 ) );
	}

	public function test_trusted_commenters_publish_straight_away(): void {
		$this->assertTrue( Moderation::publishes( 'discussion', false, 3, 3 ) );
		$this->assertTrue( Moderation::publishes( 'reflections', false, 7, 3 ) );
	}

	public function test_fiction_notes_are_always_held(): void {
		$this->assertFalse( Moderation::publishes( 'notes', false, 50, 3 ) );
	}

	public function test_a_zero_threshold_holds_everyone(): void {
		$this->assertFalse( Moderation::publishes( 'discussion', false, 50, 0 ) );
	}

	public function test_moderators_always_publish(): void {
		$this->assertTrue( Moderation::publishes( 'notes', true, 0, 3 ) );
	}

	public function test_only_an_approval_is_ever_downgraded(): void {
		foreach ( [ 0, '0', 'spam', 'trash' ] as $approved ) {
			$this->assertSame( $approved, Moderation::filter_approved( $approved, [] ) );
		}
	}
}
