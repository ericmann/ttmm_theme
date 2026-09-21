<?php
/**
 * Integration tests for the Books settings tab.
 *
 * @package TTM\Tests\Integration\Fiction
 */

declare( strict_types=1 );

use TTM\Core\Admin\Page;
use TTM\Core\Fiction\Books;

class BooksSettingsTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		$_POST    = [];
		$_REQUEST = [];
		parent::tear_down();
	}

	public function test_books_tab_is_registered(): void {
		$this->assertContains( 'books', Page::tab_slugs() );
	}

	public function test_save_persists_books_and_purge_fires(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$_POST    = [
			'tab'       => 'books',
			'_wpnonce'  => wp_create_nonce( 'ttm_settings' ),
			'ttm_books' => [
				[
					'title'   => 'The Long Winter',
					'form'    => 'novel',
					'year'    => '2024',
					'formats' => 'paperback, ebook',
					'links'   => [
						[
							'label' => 'Amazon',
							'url'   => 'https://amazon.com/book',
						],
					],
				],
			],
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test fixture mirrors $_POST into $_REQUEST for check_admin_referer().

		$before = did_action( 'ttm_purge_urls' );

		Page::handle_save();

		$this->assertGreaterThan( $before, did_action( 'ttm_purge_urls' ) );

		$books = Books::all();
		$this->assertCount( 1, $books );
		$this->assertSame( 'The Long Winter', $books[0]['title'] );
		$this->assertSame( [ 'paperback', 'ebook' ], $books[0]['formats'] );
	}
}
