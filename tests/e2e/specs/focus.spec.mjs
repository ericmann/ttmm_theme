/**
 * Keyboard focus visibility on the front page (SPEC §3.4 rule 33, Phase 8). Presses Tab up to
 * 40 times and asserts the skip link, the first nav link, the first `.ttm-item`, and the first
 * `.btn` each get a visible outline when focused - `ttm.css` never overrides `outline` (it relies
 * entirely on the browser default), so this is really a test that nothing accidentally suppresses
 * it later.
 */

import { test, expect } from '@playwright/test';

const MAX_TABS = 40;

const TARGETS = {
	'skip link': '.ttm-skip',
	'first nav link': '.wp-block-navigation-item__content',
	'first .ttm-item': '.ttm-item, .ttm-item a',
	'first .btn': '.btn',
};

test( 'every target has a visible focus outline when tabbed to', async ( {
	page,
} ) => {
	await page.goto( '/' );

	const found = {};

	for (
		let i = 0;
		i < MAX_TABS &&
		Object.keys( found ).length < Object.keys( TARGETS ).length;
		i++
	) {
		await page.keyboard.press( 'Tab' );

		// Runs inside the page, not this Node process - `document`/`getComputedStyle` are real
		// browser globals here, and `document.activeElement` is exactly what "what did Tab just
		// focus" means, so the editor-focused `@wordpress/no-global-active-element` rule doesn't
		// apply to this evaluate() callback.
		/* eslint-disable @wordpress/no-global-active-element, no-undef */
		const activeElementOutline = await page.evaluate( ( selectors ) => {
			const el = document.activeElement;
			if ( null === el ) {
				return null;
			}

			const matchedLabel = Object.entries( selectors ).find(
				( [ , selector ] ) =>
					el.matches( selector ) || null !== el.closest( selector )
			);
			if ( ! matchedLabel ) {
				return null;
			}

			const style = getComputedStyle( el );
			return {
				label: matchedLabel[ 0 ],
				outlineStyle: style.outlineStyle,
				outlineWidth: style.outlineWidth,
			};
		}, TARGETS );
		/* eslint-enable @wordpress/no-global-active-element, no-undef */

		if (
			null === activeElementOutline ||
			activeElementOutline.label in found
		) {
			continue;
		}

		found[ activeElementOutline.label ] = {
			outlineStyle: activeElementOutline.outlineStyle,
			outlineWidth: activeElementOutline.outlineWidth,
		};
	}

	for ( const label of Object.keys( TARGETS ) ) {
		// `toHaveProperty()` treats a dotted string as a nested-path lookup (so "first .ttm-item"
		// would look for found.first .ttm-item as nested keys) - wrapping it in an array makes it
		// a single literal key instead.
		expect(
			found,
			`never reached "${ label }" within ${ MAX_TABS } tabs`
		).toHaveProperty( [ label ] );
		expect(
			found[ label ].outlineStyle,
			`${ label }: outline suppressed`
		).not.toBe( 'none' );
		expect(
			found[ label ].outlineWidth,
			`${ label }: outline width is zero`
		).not.toBe( '0px' );
	}
} );
