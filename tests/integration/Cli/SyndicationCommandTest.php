<?php
/**
 * Integration tests for the P8-05 spike CLI command core (migrate:syndication).
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\SyndicationCommand;

class SyndicationCommandTest extends TTM_IntegrationTestCase {

	public function test_reads_publicize_done_external_array_into_syndication(): void {
		$post = self::factory()->post->create();
		update_post_meta(
			$post,
			'_publicize_done_external',
			[
				'twitter'  => [ '1' => 'https://x.example/1' ],
				'mastodon' => [ '2' => 'https://mastodon.example/2' ],
			]
		);

		$result = ( new SyndicationCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );

		$stored = get_post_meta( $post, 'ttm_syndication', true );
		$this->assertSame( 'https://x.example/1', $stored['x'] );
		$this->assertSame( 'https://mastodon.example/2', $stored['mastodon'] );
	}

	public function test_skips_non_https_and_unknown_services(): void {
		$post = self::factory()->post->create();
		update_post_meta(
			$post,
			'_publicize_done_external',
			[
				'twitter'  => [ '1' => 'http://x.example/insecure' ],
				'facebook' => [ '2' => 'https://facebook.example/should-not-map' ],
			]
		);

		( new SyndicationCommand() )->run( [], [] );

		$stored = get_post_meta( $post, 'ttm_syndication', true );
		$this->assertArrayNotHasKey( 'x', (array) $stored );
		$this->assertArrayNotHasKey( 'facebook', (array) $stored );
	}

	public function test_does_not_overwrite_existing_syndication(): void {
		$post = self::factory()->post->create();
		update_post_meta( $post, 'ttm_syndication', [ 'x' => 'https://x.example/already-set' ] );
		update_post_meta(
			$post,
			'_publicize_done_external',
			[ 'bluesky' => [ '1' => 'https://bsky.example/new' ] ]
		);

		( new SyndicationCommand() )->run( [], [] );

		$stored = get_post_meta( $post, 'ttm_syndication', true );
		$this->assertSame( [ 'x' => 'https://x.example/already-set' ], $stored );
	}

	public function test_dry_run_writes_nothing(): void {
		$post = self::factory()->post->create();
		update_post_meta(
			$post,
			'_publicize_done_external',
			[ 'bluesky' => [ '1' => 'https://bsky.example/new' ] ]
		);

		$result = ( new SyndicationCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertNotEmpty( $result['rows'] );
		$this->assertEmpty( get_post_meta( $post, 'ttm_syndication', true ) );
	}

	public function test_string_meta_values_are_ignored(): void {
		$post = self::factory()->post->create();
		// A raw string (not the expected unserialized array) must never be parsed - rule 15:
		// never call unserialize() ourselves to "fix" it, just skip it.
		update_post_meta( $post, '_publicize_done_external', 'a:1:{s:7:"twitter";...}' );

		$result = ( new SyndicationCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertEmpty( get_post_meta( $post, 'ttm_syndication', true ) );
	}
}
