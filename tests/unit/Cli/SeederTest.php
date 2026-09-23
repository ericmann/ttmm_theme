<?php
/**
 * Unit tests for TTM\Core\Cli\Seeder::normalize_form() (pure; no WordPress calls).
 *
 * @package TTM\Tests\Unit\Cli
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Cli;

use TTM\Core\Cli\Seeder;
use TTM\Tests\Unit\TestCase;

class SeederTest extends TestCase {

	public function test_normalize_form_accepts_allowed_values(): void {
		$this->assertSame( 'article', Seeder::normalize_form( 'article' ) );
		$this->assertSame( 'chapter', Seeder::normalize_form( 'chapter' ) );
		$this->assertSame( 'story', Seeder::normalize_form( 'story' ) );
	}

	public function test_normalize_form_rejects_unknown_or_missing_values(): void {
		$this->assertNull( Seeder::normalize_form( 'essay' ) );
		$this->assertNull( Seeder::normalize_form( '' ) );
		$this->assertNull( Seeder::normalize_form( null ) );
		$this->assertNull( Seeder::normalize_form( 1 ) );
	}

	/**
	 * P2-01, rule 49: destructive seed operations are refused on production only.
	 */
	public function test_may_wipe_is_false_only_on_production(): void {
		$this->assertFalse( Seeder::may_wipe( 'production' ) );
		$this->assertTrue( Seeder::may_wipe( 'local' ) );
		$this->assertTrue( Seeder::may_wipe( 'staging' ) );
		$this->assertTrue( Seeder::may_wipe( 'development' ) );
	}
}
