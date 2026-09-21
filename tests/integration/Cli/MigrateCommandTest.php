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

	public function test_politics_is_idempotent(): void {
		$this->politics_id();

		$first  = ( new MigrateCommand() )->run( [], [] );
		$second = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $first['ok'] );
		$this->assertTrue( $second['ok'] );

		$redirects = get_option( 'ttm_redirects' );
		$this->assertCount( 2, $redirects );
	}
}
