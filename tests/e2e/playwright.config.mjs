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
			testIgnore: /(fidelity|editors|phone)\.spec\.mjs$/,
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 1280, height: 900 },
			},
		},
		{
			// P4-01: phone.spec.mjs is phone-only (390px-specific layout assertions -- no
			// horizontal overflow, nav scroll, stacked poster/writing cell), so `desktop`
			// excludes it above rather than running it twice at the wrong viewport.
			name: 'phone',
			testIgnore: /(fidelity|editors)\.spec\.mjs$/,
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 390, height: 844 },
			},
		},
		{
			// SPEC §3.2 rule 38 / §6.2: every front-page fidelity assertion and the two
			// editor-registration checks, run against the `fidelity`/`editors` spec files that
			// live directly under tests/e2e/ (not tests/e2e/specs/, which is phase 1's suite).
			name: 'fidelity',
			testDir: '.',
			testMatch: /(fidelity|editors)\.spec\.mjs$/,
			use: {
				...devices[ 'Desktop Chrome' ],
			},
		},
	],
} );
