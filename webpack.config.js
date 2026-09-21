/**
 * wp-scripts webpack config: builds the editor bundle (plugins/ttm-core/src/index.js)
 * plus one entry per plugins/ttm-core/blocks/<name>/index.js, all into
 * plugins/ttm-core/build/.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const base = Array.isArray( defaultConfig ) ? defaultConfig[ 0 ] : defaultConfig;

const blocksDir = path.resolve( __dirname, 'plugins/ttm-core/blocks' );
const entry = {
	index: path.resolve( __dirname, 'plugins/ttm-core/src/index.js' ),
};

if ( fs.existsSync( blocksDir ) ) {
	for ( const name of fs.readdirSync( blocksDir ) ) {
		const indexFile = path.join( blocksDir, name, 'index.js' );
		if ( fs.existsSync( indexFile ) ) {
			entry[ `blocks/${ name }/index` ] = indexFile;
		}
	}
}

const output = {
	...base.output,
	path: path.resolve( __dirname, 'plugins/ttm-core/build' ),
};

module.exports = {
	...base,
	entry,
	output,
};
