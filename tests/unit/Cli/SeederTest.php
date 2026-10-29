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
	 * P2-01, rule 49: destructive seed operations are allowed on local and development sites
	 * only; staging (the beta restored from the live database) and production are refused.
	 */
	public function test_may_wipe_only_on_local_and_development(): void {
		$this->assertTrue( Seeder::may_wipe( 'local' ) );
		$this->assertTrue( Seeder::may_wipe( 'development' ) );
		$this->assertFalse( Seeder::may_wipe( 'staging' ) );
		$this->assertFalse( Seeder::may_wipe( 'production' ) );
		$this->assertFalse( Seeder::may_wipe( '' ) );
	}
}
