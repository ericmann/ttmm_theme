/**
 * Pure file-list filters for `release:pack` (SPEC §6.6, non-goal "committing
 * `plugins/ttm-core/build/`"). No I/O: take a flat list of repo-relative paths (forward
 * slashes), return `{from, to}` pairs -- `from` the repo path to read, `to` the path inside the
 * zip's slug directory.
 */

const PLUGIN_PREFIX = 'plugins/ttm-core/';
const THEME_PREFIX = 'themes/ttm-theme/';

/**
 * Paths kept for the plugin zip: everything under `plugins/ttm-core/` except `node_modules/`,
 * `tests/`, `vendor/`, `src/editor/`, any `.js` under `src/`, and any `.map` file -- which in
 * practice keeps everything under `build/`, `blocks/`, `assets/`, every PHP file under `src/`,
 * `ttm-core.php`, `uninstall.php`, `readme.txt` and `README.md`.
 *
 * @param {string[]} paths Repo-relative paths.
 * @return {{from: string, to: string}[]} Kept paths.
 */
export function pluginFiles( paths ) {
	return paths
		.filter( ( path ) => path.startsWith( PLUGIN_PREFIX ) )
		.map( ( path ) => path.slice( PLUGIN_PREFIX.length ) )
		.filter( ( rel ) => {
			if ( rel.startsWith( 'node_modules/' ) ) {
				return false;
			}
			if ( rel.startsWith( 'tests/' ) ) {
				return false;
			}
			if ( rel.startsWith( 'vendor/' ) ) {
				return false;
			}
			if ( rel.startsWith( 'src/editor/' ) ) {
				return false;
			}
			if ( rel.startsWith( 'src/' ) && rel.endsWith( '.js' ) ) {
				return false;
			}
			if ( rel.endsWith( '.map' ) ) {
				return false;
			}
			return true;
		} )
		.map( ( rel ) => ( { from: `${ PLUGIN_PREFIX }${ rel }`, to: rel } ) );
}

/**
 * Paths kept for the theme zip: everything under `themes/ttm-theme/` except `.map` files.
 *
 * @param {string[]} paths Repo-relative paths.
 * @return {{from: string, to: string}[]} Kept paths.
 */
export function themePaths( paths ) {
	return paths
		.filter( ( path ) => path.startsWith( THEME_PREFIX ) )
		.filter( ( path ) => ! path.endsWith( '.map' ) )
		.map( ( path ) => ( {
			from: path,
			to: path.slice( THEME_PREFIX.length ),
		} ) );
}
