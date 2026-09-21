/**
 * Every request a seeded screen makes must be same-origin, `data:`, or one of a small allow-list
 * of Jetpack hosts (stats/subscribe - SPEC's own non-goal is analytics, but Jetpack's front-end
 * script that ships when the plugin is active is out of this repo's control). Nothing may hit
 * `/wp-json/` or `admin-ajax.php` on the front end (SPEC §3.2 rule 6/7, rule 16).
 */

import { test, expect } from '@playwright/test';
import { SCREEN_URLS } from '../lib/urls.mjs';

// Jetpack's own front-end assets/beacons, only relevant when Jetpack happens to be active
// (e.g. during a migration rehearsal per docs/MIGRATION.md) - never requests this repo makes.
const JETPACK_HOST_PATTERN =
	/^(?:stats\.wp\.com|s0\.wp\.com|jetpack\.com|[a-z0-9-]+\.wordpress\.com)$/i;
const FORBIDDEN_PATH_PATTERN = /\/wp-json\/|admin-ajax\.php/;

for ( const path of SCREEN_URLS ) {
	test( `network allow-list: ${ path }`, async ( { page, baseURL } ) => {
		const baseHost = new URL( baseURL ).host;
		const requestUrls = [];

		page.on( 'request', ( request ) => {
			requestUrls.push( request.url() );
		} );

		await page.goto( path );
		await page.waitForLoadState( 'networkidle' );

		const offenders = requestUrls.filter( ( url ) => {
			// `data:`/`blob:` never leave the browser - a `blob:` URL is how Chrome represents an
			// already-fetched resource it re-serves internally (observed for the page's own
			// favicon), not a new network request.
			if ( url.startsWith( 'data:' ) || url.startsWith( 'blob:' ) ) {
				return false;
			}

			const parsed = new URL( url );
			if ( parsed.host === baseHost ) {
				return FORBIDDEN_PATH_PATTERN.test( parsed.pathname );
			}

			return ! JETPACK_HOST_PATTERN.test( parsed.host );
		} );

		expect( offenders, JSON.stringify( offenders, null, 2 ) ).toEqual( [] );
	} );
}
