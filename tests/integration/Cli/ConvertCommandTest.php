<?php
/**
 * Integration tests for the P8-02 CLI command cores (convert:export/import/revert).
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\ConvertCommand;

class ConvertCommandTest extends TTM_IntegrationTestCase {

	private function classic_post( string $content = '<p>Some classic content.</p>' ): int {
		return self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => $content,
			]
		);
	}

	private function block_post(): int {
		return self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => "<!-- wp:paragraph -->\n<p>Already blocks.</p>\n<!-- /wp:paragraph -->",
			]
		);
	}

	private function ndjson_file( array $lines ): string {
		$path = tempnam( sys_get_temp_dir(), 'ttm-convert-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_tempnam -- test-only temp file, not a front-end/plugin runtime path.
		file_put_contents( $path, implode( "\n", array_map( 'wp_json_encode', $lines ) ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test-only temp file.

		return $path;
	}

	public function test_export_selects_only_classic_posts(): void {
		$classic = $this->classic_post();
		$blocked = $this->block_post();

		$out    = tempnam( sys_get_temp_dir(), 'ttm-export-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_tempnam -- test-only temp file.
		$result = ( new ConvertCommand() )->run(
			[],
			[
				'all-classic' => true,
				'out'         => $out,
			] 
		);

		$this->assertTrue( $result['ok'] );

		$lines = array_filter( explode( "\n", (string) file_get_contents( $out ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- test-only temp file.
		$ids   = array_map(
			static fn ( string $line ): int => json_decode( $line, true )['id'],
			$lines
		);

		$this->assertContains( $classic, $ids );
		$this->assertNotContains( $blocked, $ids );
	}

	public function test_import_dry_run_reports_and_writes_nothing(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
					'report'    => [ 'freeform' => 0 ],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$post = get_post( $post_id );
		$this->assertStringContainsString( 'classic content', $post->post_content );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'ttm_classic_backup', true ) );
	}

	public function test_import_refuses_freeform_without_flag(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => '<!-- wp:freeform --><div>raw html</div><!-- /wp:freeform -->',
					'footnotes' => [],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertFalse( $result['ok'] );
		$post = get_post( $post_id );
		$this->assertStringContainsString( 'classic content', $post->post_content );
	}

	public function test_import_writes_backup_once_revision_and_converted_at(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$original = '<p>Some classic content.</p>';
		$post_id  = $this->classic_post( $original );
		$file     = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertTrue( $result['ok'] );

		$post = get_post( $post_id );
		$this->assertStringContainsString( 'Converted.', $post->post_content );
		$this->assertSame( $original, get_post_meta( $post_id, 'ttm_classic_backup', true ) );
		$this->assertNotSame( '', (string) get_post_meta( $post_id, 'ttm_converted_at', true ) );

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertNotEmpty( $revisions );
	}

	public function test_import_second_run_does_not_overwrite_backup(): void {
		$original = '<p>Some classic content.</p>';
		$post_id  = $this->classic_post( $original );

		$file1 = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>First conversion.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
				],
			]
		);
		( new ConvertCommand() )->import( [ $file1 ], [] );

		$file2 = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Second conversion.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
				],
			]
		);
		( new ConvertCommand() )->import( [ $file2 ], [] );

		$this->assertSame( $original, get_post_meta( $post_id, 'ttm_classic_backup', true ) );

		$post = get_post( $post_id );
		$this->assertStringContainsString( 'Second conversion.', $post->post_content );
	}

	public function test_revert_restores_original_and_clears_meta(): void {
		$original = '<p>Some classic content.</p>';
		$post_id  = $this->classic_post( $original );
		$file     = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
				],
			]
		);
		( new ConvertCommand() )->import( [ $file ], [] );

		$result = ( new ConvertCommand() )->revert( [], [ 'post' => (string) $post_id ] );

		$this->assertTrue( $result['ok'] );
		$post = get_post( $post_id );
		$this->assertSame( $original, $post->post_content );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'ttm_converted_at', true ) );
	}

	public function test_import_stores_footnotes_meta(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [
						[
							'id'      => 'mfn-1',
							'content' => 'A footnote.',
						],
					],
				],
			]
		);

		( new ConvertCommand() )->import( [ $file ], [] );

		$stored = json_decode( (string) get_post_meta( $post_id, 'footnotes', true ), true );
		$this->assertSame(
			[
				[
					'id'      => 'mfn-1',
					'content' => 'A footnote.',
				],
			],
			$stored 
		);
	}
}
