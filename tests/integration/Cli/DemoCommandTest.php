<?php
/**
 * Integration tests for TTM\Core\Cli\DemoCommand.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\DemoCommand;

class DemoCommandTest extends TTM_IntegrationTestCase {

	public function test_options_prints_the_listed_keys_in_order(): void {
		$result = ( new DemoCommand() )->options();

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['messages'] );

		$map = json_decode( $result['messages'][0], true );

		$this->assertSame(
			[ 'blogname', 'blogdescription', 'timezone_string', 'permalink_structure', 'ttm_books', 'ttm_verse', 'ttm_verse_history', 'ttm_settings' ],
			array_keys( $map )
		);
	}

	public function test_options_zeroes_book_cover_ids(): void {
		update_option(
			'ttm_books',
			[
				[
					'title'    => 'Salt Water Wires',
					'cover_id' => 42,
				],
			]
		);

		$result = ( new DemoCommand() )->options();
		$map    = json_decode( $result['messages'][0], true );

		$this->assertSame( 0, $map['ttm_books'][0]['cover_id'] );
	}

	/**
	 * P2-04: a raw series term id, like cover_id, doesn't survive `importWxr` -- zeroed for the
	 * same reason (and to keep two `demo:build` runs on the same day byte-identical).
	 */
	public function test_options_zeroes_book_series_ids(): void {
		update_option(
			'ttm_books',
			[
				[
					'title'     => 'Salt Water Wires',
					'cover_id'  => 42,
					'series_id' => 607,
				],
			]
		);

		$result = ( new DemoCommand() )->options();
		$map    = json_decode( $result['messages'][0], true );

		$this->assertSame( 0, $map['ttm_books'][0]['series_id'] );
	}

	public function test_options_newsletter_provider_is_none(): void {
		update_option(
			'ttm_settings',
			[
				'newsletter' => [
					'provider' => 'jetpack',
					'endpoint' => 'https://example.com/hook',
				],
			]
		);

		$result = ( new DemoCommand() )->options();
		$map    = json_decode( $result['messages'][0], true );

		$this->assertSame(
			[
				'newsletter' => [
					'provider' => 'none',
					'endpoint' => '',
				],
			],
			$map['ttm_settings']
		);
	}

	/**
	 * Counts drawn from the actual seed rather than hard-coded, so this test doesn't drift
	 * every time a fixture row is added.
	 */
	private function seed_and_verify_args(): array {
		$this->seed( 'normal', false );

		$post_counts       = wp_count_posts( 'post' );
		$page_counts       = wp_count_posts( 'page' );
		$attachment_counts = wp_count_posts( 'attachment' );

		return [
			'posts'       => (int) $post_counts->publish + (int) $post_counts->future,
			'pages'       => (int) $page_counts->publish,
			'series'      => count( \TTM\Core\Query\SeriesIndex::all() ),
			'attachments' => (int) $attachment_counts->inherit,
		];
	}

	public function test_verify_passes_on_the_normal_seed_with_matching_counts(): void {
		$assoc = $this->seed_and_verify_args();

		$result = ( new DemoCommand() )->verify( [], $assoc );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'demo:verify: ok', $result['messages'][0] );
	}

	public function test_verify_fails_on_a_count_mismatch(): void {
		$assoc          = $this->seed_and_verify_args();
		$assoc['posts'] = $assoc['posts'] + 1;

		$result = ( new DemoCommand() )->verify( [], $assoc );

		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'post count mismatch', $result['messages'][0] );
	}

	public function test_verify_fails_loudly_on_missing_series_term_meta(): void {
		$assoc = $this->seed_and_verify_args();

		$term = wp_insert_term( 'No Meta Series', 'series' );
		$this->assertIsArray( $term );

		$result = ( new DemoCommand() )->verify( [], $assoc );

		$this->assertFalse( $result['ok'] );
		$found = false;
		foreach ( $result['messages'] as $message ) {
			if ( str_contains( $message, 'missing term meta' ) ) {
				$found = true;
			}
		}
		$this->assertTrue( $found );
	}

	public function test_verify_fails_on_an_uncategorized_primary(): void {
		$assoc = $this->seed_and_verify_args();

		$post_id        = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$assoc['posts'] = $assoc['posts'] + 1;

		$result = ( new DemoCommand() )->verify( [], $assoc );

		$this->assertFalse( $result['ok'] );
		$found = false;
		foreach ( $result['messages'] as $message ) {
			if ( str_contains( $message, "post {$post_id} has an uncategorized primary category" ) ) {
				$found = true;
			}
		}
		$this->assertTrue( $found );
	}

	public function test_verify_fails_with_zero_series(): void {
		$result = ( new DemoCommand() )->verify(
			[],
			[
				'posts'       => 0,
				'pages'       => 0,
				'series'      => 0,
				'attachments' => 0,
			]
		);

		$this->assertFalse( $result['ok'] );
		$found = false;
		foreach ( $result['messages'] as $message ) {
			if ( str_contains( $message, 'series count mismatch' ) ) {
				$found = true;
			}
		}
		$this->assertTrue( $found );
	}
}
