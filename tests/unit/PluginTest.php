<?php
/**
 * Unit tests for TTM\Core\Plugin.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

use Brain\Monkey\Actions;
use TTM\Core\Compat\Theme;
use TTM\Core\Plugin;

class PluginTest extends TestCase {

	protected function tearDown(): void {
		Plugin::reset();
		parent::tearDown();
	}

	public function test_modules_list_starts_with_compat_theme(): void {
		$this->assertSame( Theme::class, Plugin::modules()[0] );
	}

	public function test_boot_registers_each_module_once(): void {
		// Compat\Theme (API-version notice) and Admin\BuildNotice (P1-09, §6.7 editor-bundle
		// notice) each hook admin_notices once.
		Actions\expectAdded( 'admin_notices' )->twice();

		Plugin::boot();
		Plugin::boot();

		$this->assertTrue( true, 'admin_notices expectation verified in tearDown()' );
	}
}
