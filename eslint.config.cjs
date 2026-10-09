/**
 * ESLint flat config. Mirrors the @wordpress/scripts default, but lints unit
 * tests with Jest rules: scripts 36 switched its default to Vitest.
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );

module.exports = [
	{ ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ] },
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [
			'**/@(test|__tests__)/**/*.{js,jsx,ts,tsx,mjs,cjs}',
			'**/*.@(test|spec).{js,jsx,ts,tsx,mjs,cjs}',
		],
	},
];
