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

	/**
	 * P1-04, Decision "Editor-only story derivation": a non-editor save (WP-CLI/import) never
	 * writes `story` -- a Writing post with no series simply leaves `ttm_form` unset.
	 */
	public function test_non_editor_save_never_writes_story(): void {
		add_filter( 'ttm_form_editor_save', '__return_false' );

		$writing = $this->category_id( 'writing', 'Writing' );
		$post_id = self::factory()->post->create( [ 'post_category' => [ $writing ] ] );

		remove_filter( 'ttm_form_editor_save', '__return_false' );

		// No meta row is written at all (`register_post_meta()`'s own schema default,
		// 'article', is what `get_post_meta()` falls back to -- it's not evidence a
		// non-editor save wrote 'story' or anything else).
		$this->assertFalse( metadata_exists( 'post', $post_id, 'ttm_form' ) );
	}

	/**
	 * P1-04: an editor save still writes `story` for the same Writing-with-no-series shape.
	 */
	public function test_editor_save_writes_story(): void {
		add_filter( 'ttm_form_editor_save', '__return_true' );

		$writing = $this->category_id( 'writing', 'Writing' );
		$post_id = self::factory()->post->create( [ 'post_category' => [ $writing ] ] );

		remove_filter( 'ttm_form_editor_save', '__return_true' );

		$this->assertSame( 'story', get_post_meta( $post_id, 'ttm_form', true ) );
	}

	/**
	 * P1-04: the editor-only gate is specific to the `story` derivation -- `chapter` (series
	 * form fiction) and `article` (everything else) are always written, editor save or not.
	 */
	public function test_non_editor_save_still_writes_chapter_and_article(): void {
		add_filter( 'ttm_form_editor_save', '__return_false' );

		$term      = wp_insert_term( 'A Novel', 'series', [ 'slug' => 'a-novel-savehooks' ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_form', 'novel' );

		$chapter_id = self::factory()->post->create();
		wp_set_object_terms( $chapter_id, [ $series_id ], 'series' );
		wp_update_post(
			[
				'ID'         => $chapter_id,
				'post_title' => 'Re-save',
			] 
		);

		$technology = $this->category_id( 'technology', 'Technology' );
		$article_id = self::factory()->post->create( [ 'post_category' => [ $technology ] ] );

		remove_filter( 'ttm_form_editor_save', '__return_false' );

		$this->assertSame( 'chapter', get_post_meta( $chapter_id, 'ttm_form', true ) );
		$this->assertSame( 'article', get_post_meta( $article_id, 'ttm_form', true ) );
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
