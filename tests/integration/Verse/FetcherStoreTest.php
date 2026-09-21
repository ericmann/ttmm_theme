<?php
/**
 * Integration tests for TTM\Core\Verse\Fetcher's stateful fetch/store/log.
 *
 * @package TTM\Tests\Integration\Verse
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;
use TTM\Core\Verse\Fetcher;

class FetcherStoreTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_history' );
		delete_option( 'ttm_verse_log' );
		remove_all_filters( 'pre_http_request' );
		remove_all_actions( 'ttm_purge_urls' );
		parent::tear_down();
	}

	private function fixture_payload(): array {
		$path = Seeder::fixtures_root_dir() . '/verse-sample.json';

		return json_decode( (string) file_get_contents( $path ), true );
	}

	private function mock_http_response( int $code, string $body, array $headers = [] ): void {
		add_filter(
			'pre_http_request',
			static fn () => [
				'headers'  => $headers,
				'body'     => $body,
				'response' => [
					'code'    => $code,
					'message' => '',
				],
				'cookies'  => [],
				'filename' => null,
			]
		);
	}

	public function test_fetch_stores_verse_and_fires_purge(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->mock_http_response( 200, (string) wp_json_encode( $this->fixture_payload() ), [ 'etag' => '"abc"' ] );

		$purged = [];
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			} 
		);

		$result = Fetcher::fetch();

		$this->assertTrue( $result['ok'] );

		$verse = get_option( 'ttm_verse' );
		$this->assertSame( '2026-09-20', $verse['date'] );
		$this->assertSame( 'Psalm 33:20-22', $verse['reference'] );
		$this->assertSame( '"abc"', $verse['etag'] );
		$this->assertNotEmpty( $purged );
	}

	public function test_failed_fetch_keeps_previous_and_logs(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		update_option(
			'ttm_verse',
			[
				'date'      => '2026-09-19',
				'text'      => 'Previous verse.',
				'reference' => 'Ref',
				'title'     => '',
				'url'       => '',
				'source_id' => 'prev',
				'copyright' => '',
			]
		);

		$this->mock_http_response( 500, 'Server error' );

		$result = Fetcher::fetch( true );

		$this->assertFalse( $result['ok'] );

		$verse = get_option( 'ttm_verse' );
		$this->assertSame( 'Previous verse.', $verse['text'] );

		$log = get_option( 'ttm_verse_log' );
		$this->assertFalse( $log[0]['ok'] );
	}

	public function test_history_is_capped_and_newest_first(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		for ( $i = 1; $i <= 3; $i++ ) {
			Fetcher::store(
				[
					'date'       => "2026-09-1{$i}",
					'text'       => "Verse {$i}",
					'reference'  => 'Ref',
					'title'      => '',
					'url'        => '',
					'source_id'  => "id-{$i}",
					'copyright'  => '',
					'fetched_at' => '2026-09-20 12:00:00',
					'etag'       => '',
				]
			);
		}

		$history = get_option( 'ttm_verse_history' );

		$this->assertSame( 'Verse 2', $history[0]['text'] );
		$this->assertSame( 'Verse 1', $history[1]['text'] );

		$current = get_option( 'ttm_verse' );
		$this->assertSame( 'Verse 3', $current['text'] );
	}

	public function test_304_keeps_current_and_logs_ok(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		update_option(
			'ttm_verse',
			[
				'date'      => '2026-09-19',
				'text'      => 'Unchanged verse.',
				'reference' => 'Ref',
				'title'     => '',
				'url'       => '',
				'source_id' => 'same',
				'copyright' => '',
				'etag'      => '"same-etag"',
			]
		);

		$this->mock_http_response( 304, '' );

		$result = Fetcher::fetch( true );

		$this->assertTrue( $result['ok'] );

		$verse = get_option( 'ttm_verse' );
		$this->assertSame( 'Unchanged verse.', $verse['text'] );

		$log = get_option( 'ttm_verse_log' );
		$this->assertTrue( $log[0]['ok'] );
	}
}
