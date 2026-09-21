<?php
/**
 * Integration tests for TTM\Core\Bindings\Sources.
 *
 * @package TTM\Tests\Integration\Bindings
 */

declare( strict_types=1 );

class FrontSourcesTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function source_value( string $source, array $source_args, object $block, string $attribute_name ): string {
		$registered = WP_Block_Bindings_Registry::get_instance()->get_registered( $source );

		return (string) $registered->get_value( $source_args, $block, $attribute_name );
	}

	/**
	 * A minimal stand-in for a `WP_Block` instance, exposing only what `Sources` reads
	 * (`->name` and `->context`). A real `WP_Block` would drop `postId`/`postType` from its
	 * context here, since `core/paragraph`/`core/heading` only gain those in `uses_context`
	 * when WordPress itself detects a `metadata.bindings` entry naming one of our sources —
	 * this stub exercises `Sources`' own logic directly, the same context shape WordPress
	 * would supply once that detection has happened.
	 */
	private function make_block( string $block_name, int $post_id ): object {
		return new class( $block_name, $post_id ) {
			public string $name;
			public array $context;

			public function __construct( string $name, int $post_id ) {
				$this->name    = $name;
				$this->context = [
					'postId'   => $post_id,
					'postType' => 'post',
				];
			}
		};
	}

	public function test_six_sources_are_registered(): void {
		$registry = WP_Block_Bindings_Registry::get_instance();

		foreach ( [ 'ttm/kicker', 'ttm/meta-line', 'ttm/short-date', 'ttm/relative-date', 'ttm/category-count', 'ttm/today' ] as $source ) {
			$this->assertTrue( $registry->is_registered( $source ), "{$source} is not registered" );
		}
	}

	public function test_kicker_binding_renders_in_paragraph_with_post_context(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$block = $this->make_block( 'core/paragraph', $post );
		$value = $this->source_value( 'ttm/kicker', [], $block, 'content' );

		$this->assertSame( 'Technology', $value );
	}

	public function test_kicker_politics_suffix_follows_sections_politics_slug(): void {
		// Rename the politics slug via the `ttm_config` filter (SPEC rule 24: the slug is a
		// Config key, not a hard-coded 'politics' literal) and confirm the kicker still
		// appends the "Politics" suffix for a post whose primary category is the renamed term.
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['sections.politics_slug'] = 'renamed-politics';
				return $config;
			}
		);
		\TTM\Core\Config::reset();

		$politics = $this->category_id( 'renamed-politics', 'Political Takes' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $politics ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $politics );

		$block = $this->make_block( 'core/paragraph', $post );
		$value = $this->source_value( 'ttm/kicker', [], $block, 'content' );

		$this->assertSame( 'Political Takes · Politics', $value );
	}

	/**
	 * Rule 26: `ttm/short-date` and `ttm/relative-date` must resolve to `''` (never render a
	 * "blank" bound attribute or a warning) with no `postId` in context, and with a `postId`
	 * that does not resolve to a post -- both cases `Sources::context_post()` handles via its
	 * `if ( ! $post ) return '';` guards in `short_date()`/`relative_date()`. This asserts at
	 * the source level (not `Values`, which never sees a missing post at all) so removing
	 * either guard fails here.
	 */
	public function test_short_date_and_relative_date_empty_without_post(): void {
		$no_context_block = new class() {
			public string $name = 'core/paragraph';
			public array $context = [];
		};

		$this->assertSame( '', $this->source_value( 'ttm/short-date', [], $no_context_block, 'content' ) );
		$this->assertSame( '', $this->source_value( 'ttm/relative-date', [], $no_context_block, 'content' ) );

		$missing_post_block = $this->make_block( 'core/paragraph', 999999999 );

		$this->assertSame( '', $this->source_value( 'ttm/short-date', [], $missing_post_block, 'content' ) );
		$this->assertSame( '', $this->source_value( 'ttm/relative-date', [], $missing_post_block, 'content' ) );
	}

	public function test_meta_line_html_is_stripped_outside_paragraph_content(): void {
		$post = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		// A block other than core/paragraph: SPEC §6.3 says only core/paragraph's `content`
		// attribute may carry the ttm/meta-line link; everywhere else it is plain text.
		$block = $this->make_block( 'core/heading', $post );

		update_post_meta( $post, 'ttm_word_count', 460 );
		$value = $this->source_value( 'ttm/meta-line', [ 'parts' => [ 'reading' ] ], $block, 'content' );

		$this->assertStringNotContainsString( '<', $value );
	}

	public function test_category_count_binding_reads_term_count(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create_many(
			3,
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			] 
		);

		$block = $this->make_block( 'core/paragraph', 0 );
		$value = $this->source_value(
			'ttm/category-count',
			[
				'category' => 'technology',
				'format'   => 'short',
			],
			$block,
			'content'
		);

		$this->assertSame( '3 →', $value );
	}

	public function tear_down(): void {
		delete_option( 'ttm_verse' );
		\TTM\Core\Config::reset();
		parent::tear_down();
	}

	public function test_verse_copyright_returns_notice_when_placement_is_footer(): void {
		update_option( 'ttm_verse', [ 'copyright' => 'Copyright notice.' ] );

		$block = $this->make_block( 'core/paragraph', 0 );
		$value = $this->source_value( 'ttm/verse-copyright', [], $block, 'content' );

		$this->assertSame( 'Copyright notice.', $value );
	}

	public function test_verse_copyright_is_empty_without_verse_or_when_placement_is_box_or_none(): void {
		$block = $this->make_block( 'core/paragraph', 0 );

		// No verse stored at all.
		delete_option( 'ttm_verse' );
		$this->assertSame( '', $this->source_value( 'ttm/verse-copyright', [], $block, 'content' ) );

		// A verse stored, but placement isn't 'footer'.
		update_option( 'ttm_verse', [ 'copyright' => 'Copyright notice.' ] );

		foreach ( [ 'box', 'none' ] as $placement ) {
			add_filter(
				'ttm_config',
				static function ( array $config ) use ( $placement ): array {
					$config['verse.copyright_placement'] = $placement;
					return $config;
				}
			);
			\TTM\Core\Config::reset();

			$this->assertSame( '', $this->source_value( 'ttm/verse-copyright', [], $block, 'content' ) );

			remove_all_filters( 'ttm_config' );
			\TTM\Core\Config::reset();
		}
	}

	public function test_today_footer_format_uses_blogname(): void {
		update_option( 'blogname', 'These Things Matter' );

		$block = $this->make_block( 'core/paragraph', 0 );
		$value = $this->source_value( 'ttm/today', [ 'format' => 'footer' ], $block, 'content' );

		$this->assertMatchesRegularExpression(
			'/^These Things Matter · © \d{4} Eric Mann · Built on WordPress$/',
			$value
		);
	}
}
