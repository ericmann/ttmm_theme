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

		const verify = rendered.steps.find(
			( step ) =>
				'wp-cli' === step.step && step.command.includes( 'demo:verify' )
		);
		expect( verify.command ).toBe(
			'wp ttm demo:verify --posts=107 --pages=4 --series=7 --attachments=13'
		);

		// The original template is left untouched (no accidental in-place mutation).
		expect( TEMPLATE.steps[ 0 ].options ).toBe( '{{OPTIONS}}' );
	} );

	it( 'keeps the SPEC §6.4 step order plus site empty before importWxr', () => {
		const rendered = renderBlueprint( TEMPLATE, {
			release: 'v0.2.0',
			options: {},
			verifyArgs: '--posts=0 --pages=0 --series=0 --attachments=0',
		} );

		const order = rendered.steps.map( ( step ) =>
			'wp-cli' === step.step ? step.command : step.step
		);

		expect( order ).toEqual( [
			'setSiteOptions',
			'installPlugin',
			'installTheme',
			'wp site empty --yes',
			'importWxr',
			'wp rewrite structure /%postname%/ --hard',
			'wp ttm primary:assign',
			'wp ttm recount --all',
			'wp ttm series:rebuild',
			'wp ttm stats:flush',
			'wp ttm demo:verify --posts=0 --pages=0 --series=0 --attachments=0',
		] );

		const importWxrIndex = order.indexOf( 'importWxr' );
		const siteEmptyIndex = order.indexOf( 'wp site empty --yes' );
		expect( siteEmptyIndex ).toBeLessThan( importWxrIndex );
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
