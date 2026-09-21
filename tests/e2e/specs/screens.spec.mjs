/**
 * Seven seeded screens x two viewports: exactly one <main>, zero serious/critical axe
 * violations, and a single high-priority hero image on the front page and an article
 * (SPEC §3.4 rule 33, Phase 8).
 */

import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { SCREENS } from '../lib/urls.mjs';

const SERIOUS_IMPACTS = [ 'serious', 'critical' ];

for ( const [ name, path ] of Object.entries( SCREENS ) ) {
	test.describe( `screen: ${ name } (${ path })`, () => {
		test( 'renders exactly one <main> landmark', async ( { page } ) => {
			await page.goto( path );
			await expect( page.locator( 'main' ) ).toHaveCount( 1 );
		} );

		test( 'has zero serious/critical axe violations', async ( {
			page,
		} ) => {
			await page.goto( path );

			const results = await new AxeBuilder( { page } ).analyze();
			const blocking = results.violations.filter( ( violation ) =>
				SERIOUS_IMPACTS.includes( violation.impact )
			);

			// Attach the full violation list (including moderate/minor, informational only) so a
			// failure's report shows everything axe found, not just the ones that fail the test.
			test.info().attach( `${ name }-axe-violations.json`, {
				body: JSON.stringify( results.violations, null, 2 ),
				contentType: 'application/json',
			} );

			expect( blocking, JSON.stringify( blocking, null, 2 ) ).toEqual(
				[]
			);
		} );

		if ( 'front' === name || 'article' === name ) {
			test( 'has exactly one high-priority hero image', async ( {
				page,
			} ) => {
				await page.goto( path );
				await expect(
					page.locator( 'img[fetchpriority="high"]' )
				).toHaveCount( 1 );
			} );
		}
	} );
}
