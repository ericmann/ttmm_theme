<?php
/**
 * Integration tests for TTM\Core\Blocks\Registrar.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Registrar;

class RegistrarTest extends TTM_IntegrationTestCase {

	public function test_all_block_directories_are_registered(): void {
		$dirs = glob( TTM_CORE_DIR . 'blocks/*/block.json' );
		$this->assertNotEmpty( $dirs );

		foreach ( $dirs as $file ) {
			$metadata = json_decode( (string) file_get_contents( $file ), true );
			$this->assertTrue(
				\WP_Block_Type_Registry::get_instance()->is_registered( $metadata['name'] ),
				"{$metadata['name']} is not registered"
			);
		}
	}

	public function test_missing_build_uses_fallback_editor_script_instead_of_dropping_it(): void {
		// A ttm/* block name whose build output can never exist, so the "missing" branch is exercised
		// regardless of whether this checkout has actually been built.
		$metadata = [
			'name'         => 'ttm/does-not-exist-in-the-build',
			'editorScript' => 'file:./index.js',
		];

		$filtered = Registrar::drop_missing_editor_script( $metadata );

		$this->assertArrayHasKey( 'editorScript', $filtered );
		$this->assertSame( 'ttm-core-editor-fallback', $filtered['editorScript'] );
		$this->assertContains( 'ttm/does-not-exist-in-the-build', Registrar::fallback_blocks() );
	}
}
