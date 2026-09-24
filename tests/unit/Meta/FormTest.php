<?php
/**
 * Unit tests for TTM\Core\Meta\Form::derive().
 *
 * @package TTM\Tests\Unit\Meta
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Meta;

use TTM\Core\Meta\Form;
use TTM\Tests\Unit\TestCase;

class FormTest extends TestCase {

	public function test_chapter_when_series_is_fiction(): void {
		$this->assertSame( 'chapter', Form::derive( 'novel', false ) );
	}

	public function test_story_when_in_writing_without_series(): void {
		$this->assertSame( 'story', Form::derive( null, true ) );
	}

	public function test_article_otherwise(): void {
		$this->assertSame( 'article', Form::derive( null, false ) );
		$this->assertSame( 'article', Form::derive( 'nonfiction', false ) );
		$this->assertSame( 'article', Form::derive( 'nonfiction', true ) );
	}

	/**
	 * P1-04, Decision "Editor-only story derivation": `derive()` itself is unchanged by the
	 * editor-only gate in `on_save()` -- it's still a pure function of (series form, in Writing).
	 */
	public function test_derive_is_unchanged(): void {
		$this->assertSame( 'chapter', Form::derive( 'novel', false ) );
		$this->assertSame( 'chapter', Form::derive( 'novel', true ) );
		$this->assertSame( 'story', Form::derive( null, true ) );
		$this->assertSame( 'article', Form::derive( null, false ) );
		$this->assertSame( 'article', Form::derive( 'nonfiction', true ) );
	}
}
