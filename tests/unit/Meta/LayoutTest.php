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

	public function test_a_journal_entry_can_opt_out_with_article(): void {
		$this->assertSame( 'single-article', Layout::resolve( 'single-article', 'journal' ) );
	}

	public function test_any_post_can_pick_the_photo_hero(): void {
		$this->assertSame( 'single-feature', Layout::resolve( 'single-feature', 'technology' ) );
	}

	public function test_hero_layouts(): void {
		$this->assertTrue( Layout::has_hero( 'single-journal' ) );
		$this->assertTrue( Layout::has_hero( 'single-feature' ) );
		$this->assertFalse( Layout::has_hero( 'single-article' ) );
		$this->assertFalse( Layout::has_hero( 'single' ) );
	}
}
