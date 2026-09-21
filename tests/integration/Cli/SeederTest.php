<?php
/**
 * Integration tests for TTM\Core\Cli\Seeder.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;

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
}
