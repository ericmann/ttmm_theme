<?php
/**
 * Integration tests for the ttm/book-grid block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class BookGridTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		update_option( 'ttm_books', [] );
		parent::tear_down();
	}

	private function attachment(): int {
		return self::factory()->attachment->create_object(
			[
				'file'           => 'book-cover.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			]
		);
	}

	private function render(): string {
		return (string) do_blocks( '<!-- wp:ttm/book-grid /-->' );
	}

	public function test_renders_books_with_cover_caption_and_links(): void {
		update_option(
			'ttm_books',
			[
				[
					'title'    => 'Salt and Iron',
					'form'     => 'novel',
					'year'     => 2022,
					'cover_id' => $this->attachment(),
					'formats'  => [ 'paperback', 'ebook' ],
					'links'    => [
						[
							'label' => 'Buy on Amazon',
							'url'   => 'https://example.com/buy',
						],
					],
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-book', $html );
		$this->assertStringContainsString( 'Salt and Iron', $html );
		$this->assertStringContainsString( 'Novel · 2022 · paperback, ebook', $html );
		$this->assertStringContainsString( 'Buy on Amazon', $html );
		$this->assertStringContainsString( 'rel="noopener"', $html );
		$this->assertStringContainsString( '<figure class="ttm-cover">', $html );
	}

	public function test_book_without_cover_renders_caption_only(): void {
		update_option(
			'ttm_books',
			[
				[
					'title'   => 'The Quiet Ledger',
					'form'    => 'novel',
					'year'    => 2026,
					'formats' => [ 'ebook' ],
					'links'   => [],
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'The Quiet Ledger', $html );
		$this->assertStringNotContainsString( '<figure', $html );
	}

	/**
	 * Decision "Book form label": `collection` renders as "Stories" (the mock's
	 * "Stories · 2019 · paperback"), not the literal "Collection".
	 */
	public function test_form_caption_is_translatable(): void {
		update_option(
			'ttm_books',
			[
				[
					'title'   => 'Eleven Small Doors',
					'form'    => 'collection',
					'year'    => 2019,
					'formats' => [],
					'links'   => [],
				],
			]
		);

		add_filter(
			'gettext',
			static function ( string $translation, string $text, string $domain ) {
				if ( 'ttm-core' === $domain && 'Stories' === $text ) {
					return 'Contes';
				}
				return $translation;
			},
			10,
			3
		);

		$html = $this->render();

		remove_all_filters( 'gettext' );

		$this->assertStringContainsString( 'Contes', $html );
		$this->assertStringNotContainsString( 'Collection', $html );
	}

	public function test_zero_books_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}
}
