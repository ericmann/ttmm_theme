<?php
/**
 * Integration tests for the `ttm/author-name` block-binding source (P0-02, SPEC §6.7).
 *
 * @package TTM\Tests\Integration\Bindings
 */

declare( strict_types=1 );

use TTM\Core\Config;

class AuthorNameSourceTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_filters( 'ttm_config' );
		remove_all_filters( 'pre_option_blogname' );
		Config::reset();
		parent::tear_down();
	}

	private function source_value( array $source_args, object $block, string $attribute_name ): string {
		$registered = WP_Block_Bindings_Registry::get_instance()->get_registered( 'ttm/author-name' );

		return (string) $registered->get_value( $source_args, $block, $attribute_name );
	}

	private function make_block( string $block_name ): object {
		return new class( $block_name ) {
			public string $name;
			public array $context = [];

			public function __construct( string $name ) {
				$this->name = $name;
			}
		};
	}

	private function filter_name( string $name ): void {
		Config::reset();
		add_filter(
			'ttm_config',
			static function ( array $config ) use ( $name ): array {
				$config['site.author_name'] = $name;
				return $config;
			}
		);
	}

	public function test_by_format_normal(): void {
		$value = $this->source_value( [ 'format' => 'by' ], $this->make_block( 'core/paragraph' ), 'content' );

		$this->assertSame( 'by ' . Config::author_name(), $value );
	}

	public function test_by_format_empty_name_renders_nothing(): void {
		$this->filter_name( '' );

		$value = $this->source_value( [ 'format' => 'by' ], $this->make_block( 'core/paragraph' ), 'content' );

		$this->assertSame( '', $value );
	}

	private function filter_title( string $title ): void {
		add_filter(
			'pre_option_blogname',
			static function () use ( $title ): string {
				return $title;
			}
		);
	}

	public function test_unless_in_title_hides_byline_when_title_names_author(): void {
		$this->filter_name( 'Ada Example' );
		$this->filter_title( 'Ada Example&#039;s Blog' );
		$paragraph = $this->make_block( 'core/paragraph' );

		foreach ( [ 'by', 'byline-link' ] as $format ) {
			$args = [
				'format'        => $format,
				'unlessInTitle' => true,
			];
			$this->assertSame( '', $this->source_value( $args, $paragraph, 'content' ), $format );
		}

		// Without the arg the byline still renders.
		$this->assertSame( 'by Ada Example', $this->source_value( [ 'format' => 'by' ], $paragraph, 'content' ) );
	}

	public function test_unless_in_title_keeps_byline_when_title_does_not_name_author(): void {
		$this->filter_name( 'Ada Example' );
		$this->filter_title( 'These Things Matter' );
		$args = [
			'format'        => 'by',
			'unlessInTitle' => true,
		];

		$this->assertSame( 'by Ada Example', $this->source_value( $args, $this->make_block( 'core/paragraph' ), 'content' ) );
	}

	public function test_masthead_byline_hidden_when_title_names_author(): void {
		$this->filter_name( 'Ada Example' );
		$this->filter_title( 'Ada Example&#039;s Blog' );

		ob_start();
		require get_template_directory() . '/patterns/masthead-inner.php';
		$inner_html = (string) do_blocks( (string) ob_get_clean() );
		$this->assertStringNotContainsString( 'by Ada Example', $inner_html );

		ob_start();
		require get_template_directory() . '/patterns/masthead-front.php';
		$front_html = (string) do_blocks( (string) ob_get_clean() );
		$this->assertStringNotContainsString( '>Ada Example</a>', $front_html );
	}

	public function test_byline_link_keeps_html_only_in_paragraph_content(): void {
		$paragraph_content = $this->source_value( [ 'format' => 'byline-link' ], $this->make_block( 'core/paragraph' ), 'content' );
		$this->assertStringContainsString( '<a href=', $paragraph_content );

		$heading_content = $this->source_value( [ 'format' => 'byline-link' ], $this->make_block( 'core/heading' ), 'content' );
		$this->assertStringNotContainsString( '<a', $heading_content );
		$this->assertStringContainsString( Config::author_name(), $heading_content );
	}

	public function test_filtered_name_reaches_footer_and_both_mastheads(): void {
		$this->filter_name( 'Ada Example' );

		$footer_file = locate_template( 'parts/footer.html' );
		$this->assertNotSame( '', $footer_file );
		$footer_html = (string) do_blocks( (string) file_get_contents( $footer_file ) );
		$this->assertStringContainsString( 'Ada Example', $footer_html );

		ob_start();
		require get_template_directory() . '/patterns/masthead-inner.php';
		$inner_raw  = (string) ob_get_clean();
		$inner_html = (string) do_blocks( $inner_raw );
		$this->assertStringContainsString( 'by Ada Example', $inner_html );

		ob_start();
		require get_template_directory() . '/patterns/masthead-front.php';
		$front_raw  = (string) ob_get_clean();
		$front_html = (string) do_blocks( $front_raw );
		$this->assertStringContainsString( 'Ada Example', $front_html );
	}
}
