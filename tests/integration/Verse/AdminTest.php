<?php
/**
 * Integration tests for TTM\Core\Verse\Admin.
 *
 * @package TTM\Tests\Integration\Verse
 */

declare( strict_types=1 );

use TTM\Core\Verse\Admin;

class AdminTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_log' );
		$_GET     = [];
		$_REQUEST = [];
		parent::tear_down();
	}

	public function test_fetch_now_requires_nonce_and_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->expectException( WPDieException::class );
		Admin::handle_fetch_now();
	}

	public function test_fetch_now_dies_without_a_valid_nonce(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_GET     = [];
		$_REQUEST = [];

		$this->expectException( WPDieException::class );
		Admin::handle_fetch_now();
	}

	public function test_verse_tab_renders_log_rows(): void {
		update_option(
			'ttm_verse',
			[
				'date'       => '2026-09-20',
				'text'       => 'We wait in hope.',
				'reference'  => 'Psalm 33:20',
				'fetched_at' => '2026-09-20 05:00:00',
			]
		);
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

		ob_start();
		Admin::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'We wait in hope.', $html );
		$this->assertStringContainsString( 'Psalm 33:20', $html );
		$this->assertStringContainsString( 'Verse fetched.', $html );
	}
}
