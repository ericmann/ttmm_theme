/**
 * Jest unit config on the published WordPress preset (@wordpress/scripts 36 no longer ships
 * its Jest defaults; `test-unit-jest` keeps this suite on Jest). The transform also matches
 * `.mjs` so the script helper modules get babel-transformed to CommonJS for Jest -- a bare
 * `import()` of a real ESM file is treated as a require() of an untransformed module by this
 * Jest/Node combination, which fails.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	transform: {
		'\\.m?[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
};
