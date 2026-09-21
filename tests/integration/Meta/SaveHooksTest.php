<?php
/**
 * Integration tests for the save_post_post derivation hooks (primary category, form, word count).
 *
 * @package TTM\Tests\Integration\Meta
 */

declare( strict_types=1 );

class SaveHooksTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_save_sets_primary_category_in_nav_order(): void {
		$security   = $this->category_id( 'security', 'Security' );
		$technology = $this->category_id( 'technology', 'Technology' );

		$post_id = self::factory()->post->create( [ 'post_category' => [ $security, $technology ] ] );

		$this->assertSame( $technology, (int) get_post_meta( $post_id, 'ttm_primary_category', true ) );
	}

	public function test_save_does_not_overwrite_manual_primary(): void {
		$technology = $this->category_id( 'technology', 'Technology' );
		$business   = $this->category_id( 'business', 'Business' );

		$post_id = self::factory()->post->create( [ 'post_category' => [ $technology, $business ] ] );
		update_post_meta( $post_id, 'ttm_primary_category', $business );

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated',
			] 
		);

		$this->assertSame( $business, (int) get_post_meta( $post_id, 'ttm_primary_category', true ) );
	}

	public function test_save_derives_story_form_for_writing_post(): void {
		$writing = $this->category_id( 'writing', 'Writing' );

		$post_id = self::factory()->post->create( [ 'post_category' => [ $writing ] ] );

		$this->assertSame( 'story', get_post_meta( $post_id, 'ttm_form', true ) );
	}

	public function test_locked_form_is_kept(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'ttm_form', 'chapter' );
		update_post_meta( $post_id, 'ttm_form_locked', true );

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Updated again',
			] 
		);

		$this->assertSame( 'chapter', get_post_meta( $post_id, 'ttm_form', true ) );
	}

	public function test_word_count_ignores_code_blocks(): void {
		$content = '<p>One two three</p><!-- wp:code --><pre><code>ignored words here</code></pre><!-- /wp:code -->';

		$post_id = self::factory()->post->create( [ 'post_content' => $content ] );

		$this->assertSame( 3, (int) get_post_meta( $post_id, 'ttm_word_count', true ) );
	}

	public function test_autosave_does_not_write_meta(): void {
		$post_id = self::factory()->post->create();
		delete_post_meta( $post_id, 'ttm_word_count' );

		require_once ABSPATH . 'wp-admin/includes/post.php';

		$autosave_id = wp_create_post_autosave(
			[
				'post_ID'      => $post_id,
				'post_type'    => 'post',
				'post_content' => 'Autosave words should not be counted here at all',
				'post_title'   => 'Autosave',
			]
		);

		$this->assertNotFalse( $autosave_id );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ttm_word_count', true ) );
	}
}
