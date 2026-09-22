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

	/**
	 * Decision "Kicker term order": the seeded article (Technology primary, Security second)
	 * reads "Technology · Security", not the alphabetical "Security, Technology".
	 */
	public function test_article_kicker_reads_primary_then_secondary_category(): void {
		( new \TTM\Core\Cli\Seeder() )->run( 'normal' );
		$post = get_page_by_path( 'signing-your-options-table', OBJECT, 'post' );
		$this->assertNotNull( $post );

		$html = $this->render_single( $post->ID );

		$this->assertSame( 1, preg_match( '/<div class="[^"]*is-style-kicker[^"]*wp-block-post-terms">(.*?)<\/div>/s', $html, $m ) );
		$this->assertSame( 'Technology · Security', trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) ) );

		// The dek keeps the manual excerpt's inline code (core's wp_trim_words would strip it).
		$this->assertMatchesRegularExpression( '/<p class="wp-block-post-excerpt__excerpt">[^<]*<code>wp_options<\/code>/', $html );
	}

	/**
	 * Decision "Byline tags": the byline's post_tag terms render as `.tag.tag-neutral` chips
	 * with no separator spans, and the author renders as a linked "By …".
	 */
	public function test_byline_tags_are_tag_chips(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_category' => [ $tech ],
				'tags_input'    => [ 'wordpress', 'php' ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$html = $this->render_single( $post );

		$this->assertSame( 1, preg_match( '/<div class="[^"]*is-style-tags[^"]*wp-block-post-terms">(.*?)<\/div>/s', $html, $m ) );
		$this->assertSame( 2, substr_count( $m[1], '<a class="tag tag-neutral"' ) );
		$this->assertStringNotContainsString( 'wp-block-post-terms__separator', $m[1] );
		$this->assertMatchesRegularExpression( '/<div class="wp-block-post-author-name">By <a[^>]*>[^<]+<\/a><\/div>/', $html );
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

	/**
	 * SPEC §6.2 "More in {Category}": one label with the category name inline, not a second
	 * label on the right; rows are `h4` post titles inside `.ttm-item`.
	 */
	public function test_more_in_heading_is_one_label_with_category_name(): void {
		$tech  = $this->category_id( 'technology', 'Technology' );
		$posts = [];
		foreach ( [ 1, 2 ] as $i ) {
			$posts[] = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $tech ],
				]
			);
			update_post_meta( $posts[ $i - 1 ], 'ttm_primary_category', $tech );
		}

		$html = $this->render_single( $posts[0] );

		$this->assertSame( 1, preg_match( '/<div class="[^"]*ttm-more-in[^"]*"[^>]*>(.*?)<div class="wp-block-query[^"]*">/s', $html, $m ) );
		$this->assertStringContainsString( '<h3 class="wp-block-heading ttm-cell-heading__label">More in Technology</h3>', $m[1] );
		$this->assertSame( 1, substr_count( $m[1], 'ttm-cell-heading__label' ) );
		$this->assertStringNotContainsString( 'wp-block-post-terms', $m[1] );
		$this->assertMatchesRegularExpression( '/<div class="wp-block-group ttm-item[^"]*">\s*<h4 class="wp-block-post-title"><a href/', $html );
	}

	/**
	 * SPEC §6.4: the three journal-head columns are `layout: default` groups (rule 36 extended),
	 * the note column carries the mock's copy and the RSS link, and a syndicated post drops the
	 * bound word-count paragraph entirely (Decision "Empty bound blocks").
	 */
	public function test_journal_head_columns_are_not_constrained_and_note_has_mock_copy(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );
		update_post_meta( $post, 'ttm_word_count', 248 );
		update_post_meta( $post, 'ttm_syndication', [ 'x' => 'https://x.com/example/1' ] );

		$html = $this->render_single( $post, 'single-journal' );

		$this->assertSame( 1, preg_match( '/<div class="([^"]*)ttm-journal-head([^"]*)"[^>]*>(.*?)<hr /s', $html, $m ) );
		$this->assertStringNotContainsString( 'is-layout-constrained', $m[3] );
		$this->assertMatchesRegularExpression( '/<p class="ttm-journal-head__note[^"]*">Journal entries are short and unpolished — things I saw and what they made me think\. Longer arguments land in a section\.<\/p>/', $html );
		$this->assertMatchesRegularExpression( '/<p class="ttm-journal-head__rss[^"]*"><a href="\/category\/journal\/feed\/">Journal RSS<\/a><\/p>/', $html );
		$this->assertMatchesRegularExpression( '/<h1 class="[^"]*is-style-journal-title[^"]*"/', $html );
		$this->assertStringContainsString( 'ttm-journal-head__subline', $html );
		$this->assertStringNotContainsString( 'ttm-journal-head__count', $html );
		$this->assertStringContainsString( '<span class="ttm-syndication__words">', $html );
	}
}
