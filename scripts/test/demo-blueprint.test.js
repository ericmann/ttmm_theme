/**
 * Tests for the P2-04 pure blueprint rendering (scripts/demo/lib/blueprint.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );

let renderBlueprint;
let releaseFromPluginHeader;
let verifyArgs;

const TEMPLATE = JSON.parse(
	fs.readFileSync(
		path.join( __dirname, '..', 'demo', 'blueprint.template.json' ),
		'utf8'
	)
);

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'blueprint.mjs' )
	);
	renderBlueprint = mod.renderBlueprint;
	releaseFromPluginHeader = mod.releaseFromPluginHeader;
	verifyArgs = mod.verifyArgs;
} );

describe( 'renderBlueprint', () => {
	it( 'renders the template with options, release and verify args', () => {
		const options = { blogname: 'These Things Matter' };
		const rendered = renderBlueprint( TEMPLATE, {
			release: 'v0.2.0',
			options,
			verifyArgs: '--posts=107 --pages=4 --series=7 --attachments=13',
		} );

		const setSiteOptions = rendered.steps.find(
			( step ) => 'setSiteOptions' === step.step
		);
		expect( setSiteOptions.options ).toEqual( options );

		const installPlugin = rendered.steps.find(
			( step ) => 'installPlugin' === step.step
		);
		expect( installPlugin.pluginData.url ).toContain( 'release=v0.2.0' );
		expect( installPlugin.pluginData.url ).toContain(
			'asset=ttm-core.zip'
		);

		const installTheme = rendered.steps.find(
			( step ) => 'installTheme' === step.step
		);
		expect( installTheme.themeData.url ).toContain( 'release=v0.2.0' );
		expect( installTheme.themeData.url ).toContain( 'asset=ttm-theme.zip' );

		const evalStep = rendered.steps.find(
			( step ) => 'wp-cli' === step.step && Array.isArray( step.command )
		);
		expect( evalStep.command ).toEqual( [
			'wp',
			'eval',
			expect.any( String ),
		] );
		expect( evalStep.command[ 2 ] ).toContain(
			"'--posts=107 --pages=4 --series=7 --attachments=13'"
		);
		expect( evalStep.command[ 2 ] ).toContain( 'DemoCommand()' );

		// The original template is left untouched (no accidental in-place mutation).
		expect( TEMPLATE.steps[ 0 ].options ).toBe( '{{OPTIONS}}' );
	} );

	it( 'keeps the SPEC §6.4 step order plus site empty before importWxr', () => {
		const rendered = renderBlueprint( TEMPLATE, {
			release: 'v0.2.0',
			options: {},
			verifyArgs: '--posts=0 --pages=0 --series=0 --attachments=0',
		} );

		// The post-import work (rewrite/primary:assign/recount/series:rebuild/stats:flush/
		// demo:verify) is one combined `wp eval` step, not six separate `wp-cli` steps --
		// Playground's php.wasm hits a hard memory trap by roughly the ninth separate `wp-cli`
		// blueprint step in a boot, confirmed empirically (docs/spikes/P2-01.md's own follow-up).
		const order = rendered.steps.map( ( step ) => {
			if ( 'wp-cli' !== step.step ) {
				return step.step;
			}
			return Array.isArray( step.command )
				? step.command.slice( 0, 2 ).join( ' ' )
				: step.command;
		} );

		expect( order ).toEqual( [
			'setSiteOptions',
			'installPlugin',
			'installTheme',
			'wp site empty --yes',
			'importWxr',
			'wp eval',
		] );

		const importWxrIndex = order.indexOf( 'importWxr' );
		const siteEmptyIndex = order.indexOf( 'wp site empty --yes' );
		expect( siteEmptyIndex ).toBeLessThan( importWxrIndex );

		const evalCode =
			rendered.steps[ rendered.steps.length - 1 ].command[ 2 ];
		expect( evalCode ).toContain( 'PrimaryCommand' );
		expect( evalCode ).toContain( 'RecountCommand' );
		expect( evalCode ).toContain( 'SeriesCommand' );
		expect( evalCode ).toContain( 'Stats::flush_all' );
		expect( evalCode ).toContain( 'DemoCommand' );
	} );
} );

describe( 'releaseFromPluginHeader', () => {
	it( 'reads the release tag from the plugin header', () => {
		const php = fs.readFileSync(
			path.join(
				__dirname,
				'..',
				'..',
				'plugins',
				'ttm-core',
				'ttm-core.php'
			),
			'utf8'
		);

		expect( releaseFromPluginHeader( php ) ).toMatch( /^v\d+\.\d+\.\d+$/ );
	} );
} );

describe( 'verifyArgs', () => {
	it( 'formats the four counts', () => {
		expect( verifyArgs( { post: 107, page: 4, attachment: 13 }, 7 ) ).toBe(
			'--posts=107 --pages=4 --series=7 --attachments=13'
		);
	} );
} );
