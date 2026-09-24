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
		\TTM\Core\Config::reset();
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

	/**
	 * R1-05, SPEC §6.1.1: the attribution never carries a date, even for today's own verse --
	 * "Meditation from dailymedtoday.com" only, linked.
	 */
	public function test_attribution_is_undated(): void {
		$today = \TTM\Core\Support\Clock::today();

		update_option(
			'ttm_verse',
			[
				'date'      => $today,
				'text'      => 'We wait in hope.',
				'reference' => 'Psalm 33:20',
				'url'       => 'https://dailymedtoday.com/meditation/abc',
				'copyright' => 'Copyright notice.',
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Meditation from <a href="https://dailymedtoday.com/meditation/abc">dailymedtoday.com</a>', $html );
		$this->assertStringNotContainsString( 'Meditation for', $html );
	}

	public function test_f6_falls_back_to_last_good_verse(): void {
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
	}

	/**
	 * R1-05, SPEC §6.1.1: the F6 stale/history fallback attribution is undated too -- the note
	 * being from a past day never leaks a date into the linked "Meditation from …" text.
	 */
	public function test_stale_fallback_attribution_is_undated(): void {
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

		$this->assertStringContainsString( 'Meditation from <a href="https://dailymedtoday.com/">dailymedtoday.com</a>', $html );
		$this->assertStringNotContainsString( 'Meditation for', $html );
		$this->assertStringNotContainsString( 'Jan 1', $html );
	}

	public function test_renders_nothing_without_any_verse(): void {
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_history' );

		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * P1-01, Decision "Scripture copyright": the verse box never renders a copyright notice,
	 * in the box or anywhere else -- there is no placement to opt back in with.
	 */
	public function test_copyright_is_never_rendered(): void {
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

		$this->assertStringNotContainsString( 'Copyright', $html );
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

	/**
	 * P1-05, rule 50: `ttm/verse-of-the-day` is a site-wide-by-design block -- the stored
	 * `ttm_verse` option has no post/term context to read at all; it renders normally with
	 * no current post/queried object.
	 */
	public function test_rule_50_no_context_with_other_content(): void {
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
		self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_insert_term( 'A Series', 'series' );
		self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$GLOBALS['post'] = null;
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- rule 50 sweep: proving no-context behaviour.

		$html = $this->render();

		$this->assertStringContainsString( 'Text.', $html );
	}
}
