<?php
/**
 * Unit tests for TTM\Core\Meta\Layout::resolve().
 *
 * @package TTM\Tests\Unit\Meta
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Meta;

use TTM\Core\Meta\Layout;
use TTM\Tests\Unit\TestCase;

class LayoutTest extends TestCase {

	public function test_journal_entries_default_to_the_journal_template(): void {
		$this->assertSame( 'single-journal', Layout::resolve( '', 'journal' ) );
	}

	public function test_any_post_can_pick_the_journal_template(): void {
		$this->assertSame( 'single-journal', Layout::resolve( 'single-journal', 'technology' ) );
	}

	public function test_everything_else_is_single(): void {
		$this->assertSame( 'single', Layout::resolve( '', 'technology' ) );
		$this->assertSame( 'single', Layout::resolve( '', '' ) );
		$this->assertSame( 'single', Layout::resolve( 'some-unknown-template', 'business' ) );
	}
}
