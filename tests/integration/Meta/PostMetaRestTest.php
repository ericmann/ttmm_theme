<?php
/**
 * Integration tests for the post-meta REST contract.
 *
 * @package TTM\Tests\Integration\Meta
 */

declare( strict_types=1 );

class PostMetaRestTest extends TTM_IntegrationTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function test_every_post_meta_key_is_registered_for_post(): void {
		$keys = [
			'ttm_series_part',
			'ttm_part_title',
			'ttm_primary_category',
			'ttm_form',
			'ttm_form_locked',
			'ttm_word_count',
			'ttm_syndication',
			'ttm_location',
			'ttm_featured_in_section',
		];

		$registered = get_registered_meta_keys( 'post', 'post' );

		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $registered, "Missing post meta {$key}" );
		}
	}

	public function test_rest_rejects_javascript_syndication_url(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id = self::factory()->post->create( [ 'post_author' => $admin ] );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$post_id}" );
		$request->set_body_params(
			[
				'meta' => [
					'ttm_syndication' => [
						'x'        => 'javascript:alert(1)',
						'mastodon' => 'https://mastodon.social/@eric',
					],
				],
			]
		);
		$this->server->dispatch( $request );

		$stored = get_post_meta( $post_id, 'ttm_syndication', true );

		$this->assertArrayNotHasKey( 'x', $stored );
		$this->assertSame( 'https://mastodon.social/@eric', $stored['mastodon'] );
	}

	public function test_rest_bad_form_enum_stores_article(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id = self::factory()->post->create( [ 'post_author' => $admin ] );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$post_id}" );
		$request->set_body_params( [ 'meta' => [ 'ttm_form' => 'bogus' ] ] );
		$this->server->dispatch( $request );

		$this->assertSame( 'article', get_post_meta( $post_id, 'ttm_form', true ) );
	}

	public function test_editor_cannot_write_meta_on_others_post_when_lacking_edit_post(): void {
		$author      = self::factory()->user->create( [ 'role' => 'author' ] );
		$contributor = self::factory()->user->create( [ 'role' => 'contributor' ] );
		$post_id     = self::factory()->post->create(
			[
				'post_author' => $author,
				'post_status' => 'publish',
			]
		);
		wp_set_current_user( $contributor );

		$request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$post_id}" );
		$request->set_body_params( [ 'meta' => [ 'ttm_location' => 'Somewhere' ] ] );
		$response = $this->server->dispatch( $request );

		$this->assertTrue( $response->is_error() || '' === get_post_meta( $post_id, 'ttm_location', true ) );
	}
}
