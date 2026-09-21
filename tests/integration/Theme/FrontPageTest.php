<?php
/**
 * Integration tests for the front page (P3-10): patterns, rail, template.
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

use TTM\Core\Query\Lead;

class FrontPageTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_transient( 'ttm_lead_id' );
		parent::tear_down();
	}

	private function category_id( string $slug, string $name, int $parent = 0 ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term(
			$name,
			'category',
			[
				'slug'   => $slug,
				'parent' => $parent,
			] 
		);

		return (int) $created['term_id'];
	}

	public function test_front_page_renders_all_zones_in_order(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'normal' );
		$this->go_to( '/' );

		$html = $this->render_template( 'front-page' );

		$pos_masthead = strpos( $html, 'ttm-masthead-front' );
		$pos_lead     = strpos( $html, 'ttm-lead-row__lead' );
		$pos_rail     = strpos( $html, 'ttm-rail' );
		$pos_verse    = strpos( $html, 'ttm-verse' );
		$pos_journal  = strpos( $html, 'ttm-journal-rail' );
		$pos_row1     = strpos( $html, 'ttm-section-row' );
		$pos_strip    = strpos( $html, 'ttm-series-strip' );
		$pos_poster   = strpos( $html, 'ttm-poster' );
		$pos_footer   = strpos( $html, 'is-after-poster' );

		foreach ( [ 'masthead', 'lead', 'rail', 'verse', 'journal', 'row1', 'strip', 'poster', 'footer' ] as $zone ) {
			$this->assertNotFalse( ${'pos_' . $zone}, "Missing zone: {$zone}" );
		}

		$this->assertLessThan( $pos_lead, $pos_masthead );
		$this->assertLessThan( $pos_rail, $pos_lead );
		$this->assertLessThan( $pos_journal, $pos_verse );
		$this->assertLessThan( $pos_row1, $pos_rail );
		$this->assertLessThan( $pos_strip, $pos_row1 );
		$this->assertLessThan( $pos_poster, $pos_strip );
		$this->assertLessThan( $pos_footer, $pos_poster );
	}

	public function test_lead_post_is_excluded_from_technology_cell(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$lead_post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_title'    => 'Lead Post Title',
				'post_date'     => '2026-09-19 09:00:00',
			]
		);
		update_post_meta( $lead_post, 'ttm_primary_category', $tech );

		self::factory()->post->create_many(
			2,
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				// Older than the lead post: real wall-clock post_date would otherwise outrank
				// it against the fixed set_now() clock used for Lead's freshness window.
				'post_date'     => '2026-09-01 09:00:00',
			]
		);
		foreach ( get_posts(
			[
				'category'       => $tech,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 10,
			] 
		) as $id ) {
			update_post_meta( $id, 'ttm_primary_category', $tech );
		}

		$this->assertSame( $lead_post, Lead::id() );

		$this->go_to( '/' );
		$html = $this->render_template( 'front-page' );

		$this->assertSame( 1, substr_count( $html, 'Lead Post Title' ) );
	}

	public function test_opinion_cell_meta_shows_politics_suffix(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$opinion  = $this->category_id( 'opinion', 'Opinion' );
		$politics = $this->category_id( 'politics', 'Politics', $opinion );
		$tech     = $this->category_id( 'technology', 'Technology' );

		// A lead candidate, newer than the politics post below, so Lead::compute() picks it
		// instead (both need explicit dates against the fixed set_now() clock: real
		// wall-clock post_date would otherwise decide "newest").
		$other = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-19 09:00:00',
			]
		);
		update_post_meta( $other, 'ttm_primary_category', $tech );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $opinion, $politics ],
				'post_date'     => '2026-09-18 09:00:00',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $opinion );

		$this->go_to( '/' );
		$html = $this->render_template( 'front-page' );

		$this->assertStringContainsString( 'Politics', $html );
	}

	public function test_journal_rail_shows_relative_dates_and_continue(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$journal = $this->category_id( 'journal', 'Journal' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_date'     => '2026-09-20 09:00:00',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );

		$this->go_to( '/' );
		$html = $this->render_template( 'front-page' );

		$this->assertStringContainsString( 'Today', $html );
		$this->assertStringContainsString( 'Continue', $html );
	}

	public function test_no_nonce_and_no_wp_json_strings_in_output(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'normal' );
		$this->go_to( '/' );

		$html = $this->render_template( 'front-page' );

		$this->assertStringNotContainsString( 'wpnonce', $html );
		$this->assertStringNotContainsString( 'wp_nonce', $html );
		$this->assertStringNotContainsString( '/wp-json/', $html );
	}

	public function test_front_page_has_single_main_landmark(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'normal' );
		$this->go_to( '/' );

		$html = $this->render_template( 'front-page' );

		$this->assertSame( 1, substr_count( $html, '<main' ) );
		$this->assertStringContainsString( 'id="main"', $html );
	}

	/**
	 * Rules 35/36: every `is-style-grid-*` group renders `layout:default`, never
	 * `is-layout-constrained` -- theme CSS (`.is-style-grid-*`) owns the columns, not the
	 * block's own `layout` support.
	 */
	public function test_grid_groups_are_not_constrained(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->seed( 'normal' );
		$this->go_to( '/' );

		$html = $this->render_template( 'front-page' );

		$this->assertDoesNotMatchRegularExpression( '/is-style-grid-[0-9a-z-]+[^"]*is-layout-constrained/', $html );
		$this->assertDoesNotMatchRegularExpression( '/ttm-section-row[^"]*is-layout-constrained/', $html );
	}
}
