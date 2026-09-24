<?php
/**
 * Integration tests for the P8-04 CLI command core (migrate:politics/redirects/close-comments).
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\MigrateCommand;

class MigrateCommandTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function politics_id(): int {
		$term = term_exists( 'politics', 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( 'Politics', 'category', [ 'slug' => 'politics' ] );

		return (int) $created['term_id'];
	}

	public function test_politics_child_mode_creates_opinion_and_reparents(): void {
		$politics = $this->politics_id();

		$result = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );

		$politics_term = get_term( $politics, 'category' );
		$opinion       = get_term_by( 'slug', 'opinion', 'category' );

		$this->assertNotFalse( $opinion );
		$this->assertSame( (int) $opinion->term_id, (int) $politics_term->parent );

		$redirects = get_option( 'ttm_redirects' );
		$this->assertIsArray( $redirects );
		$froms = array_column( $redirects, 'from' );
		$this->assertContains( '/category/politics/', $froms );
	}

	public function test_politics_child_mode_adds_opinion_to_politics_posts_and_sets_primary(): void {
		$politics = $this->politics_id();
		$post     = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );
		update_post_meta( $post, 'ttm_primary_category', $politics );

		$result = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );

		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'politics', $categories );
		$this->assertContains( 'opinion', $categories );

		$opinion = get_term_by( 'slug', 'opinion', 'category' );
		$this->assertNotFalse( $opinion );
		$this->assertSame( (int) $opinion->term_id, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertSame( 'opinion', \TTM\Core\Meta\PrimaryCategory::slug( $post ) );
	}

	public function test_politics_child_mode_dry_run_reports_post_count_and_writes_nothing(): void {
		$politics = $this->politics_id();
		$post     = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );
		update_post_meta( $post, 'ttm_primary_category', $politics );

		$result = ( new MigrateCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( $post, $result['rows'][0]['post_id'] );

		$this->assertFalse( get_term_by( 'slug', 'opinion', 'category' ) );
		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertNotContains( 'opinion', $categories );
		$this->assertSame( $politics, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
	}

	/**
	 * R2-02: env:live's starter content already creates Politics as a child of Opinion
	 * (`Seeder::seed_categories()`), so `politics_child()`'s "already a child; nothing to do"
	 * check must not skip posts that were assigned to Politics afterward (e.g. by
	 * `convert:import`) and never got Opinion added or set as their primary.
	 */
	public function test_politics_already_child_still_updates_posts(): void {
		$ids      = ( new \TTM\Core\Cli\Seeder() )->seed_categories();
		$politics = $ids['politics'];
		$opinion  = $ids['opinion'];

		$post = self::factory()->post->create();
		wp_set_post_terms( $post, [ $politics ], 'category' );

		$result = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'Updated 1 Politics post(s).', implode( "\n", $result['messages'] ) );

		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'politics', $categories );
		$this->assertContains( 'opinion', $categories );
		$this->assertSame( $opinion, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertSame( 'opinion', \TTM\Core\Meta\PrimaryCategory::slug( $post ) );

		$second = ( new MigrateCommand() )->run( [], [] );
		$this->assertTrue( $second['ok'] );
		$this->assertStringContainsString( 'Updated 0 Politics post(s).', implode( "\n", $second['messages'] ) );
		$this->assertCount( 0, $second['rows'] );
	}

	public function test_politics_already_child_dry_run_writes_nothing(): void {
		$ids      = ( new \TTM\Core\Cli\Seeder() )->seed_categories();
		$politics = $ids['politics'];

		$post = self::factory()->post->create();
		wp_set_post_terms( $post, [ $politics ], 'category' );

		// PrimaryCategory::on_save() (save_post_post) already stamped a primary at insert time
		// (before wp_set_post_terms() assigned Politics), same as any freshly-saved post; the
		// point of a dry run is that it's still exactly this value afterward, not that it's 0.
		$primary_before = (int) get_post_meta( $post, 'ttm_primary_category', true );

		$result = ( new MigrateCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['rows'] );
		$this->assertSame( $post, $result['rows'][0]['post_id'] );

		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertNotContains( 'opinion', $categories );
		$this->assertSame( $primary_before, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
	}

	public function test_politics_dry_run_changes_nothing(): void {
		$politics = $this->politics_id();

		$result = ( new MigrateCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( get_term_by( 'slug', 'opinion', 'category' ) );

		$politics_term = get_term( $politics, 'category' );
		$this->assertSame( 0, (int) $politics_term->parent );
	}

	public function test_politics_tag_mode_tags_posts_and_moves_them_to_opinion(): void {
		$politics = $this->politics_id();
		$post     = self::factory()->post->create( [ 'post_category' => [ $politics ] ] );

		$result = ( new MigrateCommand() )->run( [], [ 'to' => 'tag' ] );

		$this->assertTrue( $result['ok'] );

		$categories = wp_get_post_categories( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'opinion', $categories );
		$this->assertNotContains( 'politics', $categories );

		$tags = wp_get_post_tags( $post, [ 'fields' => 'slugs' ] );
		$this->assertContains( 'politics', $tags );

		$this->assertNull( term_exists( 'politics', 'category' ) );
	}

	public function test_redirects_nginx_format(): void {
		$this->politics_id();
		( new MigrateCommand() )->run( [], [] );

		$result = ( new MigrateCommand() )->redirects( [], [ 'format' => 'nginx' ] );

		$this->assertTrue( $result['ok'] );
		$joined = implode( "\n", $result['messages'] );
		$this->assertStringContainsString( 'rewrite ^/category/politics/(.*)$ /category/opinion/politics/$1 permanent;', $joined );
	}

	public function test_redirects_json_format(): void {
		$this->politics_id();
		( new MigrateCommand() )->run( [], [] );

		$result = ( new MigrateCommand() )->redirects( [], [ 'format' => 'json' ] );

		$this->assertTrue( $result['ok'] );
		$decoded = json_decode( $result['messages'][0], true );
		$this->assertIsArray( $decoded );
		$froms = array_column( $decoded, 'from' );
		$this->assertContains( '/category/politics/', $froms );
	}

	public function test_close_comments_closes_all_and_default(): void {
		$post = self::factory()->post->create( [ 'comment_status' => 'open' ] );
		$page = self::factory()->post->create(
			[
				'post_type'      => 'page',
				'comment_status' => 'open',
			]
		);

		$result = ( new MigrateCommand() )->close_comments( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'closed', get_post( $post )->comment_status );
		$this->assertSame( 'closed', get_post( $post )->ping_status );
		$this->assertSame( 'closed', get_post( $page )->comment_status );
		$this->assertSame( 'closed', get_option( 'default_comment_status' ) );
		$this->assertSame( 'closed', get_option( 'default_ping_status' ) );
	}

	public function test_close_comments_fires_purge_once(): void {
		$post_ids = self::factory()->post->create_many( 5, [ 'comment_status' => 'open' ] );

		$fired = 0;
		add_action(
			'ttm_purge_urls',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$result = ( new MigrateCommand() )->close_comments( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 1, $fired );

		foreach ( $post_ids as $post_id ) {
			$this->assertSame( 'closed', get_post( $post_id )->comment_status );
			$this->assertSame( 'closed', get_post( $post_id )->ping_status );
		}
	}

	public function test_close_comments_dry_run_fires_no_purge(): void {
		self::factory()->post->create_many( 3, [ 'comment_status' => 'open' ] );

		$fired = 0;
		add_action(
			'ttm_purge_urls',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		( new MigrateCommand() )->close_comments( [], [ 'dry-run' => true ] );

		$this->assertSame( 0, $fired );
	}

	public function test_politics_is_idempotent(): void {
		$this->politics_id();

		$first  = ( new MigrateCommand() )->run( [], [] );
		$second = ( new MigrateCommand() )->run( [], [] );

		$this->assertTrue( $first['ok'] );
		$this->assertTrue( $second['ok'] );

		$redirects = get_option( 'ttm_redirects' );
		$this->assertCount( 2, $redirects );
	}

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * P2-03, SPEC §6.7: an empty excerpt on a non-Journal post gets the Yoast meta description;
	 * a Journal-primary post is excluded even with an empty excerpt and a Yoast description.
	 */
	public function test_excerpts_from_yoast_fills_only_empty_non_journal_excerpts(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A short meta description.' );

		$journal = $this->category_id( 'journal', 'Journal' );
		$jpost   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $jpost, 'ttm_primary_category', $journal );
		update_post_meta( $jpost, '_yoast_wpseo_metadesc', 'A journal meta description.' );

		$result = ( new MigrateCommand() )->excerpts( [], [ 'from' => 'yoast' ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'A short meta description.', get_post( $post )->post_excerpt );
		$this->assertSame( '', get_post( $jpost )->post_excerpt );

		$ids = array_column( $result['rows'], 'post_id' );
		$this->assertContains( $post, $ids );
		$this->assertNotContains( $jpost, $ids );
	}

	public function test_excerpts_never_overwrite(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => 'A hand-written excerpt.',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A short meta description.' );

		( new MigrateCommand() )->excerpts( [], [ 'from' => 'yoast' ] );

		$this->assertSame( 'A hand-written excerpt.', get_post( $post )->post_excerpt );
	}

	public function test_excerpts_dry_run_writes_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A short meta description.' );

		$result = ( new MigrateCommand() )->excerpts(
			[],
			[
				'from'    => 'yoast',
				'dry-run' => true,
			]
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( '', get_post( $post )->post_excerpt );
		$this->assertStringContainsString( 'Would fill 1 excerpt(s) from Yoast.', $result['messages'][0] );
	}

	public function test_excerpts_are_truncated_to_excerpt_length(): void {
		$tech  = $this->category_id( 'technology', 'Technology' );
		$words = [];
		for ( $i = 1; $i <= 80; $i++ ) {
			$words[] = "word{$i}";
		}
		$long_desc = implode( ' ', $words ); // no terminal punctuation at all -> hard cut.

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', $long_desc );

		( new MigrateCommand() )->excerpts( [], [ 'from' => 'yoast' ] );

		$excerpt = get_post( $post )->post_excerpt;
		$this->assertStringEndsWith( '…', $excerpt );
		$this->assertLessThanOrEqual(
			(int) \TTM\Core\Config::get( 'excerpt_length', 55 ) + 1, // +1: the trailing "…" isn't a counted word but splits oddly on whitespace.
			count( preg_split( '/\s+/', trim( $excerpt ) ) )
		);
	}

	/**
	 * P2-08: `--words=` overrides `excerpt_length` for one measurement run, without touching
	 * the `Config` default other callers use.
	 */
	public function test_excerpts_words_override_ignores_excerpt_length_config(): void {
		$tech  = $this->category_id( 'technology', 'Technology' );
		$words = [];
		for ( $i = 1; $i <= 20; $i++ ) {
			$words[] = "word{$i}";
		}
		$desc = implode( ' ', $words ); // no terminal punctuation -> hard cut at the word limit.

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, '_yoast_wpseo_metadesc', $desc );

		( new MigrateCommand() )->excerpts(
			[],
			[
				'from'  => 'yoast',
				'words' => 5,
			] 
		);

		$excerpt = get_post( $post )->post_excerpt;
		$this->assertSame( 'word1 word2 word3 word4 word5…', $excerpt );
	}

	/**
	 * P2-04, SPEC §6.7: a Photon URL for `migration.photon_origin` rewrites to the origin with
	 * no network fetch at all.
	 */
	public function test_images_rewrites_photon_urls_to_origin_without_fetching(): void {
		$original = '<p><img src="https://i0.wp.com/eric.mann.blog/wp-content/uploads/2020/photo.jpg" alt=""></p>';
		$post_id  = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);

		$fetched = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$fetched ) {
				++$fetched;
				return new WP_Error( 'unexpected_fetch', 'Should not fetch a Photon origin rewrite.' );
			}
		);

		$result = ( new MigrateCommand() )->images( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 0, $fetched );
		$this->assertStringContainsString(
			home_url( '/wp-content/uploads/2020/photo.jpg' ),
			get_post( $post_id )->post_content
		);
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ttm_images_rewritten', true ) );
		$this->assertSame( $original, get_post_meta( $post_id, 'ttm_classic_backup', true ) );
	}

	public function test_images_sideloads_listed_host_via_pre_http_request_png(): void {
		$original = '<p><img src="https://cdn.example.com/photo.png" alt=""></p>';
		$post_id  = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);

		// A minimal valid 1x1 PNG. `download_url()` streams the response straight to
		// `$args['filename']` inside the real HTTP transport, which `pre_http_request`
		// entirely bypasses -- so the mock must write the body to that file itself for
		// `wp_check_filetype_and_ext()` to see real PNG bytes.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args ) use ( $png ) {
				if ( ! empty( $args['filename'] ) ) {
					file_put_contents( $args['filename'], $png ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- test fixture, mocking the HTTP transport's own stream-to-file.
				}

				return [
					'headers'  => [ 'content-type' => 'image/png' ],
					'body'     => $png,
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => $args['filename'] ?? null,
				];
			},
			10,
			3
		);

		$result = ( new MigrateCommand() )->images( [], [ 'hosts' => 'cdn.example.com' ] );

		$this->assertTrue( $result['ok'] );

		$attachments = get_posts(
			[
				'post_type'      => 'attachment',
				'post_parent'    => $post_id,
				'posts_per_page' => 5,
			]
		);
		$this->assertCount( 1, $attachments );

		$new_content = get_post( $post_id )->post_content;
		$this->assertStringNotContainsString( 'cdn.example.com/photo.png', $new_content );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ttm_images_rewritten', true ) );
		$this->assertSame( $original, get_post_meta( $post_id, 'ttm_classic_backup', true ) );
	}

	/**
	 * R1-04, SPEC §5: with no `--hosts`, `migrate:images` must use the real
	 * `migration.image_hosts` default (SPEC §5's list) -- not an empty list that silently
	 * sideloads nothing.
	 */
	public function test_images_without_hosts_uses_configured_default_hosts(): void {
		$original = '<p><img src="https://eamann.com/photo.png" alt=""></p>';
		$post_id  = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);

		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args ) use ( $png ) {
				if ( ! empty( $args['filename'] ) ) {
					file_put_contents( $args['filename'], $png ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- test fixture, mocking the HTTP transport's own stream-to-file.
				}

				return [
					'headers'  => [ 'content-type' => 'image/png' ],
					'body'     => $png,
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => $args['filename'] ?? null,
				];
			},
			10,
			3
		);

		$result = ( new MigrateCommand() )->images( [], [] );

		$this->assertTrue( $result['ok'] );

		$attachments = get_posts(
			[
				'post_type'      => 'attachment',
				'post_parent'    => $post_id,
				'posts_per_page' => 5,
			]
		);
		$this->assertCount( 1, $attachments );

		$new_content = get_post( $post_id )->post_content;
		$this->assertStringNotContainsString( 'eamann.com/photo.png', $new_content );
	}

	public function test_images_leaves_src_on_fetch_failure(): void {
		$original = '<p><img src="https://cdn.example.com/broken.jpg" alt=""></p>';
		$post_id  = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);

		add_filter(
			'pre_http_request',
			static fn () => new WP_Error( 'http_request_failed', 'Connection failed.' )
		);

		$result = ( new MigrateCommand() )->images( [], [ 'hosts' => 'cdn.example.com' ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $original, get_post( $post_id )->post_content );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ttm_images_rewritten', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'ttm_classic_backup', true ) );
		$this->assertSame( [], $result['rows'] );
	}

	public function test_images_dry_run_writes_nothing(): void {
		$original = '<p><img src="https://i0.wp.com/eric.mann.blog/wp-content/uploads/2020/photo.jpg" alt=""></p>';
		$post_id  = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);

		$result = ( new MigrateCommand() )->images( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $original, get_post( $post_id )->post_content );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ttm_images_rewritten', true ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'ttm_classic_backup', true ) );
		$this->assertStringContainsString( 'Would rewrite images on 1 post(s).', $result['messages'][0] );
	}

	/**
	 * P2-08: `migrate:images` reports sideload attempts/successes/timeouts/other-failures, for
	 * the `migration.image_timeout` tuning measurement; `--timeout=` overrides `Config` for one
	 * run without touching its default.
	 */
	public function test_images_reports_sideload_attempt_counts_and_honours_timeout_override(): void {
		$original = '<p><img src="https://cdn.example.com/broken.jpg" alt=""></p>';
		self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);

		$seen_timeout = null;
		add_filter(
			'http_request_timeout',
			static function ( $timeout ) use ( &$seen_timeout ) {
				// Priority 20: runs after MigrateCommand's own (default-priority 10) filter, so
				// this sees the value it actually set, not the pre-filter default.
				$seen_timeout = $timeout;
				return $timeout;
			},
			20
		);
		add_filter(
			'pre_http_request',
			static fn () => new WP_Error( 'http_request_failed', 'Operation timed out after 40001 milliseconds.' )
		);

		$result = ( new MigrateCommand() )->images(
			[],
			[
				'hosts'   => 'cdn.example.com',
				'timeout' => 40, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- a --timeout=<n> CLI arg value, not an actual wp_remote_* call.
			]
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 40, $seen_timeout );
		$this->assertSame(
			'Sideload attempts: 1, successes: 0, timeouts: 1, other failures: 0.',
			$result['messages'][1]
		);
	}

	public function test_images_backup_is_written_once(): void {
		$original = '<p><img src="https://i0.wp.com/eric.mann.blog/wp-content/uploads/2020/photo.jpg" alt=""></p>';
		$post_id  = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $original,
			]
		);
		update_post_meta( $post_id, 'ttm_classic_backup', 'pre-existing backup' );

		( new MigrateCommand() )->images( [], [] );

		$this->assertSame( 'pre-existing backup', get_post_meta( $post_id, 'ttm_classic_backup', true ) );
	}
}
