<?php
/**
 * Integration tests for single.html / single-journal.html (P4-05).
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class ArticleTemplatesTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_transient( 'ttm_lead_id' );
		parent::tear_down();
	}

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function render_single( int $post_id, string $template = 'single' ): string {
		// go_to() first: it resets $wp_query (and the $pages/$page/$multipage globals
		// setup_postdata() sets), so setup_postdata() must run after it, not before.
		$this->go_to( (string) get_permalink( $post_id ) );

		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		setup_postdata( $post );

		$html = $this->render_template( $template );

		wp_reset_postdata();

		return $html;
	}

	public function test_single_series_post_renders_bar_toc_prev_next_and_more_in_section(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech      = $this->category_id( 'technology', 'Technology' );
		$term      = wp_insert_term( 'Hardening WordPress', 'series' );
		$series_id = (int) $term['term_id'];

		$ids = [];
		foreach ( [ 1, 2, 3 ] as $part ) {
			$id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $tech ],
				]
			);
			update_post_meta( $id, 'ttm_series_part', $part );
			update_post_meta( $id, 'ttm_primary_category', $tech );
			wp_set_object_terms( $id, [ $series_id ], 'series' );
			$ids[ $part ] = $id;
		}
		SeriesIndex::rebuild();

		$html = $this->render_single( $ids[2] );

		$this->assertStringContainsString( 'ttm-series-bar', $html );
		$this->assertStringContainsString( 'ttm-series-toc', $html );
		$this->assertStringContainsString( 'ttm-prevnext', $html );
		$this->assertStringContainsString( '← Part 1', $html );
		$this->assertStringContainsString( 'Part 3 →', $html );
		$this->assertStringContainsString( 'ttm-more-in', $html );
	}

	public function test_f11_non_series_post_has_no_bar_and_chronological_prevnext(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech = $this->category_id( 'technology', 'Technology' );

		$older = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-10 09:00:00',
			]
		);
		update_post_meta( $older, 'ttm_primary_category', $tech );

		$current = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-15 09:00:00',
			]
		);
		update_post_meta( $current, 'ttm_primary_category', $tech );

		$html = $this->render_single( $current );

		$this->assertStringNotContainsString( 'ttm-series-mark', $html );
		$this->assertStringContainsString( '← Previously in Technology', $html );
	}

	public function test_f12_no_featured_image_renders_no_figure(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$html = $this->render_single( $post );

		$this->assertStringNotContainsString( 'wp-block-post-featured-image', $html );
	}

	/**
	 * Rule 36 (extended): the row's direct children -- `main` and `aside` -- are
	 * `layout: default` groups, so no `is-layout-constrained` (and no core global padding)
	 * sits inside `.ttm-article`.
	 */
	public function test_article_columns_are_not_constrained(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$html = $this->render_single( $post );

		$this->assertSame( 1, preg_match( '/<main class="([^"]*)"[^>]*id="main"/', $html, $main ) );
		$this->assertStringNotContainsString( 'is-layout-constrained', $main[1] );
		$this->assertSame( 1, preg_match( '/<aside class="([^"]*)"/', $html, $aside ) );
		$this->assertStringNotContainsString( 'is-layout-constrained', $aside[1] );
		$this->assertStringContainsString( 'is-style-grid-8-4 ttm-article', $html );
	}

	/**
	 * SPEC §6.2 "Hero" / Decision "Featured-image caption": the attachment's caption
	 * (`post_excerpt`) renders as `figcaption.ttm-hero__caption` inside the hero figure.
	 */
	public function test_hero_caption_renders_from_attachment_excerpt(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$attachment_id = ( new \TTM\Core\Cli\Seeder() )->image( 'Caption test', 'ttm-lead' );
		$this->assertGreaterThan( 0, $attachment_id );
		wp_update_post(
			[
				'ID'           => $attachment_id,
				'post_excerpt' => 'Caption in the theme\'s meta type.',
			]
		);
		set_post_thumbnail( $post, $attachment_id );

		$html = $this->render_single( $post );

		$this->assertStringContainsString( 'wp-block-post-featured-image', $html );
		$this->assertMatchesRegularExpression(
			'/<figcaption class="ttm-hero__caption">Caption in the theme(&#039;|\')s meta type\.<\/figcaption><\/figure>/',
			$html
		);
	}

	public function test_f13_more_in_section_marks_empty_when_no_other_posts(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$html = $this->render_single( $post );

		$this->assertStringContainsString( 'ttm-more-in', $html );
		$this->assertStringContainsString( 'is-empty', $html );
	}

	public function test_single_journal_renders_date_subline_stream_and_syndication(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_date'     => '2026-09-20 09:00:00',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );
		update_post_meta( $post, 'ttm_location', 'Portland' );
		update_post_meta(
			$post,
			'ttm_syndication',
			[ 'x' => 'https://x.com/example/1' ]
		);

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_date'     => '2026-09-01 09:00:00',
			]
		);

		$html = $this->render_single( $post, 'single-journal' );

		$this->assertStringContainsString( 'Sunday · Portland', $html );
		$this->assertStringContainsString( 'ttm-journal-stream', $html );
		$this->assertStringContainsString( 'Syndicated to', $html );
	}

	public function test_f14_journal_without_syndication_shows_word_count_in_note_column(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );
		update_post_meta( $post, 'ttm_word_count', 248 );

		$html = $this->render_single( $post, 'single-journal' );

		$this->assertStringNotContainsString( 'Syndicated to', $html );
		$this->assertStringContainsString( '248 words', $html );
	}
}
