<?php
/**
 * Integration tests for TTM\Core\Query\Archive.
 *
 * @package TTM\Tests\Integration\Query
 */

declare( strict_types=1 );

use TTM\Core\Config;
use TTM\Core\Query\Archive;

class ArchiveTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		$_GET = [];
		update_option( 'ttm_settings', [] );
		Config::reset();
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

	public function test_category_archive_uses_archive_per_page(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create_many(
			3,
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			] 
		);

		$this->go_to( (string) get_category_link( $tech ) );

		global $wp_query;
		$this->assertSame( (int) Config::get( 'archive.per_page' ), (int) $wp_query->get( 'posts_per_page' ) );
	}

	public function test_journal_archive_uses_journal_per_page(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		self::factory()->post->create_many(
			3,
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			] 
		);

		$this->go_to( (string) get_category_link( $journal ) );

		global $wp_query;
		$this->assertSame( (int) Config::get( 'journal.archive_per_page' ), (int) $wp_query->get( 'posts_per_page' ) );
	}

	public function test_tag_query_var_narrows_category_archive(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$tagged   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			] 
		);
		$untagged = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			] 
		);

		// go_to() clears $_GET and repopulates it by parsing the URL's own query string (WP core
		// test suite behavior), so the tag must be appended to the URL, not set beforehand.
		// (The test env's non-pretty permalinks mean get_category_link() already has its own
		// "?cat=" query string, so add_query_arg() rather than a literal "?" suffix.)
		$this->go_to( add_query_arg( 'tag', 'php', get_category_link( $tech ) ) );

		global $wp_query;
		$ids = wp_list_pluck( $wp_query->posts, 'ID' );

		$this->assertContains( $tagged, $ids );
		$this->assertNotContains( $untagged, $ids );
	}

	public function test_main_feed_excludes_journal_when_setting_false(): void {
		update_option( 'ttm_settings', [ 'journal_in_main_feed' => false ] );
		Config::reset();

		$journal = $this->category_id( 'journal', 'Journal' );

		$this->go_to( '/?feed=rss2' );

		global $wp_query;
		$this->assertContains( $journal, (array) $wp_query->get( 'category__not_in' ) );
	}

	public function test_main_feed_includes_journal_by_default(): void {
		$journal = $this->category_id( 'journal', 'Journal' );

		$this->go_to( '/?feed=rss2' );

		global $wp_query;
		$this->assertNotContains( $journal, (array) $wp_query->get( 'category__not_in' ) );
	}

	public function test_pagination_next_label_has_year_range_on_seeded_archive(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		update_option( 'ttm_settings', [] );
		Config::reset();
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['archive.per_page'] = 1;
				return $config;
			} 
		);

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2022-05-01 09:00:00',
			] 
		);
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2023-06-01 09:00:00',
			] 
		);

		$this->go_to( (string) get_category_link( $tech ) );

		$label = Archive::resolve_label( 'older' );

		$this->assertStringContainsString( 'Older (', $label );
		$this->assertStringContainsString( '→', $label );
	}

	public function test_section_feed_links_are_printed_in_head(): void {
		$this->category_id( 'technology', 'Technology' );

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rel="alternate"', $html );
		$this->assertStringContainsString( 'Technology RSS', $html );
	}
}
