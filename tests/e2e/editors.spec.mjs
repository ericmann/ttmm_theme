/**
 * Editor-registration fidelity (SPEC §3.2 rule 38, §6.2, §6.7): every `ttm/*` block must be
 * registered — no "doesn't include support for" invalid-block notice and `wp.blocks
 * .getBlockType()` returns something — in both the Site Editor (front-page template) and the
 * Customizer. `test.fixme` in Phase 0; a later task un-fixmes both rows.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { test, expect } from '@playwright/test';
import { ADMIN } from './lib/urls.mjs';

// Resolved from the process cwd (the repo root) rather than `import.meta.url` -- see
// tests/e2e/lib/presets.mjs for why.
const blocksDir = join( 'plugins', 'ttm-core', 'blocks' );

/**
 * Every `ttm/<name>` registered under `plugins/ttm-core/blocks/*\/block.json`.
 *
 * @return {string[]} Block names.
 */
function ttmBlockNames() {
	return readdirSync( blocksDir, { withFileTypes: true } )
		.filter( ( entry ) => entry.isDirectory() )
		.map( ( entry ) => {
			const json = JSON.parse(
				readFileSync(
					join( blocksDir, entry.name, 'block.json' ),
					'utf8'
				)
			);
			return json.name;
		} );
}

/**
 * Log into `/wp-login.php` as the seeded admin.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<void>}
 */
async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( ADMIN.user );
	await page.locator( '#user_pass' ).fill( ADMIN.pass );
	await page.locator( '#wp-submit' ).click();
}

/**
 * Assert every `ttm/*` block is registered and no invalid-block notice is shown.
 *
 * @param {import('@playwright/test').Page} page Page already on the editor screen.
 * @return {Promise<void>}
 */
async function assertBlocksRegistered( page ) {
	// `networkidle` never resolves on the Site Editor -- it keeps a persistent heartbeat/autosave
	// connection open -- so wait for the block registry itself to settle instead: every `ttm/*`
	// block name to either be registered or have given up waiting.
	const names = ttmBlockNames();
	await page
		.waitForFunction(
			( blockNames ) =>
				window.wp?.blocks &&
				blockNames.every( ( n ) => window.wp.blocks.getBlockType( n ) ),
			names,
			{ timeout: 20000 }
		)
		.catch( () => {} );

	expect(
		await page.getByText( "doesn't include support for" ).count()
	).toBe( 0 );

	const missing = await page.evaluate(
		( blockNames ) =>
			blockNames.filter(
				( n ) => ! window.wp?.blocks?.getBlockType( n )
			),
		names
	);
	expect( missing ).toEqual( [] );
}

test.describe( 'editor registration', () => {
	test( 'editor-sed: Site Editor, front-page template @1280', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await login( page );
		await page.goto(
			'/wp-admin/site-editor.php?postType=wp_template&postId=ttm-theme%2F%2Ffront-page&canvas=edit'
		);
		await assertBlocksRegistered( page );
	} );

	test( 'editor-customizer: /wp-admin/customize.php @1280', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await login( page );
		await page.goto( '/wp-admin/customize.php' );
		await assertBlocksRegistered( page );
	} );
} );
