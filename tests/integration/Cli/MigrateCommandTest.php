<?php
/**
 * Integration tests for the P8-04 CLI command core (migrate:politics/redirects/close-comments).
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\MigrateCommand;

class MigrateCommandTest extends TTM_IntegrationTestCase {

	private function politics_id(): int {
		$term = term_exists( 'politics', 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( 'Politics', 'category', [ 'slug' => 'politics' ] );

		return (int) $created['term_id'];
	}

	public function test_politics_child_mode_creates_opinion_and_reparents(): void {
		$politics = $this->politics_id();

		$result = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );

		$politics_term = get_term( $politics, 'category' );
		$opinion       = get_term_by( 'slug', 'opinion', 'category' );

		$this->assertNotFalse( $opinion );
		$this->assertSame( (int) $opinion->term_id, (int) $politics_term->parent );

		$redirects = get_option( 'ttm_redirects' );
		$this->assertIsArray( $redirects );
		$froms = array_column( $redirects, 'from' );
		$this->assertContains( '/category/politics/', $froms );
	}

	public function test_politics_child_mode_adds_opinion_to_politics_posts_and_sets_primary(): void {
		$politics = $this->politics_id();
		$post     = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );
		update_post_meta( $post, 'ttm_primary_category', $politics );

		$result = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );

		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'politics', $categories );
		$this->assertContains( 'opinion', $categories );

		$opinion = get_term_by( 'slug', 'opinion', 'category' );
		$this->assertNotFalse( $opinion );
		$this->assertSame( (int) $opinion->term_id, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertSame( 'opinion', \TTM\Core\Meta\PrimaryCategory::slug( $post ) );
	}

	public function test_politics_child_mode_dry_run_reports_post_count_and_writes_nothing(): void {
		$politics = $this->politics_id();
		$post     = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );
		update_post_meta( $post, 'ttm_primary_category', $politics );

		$result = ( new MigrateCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( $post, $result['rows'][0]['post_id'] );

		$this->assertFalse( get_term_by( 'slug', 'opinion', 'category' ) );
		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertNotContains( 'opinion', $categories );
		$this->assertSame( $politics, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
	}

	public function test_politics_dry_run_changes_nothing(): void {
		$politics = $this->politics_id();

		$result = ( new MigrateCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( get_term_by( 'slug', 'opinion', 'category' ) );

		$politics_term = get_term( $politics, 'category' );
		$this->assertSame( 0, (int) $politics_term->parent );
	}

	public function test_politics_tag_mode_tags_posts_and_moves_them_to_opinion(): void {
		$politics = $this->politics_id();
		$post     = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );

		$result = ( new MigrateCommand() )->run( [], [ 'to' => 'tag' ] );

		$this->assertTrue( $result['ok'] );

		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'opinion', $categories );
		$this->assertNotContains( 'politics', $categories );

		$tags = wp_get_post_tags( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'politics', $tags );

		$this->assertNull( term_exists( 'politics', 'category' ) );
	}

	public function test_redirects_nginx_format(): void {
		$this->politics_id();
		( new MigrateCommand() )->run( [], [] );

		$result = ( new MigrateCommand() )->redirects( [], [ 'format' => 'nginx' ] );

		$this->assertTrue( $result['ok'] );
		$joined = implode( "\n", $result['messages'] );
		$this->assertStringContainsString( 'rewrite ^/category/politics/(.*)$ /category/opinion/politics/$1 permanent;', $joined );
	}

	public function test_redirects_json_format(): void {
		$this->politics_id();
		( new MigrateCommand() )->run( [], [] );

		$result = ( new MigrateCommand() )->redirects( [], [ 'format' => 'json' ] );

		$this->assertTrue( $result['ok'] );
		$decoded = json_decode( $result['messages'][0], true );
		$this->assertIsArray( $decoded );
		$froms = array_column( $decoded, 'from' );
		$this->assertContains( '/category/politics/', $froms );
	}

	public function test_close_comments_closes_all_and_default(): void {
		$post = self::factory()->post->create( [ 'comment_status' => 'open' ] );
		$page = self::factory()->post->create(
			[
				'post_type'      => 'page',
				'comment_status' => 'open',
			]
		);

		$result = ( new MigrateCommand() )->close_comments( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'closed', get_post( $post )->comment_status );
		$this->assertSame( 'closed', get_post( $post )->ping_status );
		$this->assertSame( 'closed', get_post( $page )->comment_status );
		$this->assertSame( 'closed', get_option( 'default_comment_status' ) );
		$this->assertSame( 'closed', get_option( 'default_ping_status' ) );
	}

	public function test_close_comments_fires_purge_once(): void {
		$post_ids = self::factory()->post->create_many( 5, [ 'comment_status' => 'open' ] );

		$fired = 0;
		add_action(
			'ttm_purge_urls',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$result = ( new MigrateCommand() )->close_comments( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 1, $fired );

		foreach ( $post_ids as $post_id ) {
			$this->assertSame( 'closed', get_post( $post_id )->comment_status );
			$this->assertSame( 'closed', get_post( $post_id )->ping_status );
		}
	}

	public function test_close_comments_dry_run_fires_no_purge(): void {
		self::factory()->post->create_many( 3, [ 'comment_status' => 'open' ] );

		$fired = 0;
		add_action(
			'ttm_purge_urls',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		( new MigrateCommand() )->close_comments( [], [ 'dry-run' => true ] );

		$this->assertSame( 0, $fired );
	}

	public function test_politics_is_idempotent(): void {
		$this->politics_id();

		$first  = ( new MigrateCommand() )->run( [], [] );
		$second = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $first['ok'] );
		$this->assertTrue( $second['ok'] );

		$redirects = get_option( 'ttm_redirects' );
		$this->assertCount( 2, $redirects );
	}

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * P2-03, SPEC §6.7: an empty excerpt on a non-Journal post gets the Yoast meta description;
	 * a Journal-primary post is excluded even with an empty excerpt and a Yoast description.
	 */
	public function test_excerpts_from_yoast_fills_only_empty_non_journal_excerpts(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A short meta description.' );

		$journal = $this->category_id( 'journal', 'Journal' );
		$jpost   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $jpost, 'ttm_primary_category', $journal );
		update_post_meta( $jpost, '_yoast_wpseo_metadesc', 'A journal meta description.' );

		$result = ( new MigrateCommand() )->excerpts( [], [ 'from' => 'yoast' ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'A short meta description.', get_post( $post )->post_excerpt );
		$this->assertSame( '', get_post( $jpost )->post_excerpt );

		$ids = array_column( $result['rows'], 'post_id' );
		$this->assertContains( $post, $ids );
		$this->assertNotContains( $jpost, $ids );
	}

	public function test_excerpts_never_overwrite(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => 'A hand-written excerpt.',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A short meta description.' );

		( new MigrateCommand() )->excerpts( [], [ 'from' => 'yoast' ] );

		$this->assertSame( 'A hand-written excerpt.', get_post( $post )->post_excerpt );
	}

	public function test_excerpts_dry_run_writes_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A short meta description.' );

		$result = ( new MigrateCommand() )->excerpts(
			[],
			[
				'from'    => 'yoast',
				'dry-run' => true,
			]
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( '', get_post( $post )->post_excerpt );
		$this->assertStringContainsString( 'Would fill 1 excerpt(s) from Yoast.', $result['messages'][0] );
	}

	public function test_excerpts_are_truncated_to_excerpt_length(): void {
		$tech  = $this->category_id( 'technology', 'Technology' );
		$words = [];
		for ( $i = 1; $i <= 80; $i++ ) {
			$words[] = "word{$i}";
		}
		$long_desc = implode( ' ', $words ); // no terminal punctuation at all -> hard cut.

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', $long_desc );

		( new MigrateCommand() )->excerpts( [], [ 'from' => 'yoast' ] );

		$excerpt = get_post( $post )->post_excerpt;
		$this->assertStringEndsWith( '…', $excerpt );
		$this->assertLessThanOrEqual(
			(int) \TTM\Core\Config::get( 'excerpt_length', 55 ) + 1, // +1: the trailing "…" isn't a counted word but splits oddly on whitespace.
			count( preg_split( '/\s+/', trim( $excerpt ) ) )
		);
	}
}
