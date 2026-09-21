<?php
/**
 * Unit tests for TTM\Core\Support\Text.
 *
 * @package TTM\Tests\Unit\Support
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Support;

use TTM\Core\Support\Text;
use TTM\Tests\Unit\TestCase;

class TextTest extends TestCase {

	public function test_word_count_ignores_code_blocks_shortcodes_and_tags(): void {
		$content = '<!-- wp:paragraph --><p>One two three</p><!-- /wp:paragraph -->'
			. '<!-- wp:code --><pre><code>ignored code words here</code></pre><!-- /wp:code -->'
			. '[gallery ids="1,2,3"]'
			. '<pre>more ignored words in a raw pre block</pre>'
			. '[/gallery]'
			. '<p>Four five.</p>';

		$this->assertSame( 5, Text::word_count( $content ) );
	}

	public function test_sentence_excerpt_returns_whole_sentence_without_ellipsis(): void {
		$text = 'One two three four five. Six seven eight.';

		$this->assertSame( 'One two three four five.', Text::sentence_excerpt( $text, 5 ) );
	}

	public function test_sentence_excerpt_extends_to_sentence_end(): void {
		$text = 'One two three four five six seven. Eight nine.';

		// Cutting at 5 words lands mid-sentence ("One two three four five"); extend to the period at word 7.
		$this->assertSame( 'One two three four five six seven.', Text::sentence_excerpt( $text, 5 ) );
	}

	public function test_sentence_excerpt_cuts_back_to_earlier_sentence(): void {
		// First sentence ends at word 5 ("echo."); the next 30 words have no punctuation at all,
		// so extending (up to +15 words) never finds a boundary and the excerpt must cut back
		// to the earlier sentence instead of hard-truncating with an ellipsis.
		$tail = array_fill( 0, 30, 'word' );
		$text = 'Alpha beta gamma delta echo. ' . implode( ' ', $tail );

		$this->assertSame( 'Alpha beta gamma delta echo.', Text::sentence_excerpt( $text, 10 ) );
	}

	public function test_sentence_excerpt_appends_ellipsis_when_no_boundary(): void {
		$words = array_fill( 0, 40, 'word' );
		$text  = implode( ' ', $words );

		$this->assertSame( str_repeat( 'word ', 9 ) . 'word…', Text::sentence_excerpt( $text, 10 ) );
	}

	public function test_curly_quotes_pairs_double_quotes_and_apostrophes(): void {
		$input  = 'She said "it\'s <b>fine</b>" to us.';
		$result = Text::curly_quotes( $input );

		$this->assertSame( 'She said “it’s <b>fine</b>” to us.', $result );
	}

	public function test_reading_minutes_rounds_up_and_floors_at_one(): void {
		$this->assertSame( 1, Text::reading_minutes( 10, 230 ) );
		$this->assertSame( 2, Text::reading_minutes( 231, 230 ) );
		$this->assertSame( 5, Text::reading_minutes( 1000, 230 ) );
	}
}
