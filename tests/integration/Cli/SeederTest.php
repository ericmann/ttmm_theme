<?php
/**
 * Integration tests for TTM\Core\Cli\Seeder.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;
use TTM\Core\Query\Lead;

class SeederTest extends TTM_IntegrationTestCase {

	public function test_seed_creates_seven_sections_and_politics_child(): void {
		$seeder = new Seeder();
		$seeder->seed_categories();

		$sections = [ 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ];
		foreach ( $sections as $slug ) {
			$this->assertNotFalse( term_exists( $slug, 'category' ), "Missing section {$slug}" );
		}

		$politics = get_term_by( 'slug', 'politics', 'category' );
		$opinion  = get_term_by( 'slug', 'opinion', 'category' );
		$this->assertNotFalse( $politics );
		$this->assertSame( $opinion->term_id, $politics->parent );
	}

	public function test_seed_creates_pages_with_templates(): void {
		$seeder = new Seeder();
		$seeder->seed_pages();

		$series = get_page_by_path( 'series', OBJECT, 'page' );
		$this->assertNotNull( $series );
		$this->assertSame( 'page-series.html', get_post_meta( $series->ID, '_wp_page_template', true ) );

		$writing = get_page_by_path( 'writing', OBJECT, 'page' );
		$this->assertSame( 'page-writing.html', get_post_meta( $writing->ID, '_wp_page_template', true ) );
	}

	public function test_seed_is_idempotent(): void {
		$seeder = new Seeder();
		$seeder->seed_categories();
		$first  = $seeder->seed_posts();
		$second = $seeder->seed_posts();

		$this->assertSame( $first, $second );

		$count = wp_count_posts( 'post' )->publish;
		$this->assertGreaterThanOrEqual( 60, (int) $count );
	}

	public function test_seed_posts_have_primary_category_and_word_count(): void {
		$seeder = new Seeder();
		$seeder->seed_categories();
		$ids = $seeder->seed_posts();

		$post_id = $ids[0];
		$this->assertGreaterThan( 0, (int) get_post_meta( $post_id, 'ttm_primary_category', true ) );
		$this->assertGreaterThan( 0, (int) get_post_meta( $post_id, 'ttm_word_count', true ) );
	}

	public function test_reset_removes_only_seeded_content(): void {
		$manual_post = self::factory()->post->create( [ 'post_title' => 'Not seeded' ] );

		$seeder = new Seeder();
		$seeder->run( 'normal' );
		$seeder->reset();

		$this->assertNotNull( get_post( $manual_post ) );

		$count = wp_count_posts( 'post' )->publish;
		$this->assertSame( 1, (int) $count );
	}

	public function test_generated_image_is_an_attachment_with_alt(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$seeder        = new Seeder();
		$attachment_id = $seeder->image( 'Test Image', 'ttm-tile' );

		$this->assertGreaterThan( 0, $attachment_id );
		$this->assertSame( 'attachment', get_post_type( $attachment_id ) );
		$this->assertSame( 'Test Image', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	public function test_generated_image_is_neutral_with_no_red_pixels(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$seeder        = new Seeder();
		$attachment_id = $seeder->image( 'Neutral Image', 'ttm-tile' );
		$file          = get_attached_file( $attachment_id );
		$image         = imagecreatefrompng( $file );

		$width  = imagesx( $image );
		$height = imagesy( $image );

		// Sample interior points only (5px margin) so the thin BORDER stroke, which is a
		// lighter neutral but not part of the "field vs. band" contrast this test cares about,
		// never lands in the sample.
		for ( $i = 0; $i < 20; $i++ ) {
			$x     = 5 + (int) ( ( $width - 10 ) * ( $i / 19 ) );
			$y     = 5 + (int) ( ( $height - 10 ) * ( ( $i * 7 ) % 20 ) / 19 );
			$rgb   = imagecolorat( $image, $x, $y );
			$color = imagecolorsforindex( $image, $rgb );

			$this->assertLessThanOrEqual( 20, $color['red'] - $color['green'], "pixel ({$x},{$y}) is reddish" );
			$this->assertLessThan( 200, $color['red'], "pixel ({$x},{$y}) is too bright to be neutral" );
		}

		imagedestroy( $image );
	}

	public function test_cover_is_two_by_three_and_darker_than_field(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$seeder        = new Seeder();
		$attachment_id = $seeder->cover( 'A Cover Title' );
		$meta          = wp_get_attachment_metadata( $attachment_id );

		$this->assertSame( 600, $meta['width'] );
		$this->assertSame( 900, $meta['height'] );

		$file  = get_attached_file( $attachment_id );
		$image = imagecreatefrompng( $file );
		// Sample a corner, away from the centred title text.
		$rgb   = imagecolorat( $image, 10, 10 );
		$color = imagecolorsforindex( $image, $rgb );
		imagedestroy( $image );

		// COVER (neutral-700, [96, 93, 93]) is darker than FIELD (neutral-400, [186, 182, 182]).
		$this->assertLessThan( 150, $color['red'] );
	}

	public function test_books_with_cover_flag_get_cover_ids(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$seeder = new Seeder();
		$seeder->seed_series();
		$books = $seeder->seed_books();

		$this->assertNotEmpty( $books );
		foreach ( $books as $book ) {
			$this->assertGreaterThan( 0, $book['cover_id'] );
		}
	}

	public function test_about_page_has_three_by_two_thumbnail(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$seeder = new Seeder();
		$seeder->seed_pages();

		$about = get_page_by_path( 'about', OBJECT, 'page' );
		$this->assertNotNull( $about );

		$thumbnail_id = get_post_thumbnail_id( $about->ID );
		$this->assertGreaterThan( 0, $thumbnail_id );

		$meta = wp_get_attachment_metadata( $thumbnail_id );
		$this->assertSame( 800, $meta['width'] );
		$this->assertSame( 533, $meta['height'] );
	}

	public function test_seed_fills_description_and_portrait_on_starter_content(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		// What the theme's after_switch_theme starter content leaves on a fresh install.
		wp_insert_term( 'Security', 'category', [ 'slug' => 'security' ] );
		$about_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'about',
				'post_title' => 'About',
			]
		);

		$seeder = new Seeder();
		$seeder->seed_categories();
		$seeder->seed_pages();

		$security = get_term_by( 'slug', 'security', 'category' );
		$this->assertStringStartsWith( 'Application security for people who ship.', $security->description );
		$this->assertGreaterThan( 0, get_post_thumbnail_id( $about_id ) );
	}

	public function test_admin_display_name_is_eric_mann(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$user = get_userdata( 1 );
		$this->assertSame( 'Eric Mann', $user->display_name );
	}

	/**
	 * Invoke Seeder's private `prose()` (SPEC §6.5: deterministic-by-index paragraph draw).
	 *
	 * @param Seeder $seeder    Instance.
	 * @param int    $row_index Row index.
	 * @param int    $count     Paragraph count.
	 * @return string
	 */
	private function prose( Seeder $seeder, int $row_index, int $count ): string {
		$method = new ReflectionMethod( Seeder::class, 'prose' );
		$method->setAccessible( true );

		return $method->invoke( $seeder, $row_index, $count );
	}

	public function test_prose_paragraphs_are_drawn_deterministically_by_index(): void {
		$seeder = new Seeder();

		$first  = $this->prose( $seeder, 3, 2 );
		$second = $this->prose( $seeder, 3, 2 );
		$this->assertSame( $first, $second, 'Same row index should draw the same paragraphs every time.' );

		$other = $this->prose( $seeder, 9, 2 );
		$this->assertNotSame( $first, $other, 'A different row index should draw a different first paragraph.' );

		$this->assertSame( '', $this->prose( $seeder, 0, 0 ), 'A zero count should draw nothing.' );
	}

	public function test_seed_sets_the_mock_tagline(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$this->assertSame(
			'Technology, business, faith and the occasional story. One writer, several desks.',
			get_option( 'blogdescription' )
		);
	}

	public function test_seeded_lead_is_signing_your_options_table(): void {
		$this->set_now();

		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$lead = get_post( Lead::id() );
		$this->assertNotNull( $lead );
		$this->assertSame( 'signing-your-options-table', $lead->post_name );
		$this->assertTrue( has_post_thumbnail( $lead ) );
	}

	public function test_seeded_journal_excerpts_are_38_to_48_words(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$journal = get_term_by( 'slug', 'journal', 'category' );
		$posts   = get_posts(
			[
				'category'       => $journal->term_id,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
			]
		);

		$this->assertNotEmpty( $posts );

		foreach ( $posts as $post ) {
			if ( 'classic-post' === $post->post_name ) {
				continue; // Kept verbatim per the task text; not one of the excerpt rows.
			}

			$word_count = count( preg_split( '/\s+/', trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ) ) );
			$this->assertGreaterThanOrEqual( 38, $word_count, "{$post->post_name} excerpt is too short" );
			$this->assertLessThanOrEqual( 48, $word_count, "{$post->post_name} excerpt is too long" );
		}
	}

	public function test_no_seed_row_contains_lorem(): void {
		$fixtures = [ 'posts.json', 'pages.json' ];

		foreach ( $fixtures as $fixture ) {
			$path = Seeder::fixtures_dir() . '/' . $fixture;
			$this->assertFileExists( $path );
			$this->assertStringNotContainsStringIgnoringCase( 'lorem', (string) file_get_contents( $path ) );
		}
	}

	public function test_prose_fixture_has_at_least_forty_paragraphs_without_lorem(): void {
		$path = Seeder::fixtures_dir() . '/prose.json';
		$this->assertFileExists( $path );

		$paragraphs = json_decode( (string) file_get_contents( $path ), true );

		$this->assertIsArray( $paragraphs );
		$this->assertGreaterThanOrEqual( 40, count( $paragraphs ) );

		foreach ( $paragraphs as $paragraph ) {
			$this->assertStringNotContainsStringIgnoringCase( 'lorem', $paragraph );
		}
	}

	public function test_seeded_strip_series_are_in_progress_with_totals(): void {
		( new Seeder() )->run( 'normal' );

		$expected = [
			'hardening-wordpress'    => [ 4, 6 ],
			'the-consultants-ledger' => [ 5, 8 ],
			'ordinary-time'          => [ 9, 12 ],
		];

		foreach ( $expected as $slug => [ $published, $total ] ) {
			$row = \TTM\Core\Query\SeriesIndex::by_slug( $slug );
			$this->assertNotNull( $row, "Missing series index row for {$slug}" );
			$this->assertSame( 'in-progress', $row['status'], "{$slug} should be in-progress" );
			$this->assertSame( $published, $row['published'], "{$slug} published count" );
			$this->assertSame( $total, $row['total'], "{$slug} total parts" );
		}

		$term = get_term_by( 'slug', 'ordinary-time', 'series' );
		$this->assertSame( 'Sundays', get_term_meta( $term->term_id, 'ttm_cadence', true ) );
	}

	public function test_seeded_quiet_ledger_latest_chapter_is_reconciliation(): void {
		( new Seeder() )->run( 'normal' );

		$chapter_12 = get_page_by_path( 'quiet-ledger-ch-12', OBJECT, 'post' );
		$this->assertNotNull( $chapter_12 );
		$this->assertSame( 'Reconciliation', get_post_meta( $chapter_12->ID, 'ttm_part_title', true ) );
	}

	/**
	 * R3-03: the two Writing essays are locked to a non-story form (a Seeder fixture field,
	 * `form` + `ttm_form_locked`), so they no longer auto-classify as `story` and no longer
	 * take a Fiction\Serials::stories() slot. The front page's single "Also running" story
	 * stays The Last Cron Job either way.
	 */
	public function test_seeded_writing_essays_are_locked_articles_and_last_cron_job_stays_first(): void {
		( new Seeder() )->run( 'normal' );

		$essay_1 = get_page_by_path( 'finishing-a-draft-you-no-longer-believe-in', OBJECT, 'post' );
		$essay_2 = get_page_by_path( 'outlining-for-people-who-hate-outlines', OBJECT, 'post' );
		$story   = get_page_by_path( 'story-the-last-cron-job', OBJECT, 'post' );

		$this->assertNotNull( $essay_1 );
		$this->assertNotNull( $essay_2 );
		$this->assertNotNull( $story );

		$this->assertSame( 'article', get_post_meta( $essay_1->ID, 'ttm_form', true ) );
		$this->assertSame( 'article', get_post_meta( $essay_2->ID, 'ttm_form', true ) );
		$this->assertSame( '1', get_post_meta( $essay_1->ID, 'ttm_form_locked', true ) );
		$this->assertSame( '1', get_post_meta( $essay_2->ID, 'ttm_form_locked', true ) );

		$this->assertGreaterThan( strtotime( $essay_1->post_date_gmt ), strtotime( $story->post_date_gmt ) );
		$this->assertGreaterThan( strtotime( $essay_2->post_date_gmt ), strtotime( $story->post_date_gmt ) );

		$stories = \TTM\Core\Fiction\Serials::stories( 1 );
		$this->assertSame( [ $story->ID ], $stories );
	}

	/**
	 * REVIEW round 2, finding 2: SPEC §6.10 / mock 2d name four stories, in this order.
	 */
	public function test_writing_short_fiction_is_the_four_spec_stories_in_mock_order(): void {
		( new Seeder() )->run( 'normal' );

		$titles = array_map( 'get_the_title', \TTM\Core\Fiction\Serials::stories( 4 ) );

		$this->assertSame(
			[
				'The Last Cron Job',
				'A Field Guide to Empty Offices',
				'Uptime',
				'What the River Audits',
			],
			$titles
		);
	}

	/**
	 * Rule 39-adjacent: a manual excerpt with markdown backticks renders literally wherever
	 * excerpts are shown as plain text (REVIEW round 2, finding 2).
	 */
	public function test_no_seeded_excerpt_contains_a_backtick(): void {
		( new Seeder() )->run( 'normal' );

		$posts = get_posts(
			[
				'post_type'      => 'post',
				'posts_per_page' => 200,
				'meta_key'       => '_ttm_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test-only, bounded query.
			]
		);

		$this->assertNotEmpty( $posts );

		foreach ( $posts as $post ) {
			$this->assertStringNotContainsString(
				'`',
				$post->post_excerpt,
				"Post \"{$post->post_title}\" has a backtick in its excerpt."
			);
		}
	}

	public function test_seeded_article_reads_fourteen_minutes(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$post  = get_page_by_path( 'signing-your-options-table', OBJECT, 'post' );
		$words = (int) get_post_meta( $post->ID, 'ttm_word_count', true );

		$this->assertGreaterThanOrEqual( 2991, $words );
		$this->assertLessThanOrEqual( 3220, $words );
	}

	public function test_seeded_article_has_two_h2_a_code_block_and_a_pull_quote(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$post = get_page_by_path( 'signing-your-options-table', OBJECT, 'post' );

		$this->assertSame( 2, substr_count( $post->post_content, '<!-- wp:heading -->' ) );
		$this->assertStringContainsString( '<!-- wp:code -->', $post->post_content );
		$this->assertStringContainsString( 'is-style-pull', $post->post_content );
		$this->assertStringContainsString( 'href="/hardening-part-2-salts-and-keys/"', $post->post_content );
	}

	public function test_hardening_series_has_four_published_and_two_scheduled_parts(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$slugs = [
			'hardening-part-1'                                  => 'publish',
			'hardening-part-2-salts-and-keys'                   => 'publish',
			'signing-your-options-table'                        => 'publish',
			'hardening-part-4-keys-in-the-environment'          => 'publish',
			'hardening-part-5-the-admin-with-the-weak-password' => 'future',
			'hardening-part-6-incident-when-the-alarm-fires'    => 'future',
		];

		foreach ( $slugs as $slug => $status ) {
			$post = get_page_by_path( $slug, OBJECT, 'post' );
			$this->assertNotNull( $post, "missing post: {$slug}" );
			$this->assertSame( $status, $post->post_status, "wrong status for: {$slug}" );
		}
	}

	public function test_hardening_is_the_featured_series(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$term = get_term_by( 'slug', 'hardening-wordpress', 'series' );
		$this->assertTrue( (bool) get_term_meta( $term->term_id, 'ttm_featured', true ) );

		$other = get_term_by( 'slug', 'the-quiet-ledger', 'series' );
		$this->assertFalse( (bool) get_term_meta( $other->term_id, 'ttm_featured', true ) );
	}

	public function test_seeded_article_hero_has_caption(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$post          = get_page_by_path( 'signing-your-options-table', OBJECT, 'post' );
		$thumbnail_id  = get_post_thumbnail_id( $post );
		$this->assertGreaterThan( 0, $thumbnail_id );

		$attachment = get_post( $thumbnail_id );
		$this->assertSame(
			"Caption in the theme's meta type. Photographs are grayscale only on the front page and archives.",
			$attachment->post_excerpt
		);
	}

	/**
	 * R1-07: `now` must NOT already be a Sunday, or `journal-post-1`'s `"days_ago": 0` makes
	 * the post date equal `now` regardless of whether Seeder.php's weekday walk-back loop
	 * runs at all -- the only coverage for that loop would then be tautological. 2026-09-23
	 * is a Wednesday; the walk-back must land exactly 3 days earlier, on the Sunday.
	 */
	public function test_journal_post_one_is_on_a_sunday_with_location_and_syndication(): void {
		$this->set_now( '2026-09-23 12:00:00' );

		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$post = get_page_by_path( 'journal-post-1', OBJECT, 'post' );
		$this->assertNotNull( $post );
		$this->assertSame( 'Sunday', gmdate( 'l', strtotime( $post->post_date_gmt ) ) );
		$this->assertSame( '2026-09-20', gmdate( 'Y-m-d', strtotime( $post->post_date_gmt ) ) );
		$this->assertSame( 'Portland', get_post_meta( $post->ID, 'ttm_location', true ) );

		$syndication = get_post_meta( $post->ID, 'ttm_syndication', true );
		$this->assertNotEmpty( $syndication['x'] ?? '' );
		$this->assertNotEmpty( $syndication['mastodon'] ?? '' );
	}

	public function test_journal_word_counts_near_the_mock(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$post_2 = get_page_by_path( 'journal-post-2', OBJECT, 'post' );
		$post_3 = get_page_by_path( 'journal-post-3', OBJECT, 'post' );

		$words_2 = (int) get_post_meta( $post_2->ID, 'ttm_word_count', true );
		$words_3 = (int) get_post_meta( $post_3->ID, 'ttm_word_count', true );

		$this->assertGreaterThanOrEqual( 160, $words_2 );
		$this->assertLessThanOrEqual( 210, $words_2 );
		$this->assertGreaterThanOrEqual( 80, $words_3 );
		$this->assertLessThanOrEqual( 115, $words_3 );
	}

	public function test_quiet_ledger_has_synopsis_genre_cadence_and_titled_chapters(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$term = get_term_by( 'slug', 'the-quiet-ledger', 'series' );
		$this->assertNotEmpty( $term->description );
		$this->assertSame( 'literary thriller', get_term_meta( $term->term_id, 'ttm_genre', true ) );
		$this->assertSame( 'monthly', get_term_meta( $term->term_id, 'ttm_cadence', true ) );

		for ( $chapter = 1; $chapter <= 12; $chapter++ ) {
			$post = get_page_by_path( "quiet-ledger-ch-{$chapter}", OBJECT, 'post' );
			$this->assertNotNull( $post, "missing chapter {$chapter}" );
			$part_title = get_post_meta( $post->ID, 'ttm_part_title', true );
			$this->assertNotEmpty( $part_title, "chapter {$chapter} has no part_title" );
			$this->assertNotEmpty( $post->post_excerpt, "chapter {$chapter} has no excerpt" );
		}

		$row   = \TTM\Core\Query\SeriesIndex::by_slug( 'the-quiet-ledger' );
		$stats = \TTM\Core\Fiction\Serials::stats( $row );
		$this->assertGreaterThanOrEqual( 10, $stats['avg_minutes'] );
		$this->assertLessThanOrEqual( 16, $stats['avg_minutes'] );
	}

	public function test_security_has_two_year_groups_on_page_one_and_a_second_page(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$term = get_term_by( 'slug', 'security', 'category' );
		$query = new WP_Query(
			[
				'cat'            => $term->term_id,
				'posts_per_page' => 12,
				'paged'          => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$this->assertGreaterThan( 12, $query->found_posts, 'expected a second page' );

		$years = [];
		foreach ( $query->posts as $post ) {
			$years[ gmdate( 'Y', strtotime( $post->post_date_gmt ) ) ] = true;
		}
		$this->assertGreaterThanOrEqual( 2, count( $years ), 'page one should span two year groups' );
	}

	public function test_security_top_tags_are_the_mock_five(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$term = get_term_by( 'slug', 'security', 'category' );
		$tags = \TTM\Core\Query\Stats::top_tags( $term->term_id );
		$slugs = array_column( $tags, 'slug' );

		sort( $slugs );
		$expected = [ 'cryptography', 'disclosure', 'passwords', 'threat-modeling', 'wordpress' ];
		$this->assertSame( $expected, $slugs );
	}

	public function test_three_security_posts_are_most_read(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$term  = get_term_by( 'slug', 'security', 'category' );
		$query = new WP_Query(
			[
				'cat'            => $term->term_id,
				'posts_per_page' => 50,
				'meta_key'       => 'ttm_featured_in_section',
				'meta_value'     => '1',
			]
		);

		$this->assertCount( 3, $query->posts );

		$slugs = wp_list_pluck( $query->posts, 'post_name' );
		sort( $slugs );
		$this->assertSame(
			[ 'hardware-keys-for-my-parents', 'nonces-are-not-csrf-tokens', 'reading-a-cve-like-an-engineer' ],
			$slugs
		);
	}

	/**
	 * R1-07: SPEC §6.10 and mock 2d's "In print" grid name exactly two books -- an
	 * assertContains() pair cannot catch a stray third row, so this asserts the exact,
	 * sorted title set.
	 */
	public function test_books_are_salt_water_wires_and_eleven_small_doors(): void {
		$seeder = new Seeder();
		$books  = $seeder->seed_books();

		$titles = array_column( $books, 'title' );
		sort( $titles );
		$this->assertSame( [ 'Eleven Small Doors', 'Salt Water Wires' ], $titles );

		foreach ( $books as $book ) {
			if ( 'Salt Water Wires' === $book['title'] ) {
				$this->assertSame( 'novel', $book['form'] );
				$this->assertSame( 2022, $book['year'] );
			}
			if ( 'Eleven Small Doors' === $book['title'] ) {
				$this->assertSame( 'collection', $book['form'] );
				$this->assertSame( 2019, $book['year'] );
			}
		}
	}

	/**
	 * P0-02: every section but Journal has at least five distinct tags spread across its
	 * seeded posts (SPEC §6.12). Stats::top_tags() invalidation lands in P0-05, so this counts
	 * distinct tag slugs directly off wp_get_post_tags() instead.
	 */
	public function test_every_section_except_journal_has_at_least_five_distinct_tags(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$sections = [ 'technology', 'business', 'faith', 'writing', 'security', 'opinion' ];

		foreach ( $sections as $slug ) {
			$term  = get_term_by( 'slug', $slug, 'category' );
			$posts = get_posts(
				[
					'category'       => $term->term_id,
					'post_status'    => 'publish',
					'posts_per_page' => 200,
				]
			);

			$slugs = [];
			foreach ( $posts as $post ) {
				foreach ( wp_get_post_tags( $post->ID ) as $tag ) {
					$slugs[ $tag->slug ] = true;
				}
			}

			$this->assertGreaterThanOrEqual(
				5,
				count( $slugs ),
				"expected at least five distinct tags in {$slug}"
			);
		}

		$journal = get_term_by( 'slug', 'journal', 'category' );
		$posts   = get_posts(
			[
				'category'       => $journal->term_id,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
			]
		);
		$slugs = [];
		foreach ( $posts as $post ) {
			foreach ( wp_get_post_tags( $post->ID ) as $tag ) {
				$slugs[ $tag->slug ] = true;
			}
		}
		$this->assertCount( 0, $slugs, 'Journal should stay untagged' );
	}

	/**
	 * P0-02: SPEC §6.3 -- the transients post gets an older Technology neighbour so both
	 * prev/next cells render, and that neighbour is not in any series.
	 */
	public function test_transients_post_has_an_older_technology_neighbour(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$transients = get_page_by_path( 'transients-object-caches-and-fast-enough', OBJECT, 'post' );
		$this->assertNotNull( $transients );

		$neighbour = get_page_by_path( 'why-i-still-read-the-wordpress-changelog', OBJECT, 'post' );
		$this->assertNotNull( $neighbour );
		$this->assertSame( 'publish', $neighbour->post_status );

		$categories = get_the_terms( $neighbour->ID, 'category' );
		$this->assertIsArray( $categories );
		$this->assertContains( 'technology', wp_list_pluck( $categories, 'slug' ) );

		$this->assertLessThan(
			strtotime( $transients->post_date_gmt ),
			strtotime( $neighbour->post_date_gmt ),
			'the neighbour should be older than the transients post'
		);

		$this->assertSame( [], wp_get_post_terms( $neighbour->ID, 'series' ) );
	}

	/**
	 * P0-02: the older Technology neighbour is sized to ~600 words (SPEC §6.3).
	 */
	public function test_changelog_post_is_about_six_hundred_words(): void {
		$seeder = new Seeder();
		$seeder->run( 'normal' );

		$post = get_page_by_path( 'why-i-still-read-the-wordpress-changelog', OBJECT, 'post' );
		$this->assertNotNull( $post );

		$words = (int) get_post_meta( $post->ID, 'ttm_word_count', true );
		$this->assertGreaterThanOrEqual( 500, $words );
		$this->assertLessThanOrEqual( 700, $words );
	}
}
