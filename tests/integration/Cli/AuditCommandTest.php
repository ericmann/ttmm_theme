<?php
/**
 * Integration tests for the P8-03 `wp ttm audit` CLI command core.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\AuditCommand;

class AuditCommandTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function flags_for( array $result, int $post_id ): array {
		foreach ( $result['rows'] as $row ) {
			if ( $row['id'] === $post_id ) {
				return $row['flags'];
			}
		}

		return [];
	}

	private function detail_for( array $result, int $post_id ): array {
		foreach ( $result['rows'] as $row ) {
			if ( $row['id'] === $post_id ) {
				return $row['detail'];
			}
		}

		return [];
	}

	public function test_classic_flag_on_the_seeded_classic_post(): void {
		$classic = self::factory()->post->create( [ 'post_content' => '<p>Classic content.</p>' ] );
		$block   = self::factory()->post->create( [ 'post_content' => "<!-- wp:paragraph -->\n<p>Block.</p>\n<!-- /wp:paragraph -->" ] );

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'classic', $this->flags_for( $result, $classic ) );
		$this->assertNotContains( 'classic', $this->flags_for( $result, $block ) );
	}

	public function test_no_excerpt_and_no_featured_image_flags(): void {
		$bare = self::factory()->post->create( [ 'post_excerpt' => '' ] );

		$result = ( new AuditCommand() )->run( [], [] );
		$flags  = $this->flags_for( $result, $bare );

		$this->assertContains( 'no-excerpt', $flags );
		$this->assertContains( 'no-featured-image', $flags );
	}

	public function test_multi_category_lists_categories(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$post = self::factory()->post->create( [ 'post_category' => [ $tech, $business ] ] );

		$result = ( new AuditCommand() )->run( [], [] );

		$row = null;
		foreach ( $result['rows'] as $candidate ) {
			if ( $candidate['id'] === $post ) {
				$row = $candidate;
			}
		}

		$this->assertNotNull( $row );
		$this->assertContains( 'multi-category', $row['flags'] );
		$this->assertContains( 'technology', $row['detail']['categories'] );
		$this->assertContains( 'business', $row['detail']['categories'] );
	}

	public function test_politics_flag_for_child_category_posts(): void {
		$politics = $this->category_id( 'politics', 'Politics' );
		$other    = $this->category_id( 'technology', 'Technology' );

		$politics_post = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );
		$other_post    = self::factory()->post->create( [ 'post_category' => [ $other ] ] );

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'politics', $this->flags_for( $result, $politics_post ) );
		$this->assertNotContains( 'politics', $this->flags_for( $result, $other_post ) );
	}

	public function test_series_tag_candidate_flags_tags_with_three_or_more_posts(): void {
		wp_insert_term( 'popular-tag', 'post_tag', [ 'slug' => 'popular-tag' ] );
		wp_insert_term( 'rare-tag', 'post_tag', [ 'slug' => 'rare-tag' ] );

		$popular_posts = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$popular_posts[] = self::factory()->post->create( [ 'tags_input' => [ 'popular-tag' ] ] );
		}
		$rare_post = self::factory()->post->create( [ 'tags_input' => [ 'rare-tag' ] ] );

		$result = ( new AuditCommand() )->run( [], [] );

		foreach ( $popular_posts as $post_id ) {
			$this->assertContains( 'series-tag-candidate', $this->flags_for( $result, $post_id ) );
		}
		$this->assertNotContains( 'series-tag-candidate', $this->flags_for( $result, $rare_post ) );
	}

	public function test_broken_internal_link_uses_local_index_only(): void {
		add_filter(
			'pre_http_request',
			static function () {
				throw new Exception( 'HTTP must never be reached by convert:audit.' );
			}
		);

		$target = self::factory()->post->create( [ 'post_name' => 'target-post' ] );
		$broken = self::factory()->post->create(
			[
				'post_content' => sprintf(
					'<p>See <a href="%s">this</a> and <a href="%s">this</a>.</p>',
					get_permalink( $target ),
					home_url( '/no-such-post/' )
				),
			]
		);
		$clean  = self::factory()->post->create(
			[
				'post_content' => sprintf( '<p>See <a href="%s">this</a>.</p>', get_permalink( $target ) ),
			]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		remove_all_filters( 'pre_http_request' );

		$this->assertContains( 'broken-internal-link', $this->flags_for( $result, $broken ) );
		$this->assertNotContains( 'broken-internal-link', $this->flags_for( $result, $clean ) );
	}

	public function test_missing_alt_accepts_alt_text_made_of_a_l_t_letters(): void {
		// Regression: the previous implementation trimmed the whole `alt="tall"` match against
		// the charlist "alt=\"' ", and every letter of "tall" is in that charlist -- the value
		// itself got trimmed away, flagging real alt text as missing.
		$has_alt = self::factory()->post->create( [ 'post_content' => '<p><img src="x.jpg" alt="tall"></p>' ] );
		$no_alt  = self::factory()->post->create( [ 'post_content' => '<p><img src="x.jpg" alt=""></p>' ] );

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertNotContains( 'missing-alt', $this->flags_for( $result, $has_alt ) );
		$this->assertContains( 'missing-alt', $this->flags_for( $result, $no_alt ) );
	}

	public function test_broken_internal_link_ignores_uploads_feeds_and_pagination(): void {
		add_filter(
			'pre_http_request',
			static function () {
				throw new Exception( 'HTTP must never be reached by convert:audit.' );
			}
		);

		$upload_path = (string) wp_parse_url( (string) wp_get_upload_dir()['baseurl'], PHP_URL_PATH );

		$post = self::factory()->post->create(
			[
				'post_content' => sprintf(
					'<p><a href="%1$s">upload</a> <a href="%2$s">feed</a> <a href="%3$s">page 2</a> <a href="%4$s">date archive</a></p>',
					home_url( $upload_path . '/2026/09/photo.jpg' ),
					home_url( '/feed/' ),
					home_url( '/category/technology/page/2/' ),
					home_url( '/2026/09/' )
				),
			]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		remove_all_filters( 'pre_http_request' );

		$this->assertNotContains( 'broken-internal-link', $this->flags_for( $result, $post ) );
	}

	public function test_broken_internal_link_indexes_attachment_permalinks(): void {
		add_filter(
			'pre_http_request',
			static function () {
				throw new Exception( 'HTTP must never be reached by convert:audit.' );
			}
		);

		$attachment_id = self::factory()->attachment->create_object(
			[
				'file'           => 'cover.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			]
		);

		$post = self::factory()->post->create(
			[
				'post_content' => sprintf( '<p><a href="%s">the image</a></p>', get_permalink( $attachment_id ) ),
			]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		remove_all_filters( 'pre_http_request' );

		$this->assertNotContains( 'broken-internal-link', $this->flags_for( $result, $post ) );
	}

	public function test_remote_image_flag_for_off_origin_img(): void {
		$upload_path = (string) wp_parse_url( (string) wp_get_upload_dir()['baseurl'], PHP_URL_PATH );

		$remote = self::factory()->post->create(
			[ 'post_content' => '<p><img src="https://cdn.example.com/photo.jpg" alt=""></p>' ]
		);
		$local  = self::factory()->post->create(
			[ 'post_content' => sprintf( '<p><img src="%s" alt=""></p>', home_url( $upload_path . '/2026/09/photo.jpg' ) ) ]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'remote-image', $this->flags_for( $result, $remote ) );
		$this->assertNotContains( 'remote-image', $this->flags_for( $result, $local ) );
	}

	public function test_shortcode_flag_lists_known_names_only(): void {
		$flagged   = self::factory()->post->create( [ 'post_content' => '<p>See [ref] and [cc_by].</p>' ] );
		$unflagged = self::factory()->post->create( [ 'post_content' => '<p>The [architect] spoke.</p>' ] );

		$result = ( new AuditCommand() )->run( [], [] );

		$row = null;
		foreach ( $result['rows'] as $candidate ) {
			if ( $candidate['id'] === $flagged ) {
				$row = $candidate;
			}
		}

		$this->assertNotNull( $row );
		$this->assertContains( 'shortcode', $row['flags'] );
		$this->assertContains( 'ref', $row['detail']['shortcodes'] );
		$this->assertContains( 'cc_by', $row['detail']['shortcodes'] );
		$this->assertNotContains( 'shortcode', $this->flags_for( $result, $unflagged ) );
	}

	/**
	 * SI-17: a bare `<code lang="x">` HTML tag (never converted to a `core/code` block) whose
	 * content spans multiple lines flags `codecolorer`, distinct from the `[shortcode]` bracket
	 * forms `KNOWN_SHORTCODES` matches.
	 */
	public function test_shortcode_flag_names_codecolorer_for_bare_multiline_code_lang(): void {
		$flagged = self::factory()->post->create(
			[ 'post_content' => "<p>Before.</p>\n<code lang=\"php\">\$a = 1;\n\$b = 2;</code>\n<p>After.</p>" ]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'shortcode', $this->flags_for( $result, $flagged ) );
		$this->assertContains( 'codecolorer', $this->detail_for( $result, $flagged )['shortcodes'] );
	}

	/**
	 * SI-17: once converted to the `<pre class="wp-block-code"><code lang="x">` block form the
	 * audit no longer flags it -- the whole point of the pre-pass is to make this go away.
	 */
	public function test_shortcode_flag_ignores_converted_code_lang(): void {
		$converted = self::factory()->post->create(
			[ 'post_content' => "<pre class=\"wp-block-code\"><code lang=\"php\">\$a = 1;\n\$b = 2;</code></pre>" ]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertNotContains( 'shortcode', $this->flags_for( $result, $converted ) );
	}

	/**
	 * SI-17: a single-line inline `<code lang>` (left inline on purpose by the pre-pass) never
	 * flags either.
	 */
	public function test_shortcode_flag_ignores_inline_code_lang(): void {
		$inline = self::factory()->post->create(
			[ 'post_content' => '<p>Set the <code lang="php">$post</code> variable.</p>' ]
		);

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertNotContains( 'shortcode', $this->flags_for( $result, $inline ) );
	}

	public function test_post_format_aside_flag(): void {
		$aside = self::factory()->post->create();
		set_post_format( $aside, 'aside' );
		$regular = self::factory()->post->create();

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'post-format-aside', $this->flags_for( $result, $aside ) );
		$this->assertNotContains( 'post-format-aside', $this->flags_for( $result, $regular ) );
	}

	public function test_no_tags_flag(): void {
		$untagged = self::factory()->post->create();
		$tagged   = self::factory()->post->create( [ 'tags_input' => [ 'some-tag' ] ] );

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'no-tags', $this->flags_for( $result, $untagged ) );
		$this->assertNotContains( 'no-tags', $this->flags_for( $result, $tagged ) );
	}

	public function test_writing_no_form_flag(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$other   = $this->category_id( 'technology', 'Technology' );

		// Meta\Form::on_save() writes ttm_form on every editorial save, so "no ttm_form" (SPEC
		// §6.7) only describes legacy/imported content that predates it -- simulated here by
		// deleting the row the save hook just wrote.
		$no_form = self::factory()->post->create( [ 'post_category' => [ $writing ] ] );
		update_post_meta( $no_form, 'ttm_primary_category', $writing );
		delete_post_meta( $no_form, 'ttm_form' );

		$has_form = self::factory()->post->create( [ 'post_category' => [ $writing ] ] );
		update_post_meta( $has_form, 'ttm_primary_category', $writing );
		update_post_meta( $has_form, 'ttm_form', 'chapter' );

		$elsewhere = self::factory()->post->create( [ 'post_category' => [ $other ] ] );
		update_post_meta( $elsewhere, 'ttm_primary_category', $other );

		$result = ( new AuditCommand() )->run( [], [] );

		$this->assertContains( 'writing-no-form', $this->flags_for( $result, $no_form ) );
		$this->assertNotContains( 'writing-no-form', $this->flags_for( $result, $has_form ) );
		$this->assertNotContains( 'writing-no-form', $this->flags_for( $result, $elsewhere ) );
	}

	public function test_summary_counts_flags_and_inert_rows(): void {
		self::factory()->post->create( [ 'post_excerpt' => '' ] );
		self::factory()->post->create( [ 'post_excerpt' => '' ] );

		wp_insert_post(
			[
				'post_type'   => 'custom_css',
				'post_status' => 'publish',
				'post_title'  => 'ttm-theme',
				'post_name'   => 'ttm-theme',
			]
		);

		$result = ( new AuditCommand() )->run( [], [ 'summary' => true ] );

		$this->assertTrue( $result['ok'] );

		$by_flag = [];
		foreach ( $result['rows'] as $row ) {
			$this->assertArrayHasKey( 'flag', $row );
			$this->assertArrayHasKey( 'count', $row );
			$by_flag[ $row['flag'] ] = $row['count'];
		}

		$this->assertArrayHasKey( 'no-excerpt', $by_flag );
		$this->assertGreaterThanOrEqual( 2, $by_flag['no-excerpt'] );
		$this->assertArrayHasKey( 'inert-rows', $by_flag );
		$this->assertGreaterThanOrEqual( 1, $by_flag['inert-rows'] );
	}

	public function test_only_filter_restricts_checks(): void {
		$post = self::factory()->post->create(
			[
				'post_content' => '<p>Classic content.</p>',
				'post_excerpt' => '',
			]
		);

		$result = ( new AuditCommand() )->run( [], [ 'only' => 'classic' ] );

		$flags = $this->flags_for( $result, $post );
		$this->assertContains( 'classic', $flags );
		$this->assertNotContains( 'no-excerpt', $flags );
	}
}
