<?php
/**
 * Unit tests for TTM\Core\Cli\DemoImage::is_demo_name() (pure; no WordPress calls).
 *
 * @package TTM\Tests\Unit\Cli
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Cli;

use TTM\Core\Cli\DemoImage;
use TTM\Tests\Unit\TestCase;

class DemoImageTest extends TestCase {

	public function test_is_demo_name_accepts_demo_slugs(): void {
		$this->assertTrue( DemoImage::is_demo_name( 'demo-about.jpg' ) );
		$this->assertTrue( DemoImage::is_demo_name( 'demo-signing-your-options-table.jpg' ) );
		$this->assertTrue( DemoImage::is_demo_name( 'demo-a1-b2.jpg' ) );
	}

	public function test_is_demo_name_rejects_paths_case_and_other_extensions(): void {
		$this->assertFalse( DemoImage::is_demo_name( '../demo-x.jpg' ) );
		$this->assertFalse( DemoImage::is_demo_name( 'demo-X.jpg' ) );
		$this->assertFalse( DemoImage::is_demo_name( 'demo-x.JPG' ) );
		$this->assertFalse( DemoImage::is_demo_name( 'demo-x.png' ) );
		$this->assertFalse( DemoImage::is_demo_name( 'photo.jpg' ) );
	}
}
