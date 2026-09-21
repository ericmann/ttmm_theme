<?php
/**
 * Front-page fallback state tests, from the seeder's quiet/empty/normal states (P3-11).
 *
 * @package TTM\Tests\Integration\Fallbacks
 */

declare( strict_types=1 );

use TTM\Core\Query\Lead;

class FrontPageStatesTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_transient( 'ttm_lead_id' );
		parent::tear_down();
	}

	private function render(): string {
		$this->go_to( '/' );

		return $this->render_template( 'front-page' );
	}

	public function test_quiet_month_cells_show_existing_posts_with_full_dates(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'quiet' );

		$html = $this->render();

		// F9: every date is > 30 days old in the "quiet" state, so meta-line dates always carry
		// a comma + 4-digit year (Values::relative_date()/short_date() add the year unconditionally
		// once past journal.rail_window_days).
		$this->assertMatchesRegularExpression( '/[A-Z][a-z]+ \d{1,2}, 20\d{2}/', $html );
	}

	public function test_quiet_journal_rail_shows_latest_entry_with_full_date(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'quiet' );

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-journal-rail', $html );
		$this->assertMatchesRegularExpression( '/ttm-journal-excerpt__date[^>]*">[^<]*[A-Z][a-z]+ \d{1,2}, 20\d{2}/', $html );
	}

	public function test_quiet_lead_is_sitewide_when_technology_is_stale(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'quiet' );

		$lead = Lead::compute();

		$this->assertSame( 'sitewide', $lead['reason'] );
	}

	public function test_empty_state_hides_security_and_opinion_cells_but_keeps_technology_span(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'empty' );

		$html = $this->render();

		$this->assertStringContainsString( 'is-style-span-2', $html );

		// The security/opinion queries render no posts in the "empty" state; F17 marks the
		// empty Query wrapper is-empty (CSS then collapses it from the grid).
		$empty_queries = substr_count( $html, 'wp-block-query is-empty' ) + substr_count( $html, 'is-layout-flow wp-block-query-is-layout-flow is-empty' );
		$this->assertGreaterThanOrEqual( 2, $empty_queries );
	}

	public function test_empty_state_removes_series_strip_and_series_nav_item(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'empty' );

		$html = $this->render();

		// F4: zero series terms at all -> ttm/series-list renders nothing.
		$this->assertStringNotContainsString( 'ttm-series-list', $html );

		// F18: the "Series" nav item is removed entirely when the index is empty. (The series
		// strip's own CSS-hidden fallback heading text, unrelated to the nav, legitimately
		// contains the literal ">Series<" substring, so check the nav label specifically.)
		$this->assertStringNotContainsString( 'wp-block-navigation-item__label">Series<', $html );
	}

	public function test_empty_state_verse_block_absent_and_journal_first_in_rail(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'empty' );

		$html = $this->render();

		$this->assertStringNotContainsString( 'ttm-verse', $html );
		$this->assertStringContainsString( 'ttm-journal-rail', $html );
	}

	public function test_empty_state_writing_cell_is_plain(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'empty' );

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-writing-cell is-plain', $html );
	}

	public function test_f9_stale_year_section_shows_two_rows_without_dek(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'empty' );

		$security = get_term_by( 'slug', 'security', 'category' );
		$this->assertNotFalse( $security );

		foreach ( [ 'Old Security One', 'Old Security Two' ] as $i => $title ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $security->term_id ],
					'post_title'    => $title,
					'post_excerpt'  => 'A real excerpt that must not appear.',
					'post_date'     => sprintf( '2024-01-0%d 09:00:00', $i + 1 ),
				]
			);
			update_post_meta( $post_id, 'ttm_primary_category', $security->term_id );
		}

		// No do_action( 'init' ) re-fire needed: the pattern itself no longer varies by
		// staleness (patterns.php never calls Query\Cells) -- the dek suppression and the
		// posts_per_page drop both happen plugin-side, per request, in Query\Cells.
		$html = (string) do_blocks( '<!-- wp:pattern {"slug":"ttm/section-cell-security"} /-->' );

		$this->assertSame( 2, substr_count( $html, 'wp-block-post ' ) );
		$this->assertStringContainsString( 'Old Security One', $html );
		$this->assertStringContainsString( 'Old Security Two', $html );
		$this->assertStringNotContainsString( 'ttm-item__dek', $html );
		$this->assertStringNotContainsString( 'A real excerpt', $html );
	}

	public function test_normal_state_zero_count_link_reads_all_arrow_for_new_category(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'normal' );

		wp_insert_term( 'Gardening', 'category', [ 'slug' => 'gardening' ] );

		$ttm_section = [
			'slug'     => 'gardening',
			'name'     => 'Gardening',
			'per_page' => 3,
		];

		ob_start();
		require get_stylesheet_directory() . '/inc/pattern-templates/section-cell.php';
		$content = (string) ob_get_clean();

		$html = (string) do_blocks( $content );

		$this->assertStringContainsString( 'All →', $html );
	}
}
