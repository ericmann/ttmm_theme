<?php
/**
 * Integration tests for the P4-04 additions to TTM\Core\Bindings\Sources.
 *
 * @package TTM\Tests\Integration\Bindings
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class ArticleSourcesTest extends TTM_IntegrationTestCase {

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
	 * A minimal stand-in for a `WP_Block` instance exposing only `->name`/`->context` (see
	 * FrontSourcesTest for why: a real `WP_Block` would drop postId/postType from its context
	 * here since core/paragraph doesn't declare them in usesContext by default).
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

	public function test_eleven_sources_registered_after_phase_4(): void {
		$registry = WP_Block_Bindings_Registry::get_instance();

		$sources = [
			'ttm/kicker',
			'ttm/meta-line',
			'ttm/short-date',
			'ttm/relative-date',
			'ttm/category-count',
			'ttm/today',
			'ttm/reading-time',
			'ttm/word-count',
			'ttm/journal-subline',
			'ttm/series-name',
			'ttm/series-part',
		];

		$this->assertCount( 11, $sources );
		foreach ( $sources as $source ) {
			$this->assertTrue( $registry->is_registered( $source ), "{$source} is not registered" );
		}
	}

	public function test_reading_time_binding_reads_post_word_count(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, 'ttm_word_count', 3220 );

		$block = $this->make_block( 'core/paragraph', $post );
		$value = $this->source_value( 'ttm/reading-time', [ 'format' => 'long' ], $block, 'content' );

		$this->assertSame( '14 min read', $value );
	}

	public function test_journal_subline_binding_uses_post_date_weekday(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_date'     => '2026-09-20 09:00:00',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );
		update_post_meta( $post, 'ttm_location', 'Portland' );

		$block = $this->make_block( 'core/paragraph', $post );
		$value = $this->source_value( 'ttm/journal-subline', [], $block, 'content' );

		$this->assertSame( 'Sunday · Portland', $value );
	}

	public function test_newsletter_copy_binding_reads_series_context(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$term      = wp_insert_term( 'Hardening WordPress', 'series', [ 'slug' => 'hardening-wp' ] );
		$series_id = (int) $term['term_id'];

		$in_series = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $in_series, 'ttm_series_part', 1 );
		update_post_meta( $in_series, 'ttm_primary_category', $tech );
		wp_set_object_terms( $in_series, [ $series_id ], 'series' );

		$plain = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $plain, 'ttm_primary_category', $tech );
		SeriesIndex::rebuild();

		$this->go_to( (string) get_permalink( $in_series ) );
		$block = $this->make_block( 'core/paragraph', $in_series );
		$this->assertSame( 'Get the next part', $this->source_value( 'ttm/newsletter-copy', [], $block, 'content' ) );

		$this->go_to( (string) get_permalink( $plain ) );
		$block = $this->make_block( 'core/paragraph', $plain );
		$this->assertSame( 'The weekly issue.', $this->source_value( 'ttm/newsletter-copy', [], $block, 'content' ) );

		$link = get_term_link( $series_id, 'series' );
		if ( is_wp_error( $link ) ) {
			$this->fail( 'Series term link could not be resolved.' );
		}
		$this->go_to( $link );
		$this->assertTrue( is_tax( 'series' ) );
		$block = $this->make_block( 'core/paragraph', 0 );
		$this->assertSame( 'Get the next part', $this->source_value( 'ttm/newsletter-copy', [], $block, 'content' ) );
	}

	public function test_section_label_binding_reads_primary_category(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$security = $this->category_id( 'security', 'Security' );
		$post     = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech, $security ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $security );

		$block = $this->make_block( 'core/heading', $post );
		$this->assertSame( 'More in Security', $this->source_value( 'ttm/section-label', [ 'format' => 'more-in' ], $block, 'content' ) );

		$none = $this->make_block( 'core/heading', 0 );
		$this->assertSame( '', $this->source_value( 'ttm/section-label', [ 'format' => 'more-in' ], $none, 'content' ) );

		$this->go_to( (string) get_category_link( $tech ) );
		$this->assertSame( 'Series in Technology', $this->source_value( 'ttm/section-label', [ 'format' => 'series-in' ], $none, 'content' ) );
	}
}
