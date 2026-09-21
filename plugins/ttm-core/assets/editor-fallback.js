/**
 * Committed, unbuilt fallback editor script (SPEC §6.7): when a `ttm/*` block's own built
 * `index.js` is missing (`Registrar::drop_missing_editor_script()`), this registers a minimal
 * client-side block definition -- just enough that the block editor recognizes the block
 * (WordPress already bootstrapped the rest of its metadata server-side from block.json) and
 * renders it via `ServerSideRender` instead of showing "doesn't include support for the …
 * block". Never enqueued on the front end (rule 6): it only ever becomes an `editorScript`
 * dependency, which core only loads in editor contexts.
 *
 * Plain script, no build step, no dependencies beyond the ones it declares
 * (`wp-blocks`, `wp-element`, `wp-server-side-render`, `wp-i18n`).
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.blocks || ! wp.element || ! wp.serverSideRender ) {
		return;
	}

	var blockNames = window.ttmCoreBlocks || [];

	blockNames.forEach( function ( name ) {
		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}

		wp.blocks.registerBlockType( name, {
			edit: function () {
				return wp.element.createElement( wp.serverSideRender, {
					block: name,
				} );
			},
			save: function () {
				return null;
			},
		} );
	} );
} )();
