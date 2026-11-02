<?php
/**
 * Unit tests for TTM\Core\Comments\Threading's pure decisions.
 *
 * @package TTM\Tests\Unit\Comments
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Comments;

use TTM\Core\Comments\Threading;
use TTM\Tests\Unit\TestCase;

class ThreadingTest extends TestCase {

	public function test_journal_section_collects_reflections_whatever_the_form(): void {
		$this->assertSame( 'reflections', Threading::kind( 'journal', 'article' ) );
		$this->assertSame( 'reflections', Threading::kind( 'journal', 'chapter' ) );
	}

	public function test_fiction_forms_gather_notes(): void {
		$this->assertSame( 'notes', Threading::kind( 'writing', 'chapter' ) );
		$this->assertSame( 'notes', Threading::kind( 'writing', 'story' ) );
		$this->assertSame( 'notes', Threading::kind( '', 'chapter' ) );
	}

	public function test_everything_else_holds_a_discussion(): void {
		$this->assertSame( 'discussion', Threading::kind( 'technology', 'article' ) );
		$this->assertSame( 'discussion', Threading::kind( '', '' ) );
	}

	public function test_depth_by_kind(): void {
		$this->assertSame( 3, Threading::depth( 'discussion' ) );
		$this->assertSame( 2, Threading::depth( 'notes' ) );
		$this->assertSame( 1, Threading::depth( 'reflections' ) );
	}
}
