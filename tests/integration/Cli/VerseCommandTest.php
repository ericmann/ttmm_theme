<?php
/**
 * Integration tests for TTM\Core\Cli\VerseCommand.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\VerseCommand;

class VerseCommandTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_history' );
		delete_option( 'ttm_verse_log' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function fixture_payload(): array {
		$path = TTM\Core\Cli\Seeder::fixtures_root_dir() . '/verse-sample.json';

		return json_decode( (string) file_get_contents( $path ), true );
	}

	public function test_fetch_returns_ok_false_on_http_error(): void {
		add_filter(
			'pre_http_request',
			static fn () => new WP_Error( 'http_request_failed', 'Connection failed.' )
		);

		$result = ( new VerseCommand() )->run( [ 'fetch' ], [] );

		$this->assertFalse( $result['ok'] );
	}

	public function test_inspect_returns_parsed_item(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$body = (string) wp_json_encode( $this->fixture_payload() );
		add_filter(
			'pre_http_request',
			static fn () => [
				'headers'  => [],
				'body'     => $body,
				'response' => [
					'code'    => 200,
					'message' => '',
				],
				'cookies'  => [],
				'filename' => null,
			]
		);

		$result = ( new VerseCommand() )->run( [ 'inspect' ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'Psalm 33:20', $result['messages'][0] );
	}

	public function test_log_lists_entries(): void {
		update_option(
			'ttm_verse_log',
			[
				[
					'at'      => '2026-09-20 05:00:00',
					'ok'      => true,
					'message' => 'Verse fetched.',
				],
			]
		);

		$result = ( new VerseCommand() )->run( [ 'log' ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( 'Verse fetched.', $result['rows'][0]['message'] );
	}
}
