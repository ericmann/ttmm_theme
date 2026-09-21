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
}
