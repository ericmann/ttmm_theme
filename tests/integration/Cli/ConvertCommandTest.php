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

	/**
	 * P3-03: WP core's own `sanitize_post_meta_footnotes` filter (`_wp_filter_post_meta_footnotes()`,
	 * wp-includes/blocks.php) `json_decode()`s the incoming meta value and returns `''` outright
	 * on failure; `update_metadata()` unslashes the value before that filter runs, so an
	 * unslashed `wp_json_encode()` result -- whose own `\"` escaping around any quoted HTML
	 * attribute reads as WP "magic quotes" slashing and gets stripped -- becomes invalid JSON
	 * and silently discards every footnote. A footnote whose content has an `<a href="…">` (a
	 * quoted attribute) reproduced this on nearly every post touched during a real import; see
	 * docs/feedback/phase-4/LIVE-TRIAGE.md.
	 */
	public function test_import_stores_footnotes_meta_containing_a_quoted_attribute(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [
						[
							'id'      => 'ref-1-1',
							'content' => 'See <a href="https://example.com/">this link</a> for details.',
						],
					],
				],
			]
		);

		( new ConvertCommand() )->import( [ $file ], [] );

		$stored = json_decode( (string) get_post_meta( $post_id, 'footnotes', true ), true );
		$this->assertIsArray( $stored );
		$this->assertSame(
			'See <a href="https://example.com/">this link</a> for details.',
			$stored[0]['content']
		);
	}

	/**
	 * P3-02, SPEC §6.8: `--dry-run` lists a post's `report.shortcodes` (unconverted shortcodes
	 * the pre-pass left in place) alongside its block counts.
	 */
	public function test_dry_run_lists_remaining_shortcodes_and_freeform(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
					'report'    => [
						'freeform'   => 0,
						'shortcodes' => [
							[
								'name'  => 'seoslides',
								'count' => 2,
							],
						],
					],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'remaining shortcodes: seoslides(2)', $result['messages'][0] );
	}

	/**
	 * P3-02, SPEC §6.8: after conversion, each footnote's text must appear exactly once in the
	 * rendered post (`the_content`) -- `import_one()` appends the `core/footnotes` block the
	 * `<sup data-fn>` markers need to resolve against, since `rawHandler` never adds one itself.
	 */
	public function test_import_verifies_each_footnote_appears_once(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => '<!-- wp:paragraph --><p>Signed off<sup data-fn="ref-1-1" class="fn"><a href="#ref-1-1" id="ref-1-1-link">1</a></sup> on the draft.</p><!-- /wp:paragraph -->',
					'footnotes' => [
						[
							'id'      => 'ref-1-1',
							'content' => 'A clarifying note.',
						],
					],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringNotContainsString( 'footnotes-mismatch', $result['messages'][0] );

		$post = get_post( $post_id );
		$this->assertStringContainsString( 'wp:footnotes', $post->post_content );

		global $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- setup_postdata() needs the global itself set, not just passed as an argument.
		$post = get_post( $post_id );
		setup_postdata( $post );
		$rendered = apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();

		$this->assertSame( 1, substr_count( wp_strip_all_tags( $rendered ), 'A clarifying note.' ) );
	}

	/**
	 * P3-02: a footnote that never actually resolves in the rendered post is reported, but the
	 * import still proceeds and the classic backup is still written exactly once. Simulated by
	 * having `blocks` already contain the literal substring "wp:footnotes" as inert prose
	 * (not a real block comment) -- `import_one()`'s "already has one" guard sees it and skips
	 * appending the real `core/footnotes` block, so the note text never actually renders.
	 */
	public function test_import_reports_footnote_mismatch_without_writing_backup_twice(): void {
		$original = '<p>Some classic content.</p>';
		$post_id  = $this->classic_post( $original );
		$file     = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted, mentions wp:footnotes in passing.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [
						[
							'id'      => 'ref-1-1',
							'content' => 'This note never got referenced.',
						],
					],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'footnotes-mismatch', $result['messages'][0] );
		$this->assertSame( $original, get_post_meta( $post_id, 'ttm_classic_backup', true ) );

		// A second import (e.g. a retry) must still not touch the backup.
		( new ConvertCommand() )->import( [ $file ], [] );
		$this->assertSame( $original, get_post_meta( $post_id, 'ttm_classic_backup', true ) );
	}

	/**
	 * P3-02/P3-03, SPEC §6.8: `[text-mismatch]` surfaces `scripts/convert-classic.mjs`'s own
	 * `report.textEqual` (its before/after comparison already accounts for every substitution
	 * the pre-pass makes -- a [ref] note's text moving into the footnotes list, [caption]/
	 * [gallery]/[audio]'s own bracket syntax being dropped, etc. -- so the PHP side surfaces
	 * that value rather than re-deriving its own, cruder comparison against the raw classic
	 * content, which produced false mismatches on nearly every real post; see
	 * docs/feedback/phase-4/LIVE-TRIAGE.md).
	 */
	public function test_text_equality_ignores_stripped_shortcodes(): void {
		$post_id = $this->classic_post( '<p>Listen to the recording.</p>[audio src="https://example.com/a.mp3"]' );
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Listen to the recording.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:audio -->\n<figure class=\"wp-block-audio\"><audio controls src=\"https://example.com/a.mp3\"></audio></figure>\n<!-- /wp:audio -->",
					'footnotes' => [],
					'report'    => [ 'textEqual' => true ],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringNotContainsString( 'text-mismatch', $result['messages'][0] );
	}

	/**
	 * R1-03, SPEC §1.3 "Done"/§6.6: a record whose `report.textEqual` is false (`scripts/
	 * convert-classic.mjs --allow-text-mismatch` still emits it, rather than aborting the whole
	 * plan) is skipped entirely -- never converted, never given a `ttm_classic_backup` -- so the
	 * post stays classic and shows up in the owner's cleanup worklist.
	 */
	public function test_import_skips_text_mismatch_records_and_keeps_them_classic(): void {
		$original = '<p>Some classic content.</p>';
		$post_id  = $this->classic_post( $original );
		$file     = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					'blocks'    => "<!-- wp:paragraph -->\n<p>Converted.</p>\n<!-- /wp:paragraph -->",
					'footnotes' => [],
					'report'    => [ 'textEqual' => false ],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'text-mismatch, skipped', $result['messages'][0] );
		$this->assertStringContainsString( 'Skipped 1 post(s) with a text mismatch', end( $result['messages'] ) );

		$post = get_post( $post_id );
		$this->assertSame( $original, $post->post_content );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'ttm_classic_backup', true ) );
	}

	/**
	 * R1-03, SPEC §6.8: `footnotes_verified()` counts each note's text inside the rendered
	 * `core/footnotes` list only (not the whole body), and requires exactly one occurrence --
	 * two identical `core/footnotes` blocks (a duplicated list) makes every note's text appear
	 * twice there, which must fail verification even though the note also "appears" (twice) in
	 * the rendered page.
	 */
	public function test_footnote_list_duplicated_fails_verification(): void {
		$post_id = $this->classic_post();
		$file    = $this->ndjson_file(
			[
				[
					'id'        => $post_id,
					'slug'      => 'x',
					// Two wp:footnotes blocks already present -- import_one()'s "already has
					// one" guard (`strpos($blocks, 'wp:footnotes')`) sees the first and doesn't
					// append a third, so the post renders exactly two footnote lists.
					'blocks'    => '<!-- wp:paragraph --><p>Signed off<sup data-fn="ref-1-1" class="fn"><a href="#ref-1-1" id="ref-1-1-link">1</a></sup> on the draft.</p><!-- /wp:paragraph -->' . "\n\n<!-- wp:footnotes /-->\n\n<!-- wp:footnotes /-->\n",
					'footnotes' => [
						[
							'id'      => 'ref-1-1',
							'content' => 'A clarifying note.',
						],
					],
				],
			]
		);

		$result = ( new ConvertCommand() )->import( [ $file ], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'footnotes-mismatch', $result['messages'][0] );
	}
}
