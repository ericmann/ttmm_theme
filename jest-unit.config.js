/**
 * Extends @wordpress/scripts' default Jest unit config so the P8-01 spike's `.mjs` helper
 * modules (scripts/lib/footnotes.mjs, scripts/convert-classic.mjs) get babel-transformed to
 * CommonJS for Jest, the same way its own .js files already are -- Jest's default transform
 * pattern only matches .js/.jsx/.ts/.tsx, and a bare `import()` of a real ESM file is treated
 * as a require() of an untransformed module by this Jest/Node combination, which fails.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	transform: {
		'\\.m?[jt]sx?$': path.join(
			path.dirname( require.resolve( '@wordpress/scripts/package.json' ) ),
			'config',
			'babel-transform'
		),
	},
};
