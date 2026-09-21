/**
 * Playwright config for the P8-07 browser suite (SPEC §3.4 rule 33, Phase 8).
 *
 * Runs against the wp-env **dev** site (default `http://localhost:8888`, the one
 * `wp-env run cli wp ttm seed --reset` populates), not the tests instance on 8889 - see the
 * `WP_BASE_URL` override in `package.json`'s `test:e2e` script for why that has to be explicit
 * (wp-scripts' own Playwright runner otherwise defaults `WP_BASE_URL` to the *tests* environment's
 * port when @wordpress/env is installed and the variable isn't already set).
 */

import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	// Resolved relative to this config file's own directory (tests/e2e/), which is what
	// Playwright's `testDir` always does regardless of the invoking process's cwd - so this
	// points at tests/e2e/specs the same whether Playwright is run from the repo root (as
	// `npm run test:e2e` does) or from tests/e2e/ directly.
	testDir: 'specs',
	reporter: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
	retries: 0,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
	},
	projects: [
		{
			name: 'desktop',
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 1280, height: 900 },
			},
		},
		{
			name: 'phone',
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 390, height: 844 },
			},
		},
	],
} );
