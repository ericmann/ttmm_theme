<?php
/**
 * Integration tests for the ttm/verse-of-the-day block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class VerseOfTheDayTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_history' );
		parent::tear_down();
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/verse-of-the-day' . $json . ' /-->' );
	}

	public function test_renders_todays_verse_with_attribution_link(): void {
		$today = \TTM\Core\Support\Clock::today();

		update_option(
			'ttm_verse',
			[
				'date'      => $today,
				'text'      => 'We wait in hope.',
				'reference' => 'Psalm 33:20',
				'title'     => 'Hope',
				'url'       => 'https://dailymedtoday.com/meditation/abc',
				'copyright' => 'Copyright notice.',
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'We wait in hope.', $html );
		$this->assertStringContainsString( 'Psalm 33:20', $html );
		$this->assertStringContainsString( 'href="https://dailymedtoday.com/meditation/abc"', $html );
		$this->assertStringContainsString( 'dailymedtoday.com', $html );
	}

	public function test_f6_falls_back_to_last_good_verse_with_its_own_date(): void {
		update_option( 'ttm_verse', [] );
		update_option(
			'ttm_verse_history',
			[
				[
					'date'      => '2020-01-01',
					'text'      => 'An older verse.',
					'reference' => 'Genesis 1:1',
					'copyright' => '',
					'url'       => '',
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'An older verse.', $html );
		$this->assertStringContainsString( 'Jan 1', $html );
	}

	public function test_f6_shows_stored_verse_from_yesterday_not_history_head(): void {
		$this->set_now( '2026-09-19 12:00:00' );

		update_option(
			'ttm_verse',
			[
				'date'      => '2026-09-18',
				'text'      => "Yesterday's verse.",
				'reference' => 'Psalm 1:1',
				'copyright' => '',
				'url'       => '',
			]
		);
		update_option(
			'ttm_verse_history',
			[
				[
					'date'      => '2026-09-17',
					'text'      => 'An older history-head verse.',
					'reference' => 'Genesis 1:1',
					'copyright' => '',
					'url'       => '',
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Yesterday’s verse.', $html );
		$this->assertStringNotContainsString( 'An older history-head verse.', $html );
		$this->assertStringContainsString( 'Meditation for Sept 18', $html );
	}

	public function test_attribution_uses_sept_abbreviation(): void {
		$this->set_now( '2026-09-05 12:00:00' );

		update_option(
			'ttm_verse',
			[
				'date'      => '2026-09-05',
				'text'      => 'Text.',
				'reference' => 'Ref.',
				'copyright' => '',
				'url'       => '',
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Meditation for Sept 5', $html );
		$this->assertStringNotContainsString( 'Meditation for Sep ', $html );
	}

	public function test_renders_nothing_without_any_verse(): void {
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_history' );

		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	public function test_copyright_is_rendered_as_plain_text(): void {
		update_option(
			'ttm_verse',
			[
				'date'      => \TTM\Core\Support\Clock::today(),
				'text'      => 'Text.',
				'reference' => 'Ref.',
				'copyright' => 'Copyright <b>bold</b> notice.',
				'url'       => '',
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Copyright &lt;b&gt;bold&lt;/b&gt; notice.', $html );
	}

	public function test_preview_state_empty_renders_nothing_in_editor_context(): void {
		update_option(
			'ttm_verse',
			[
				'date'      => \TTM\Core\Support\Clock::today(),
				'text'      => 'Text.',
				'reference' => 'Ref.',
				'copyright' => '',
				'url'       => '',
			]
		);

		set_current_screen( 'post' );

		$html = $this->render( [ 'previewState' => 'empty' ] );

		$this->assertSame( '', trim( $html ) );
	}
}
