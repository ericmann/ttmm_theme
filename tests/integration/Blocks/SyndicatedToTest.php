<?php
/**
 * Integration tests for the ttm/syndicated-to block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class SyndicatedToTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function render( int $post_id ): string {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		setup_postdata( $post );

		$html = (string) do_blocks( '<!-- wp:ttm/syndicated-to /-->' );

		wp_reset_postdata();

		return $html;
	}

	public function test_renders_networks_as_links_and_word_count(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );
		update_post_meta(
			$post,
			'ttm_syndication',
			[
				'x'        => 'https://x.com/example/1',
				'mastodon' => 'https://mastodon.social/@example/1',
			]
		);
		update_post_meta( $post, 'ttm_word_count', 248 );

		$html = $this->render( $post );

		$this->assertStringContainsString( 'href="https://x.com/example/1"', $html );
		$this->assertStringContainsString( '>X<', $html );
		$this->assertStringContainsString( 'href="https://mastodon.social/@example/1"', $html );
		$this->assertStringContainsString( '>Mastodon<', $html );
		$this->assertStringContainsString( ' and ', $html );
		$this->assertStringContainsString( '248 words', $html );
	}

	public function test_f14_no_urls_renders_nothing(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );

		$html = $this->render( $post );

		$this->assertSame( '', trim( $html ) );
	}

	public function test_non_journal_post_renders_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, 'ttm_syndication', [ 'x' => 'https://x.com/example/1' ] );

		$html = $this->render( $post );

		$this->assertSame( '', trim( $html ) );
	}

	public function test_bluesky_only(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );
		update_post_meta( $post, 'ttm_syndication', [ 'bluesky' => 'https://bsky.app/example/1' ] );

		$html = $this->render( $post );

		$this->assertStringContainsString( '>Bluesky<', $html );
		$this->assertStringNotContainsString( ' and ', $html );
	}
}
