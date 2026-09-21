<?php
/**
 * Integration tests for the `series` taxonomy registration and single-term enforcement.
 *
 * @package TTM\Tests\Integration\Taxonomy
 */

declare( strict_types=1 );

class SeriesTaxonomyTest extends TTM_IntegrationTestCase {

	public function test_series_taxonomy_is_registered_with_spec_args(): void {
		$this->assertTrue( taxonomy_exists( 'series' ) );

		$tax = get_taxonomy( 'series' );

		$this->assertFalse( $tax->hierarchical );
		$this->assertTrue( $tax->public );
		$this->assertTrue( $tax->show_in_rest );
		$this->assertSame( 'series', $tax->query_var );
		$this->assertSame( 'series', $tax->rewrite['slug'] );
		$this->assertFalse( $tax->rewrite['with_front'] );
		$this->assertContains( 'series', get_object_taxonomies( 'post' ) );
	}

	public function test_a_post_keeps_only_its_first_series_term(): void {
		$post_id = self::factory()->post->create();
		$a       = self::factory()->term->create(
			[
				'taxonomy' => 'series',
				'name'     => 'Series A',
			] 
		);
		$b       = self::factory()->term->create(
			[
				'taxonomy' => 'series',
				'name'     => 'Series B',
			] 
		);

		wp_set_object_terms( $post_id, [ $a, $b ], 'series' );

		$terms = wp_get_object_terms( $post_id, 'series', [ 'fields' => 'ids' ] );

		$this->assertSame( [ $a ], $terms );
	}
}
