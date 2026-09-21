/**
 * Editor-only block variations (04 §4). Plain script, no build; enqueued only on
 * enqueue_block_editor_assets. No network requests.
 */
wp.domReady( function () {
	wp.blocks.registerBlockVariation( 'core/query', {
		name: 'ttm/section-query',
		title: 'Section Query',
		isActive: [ 'namespace' ],
		scope: [ 'inserter', 'transform' ],
		attributes: {
			namespace: 'ttm/section-query',
			query: {
				perPage: 3,
				postType: 'post',
				inherit: false,
				ttmSection: '',
				ttmExcludeLead: true,
				ttmPrimaryOnly: true,
			},
		},
	} );

	wp.blocks.registerBlockVariation( 'core/query', {
		name: 'ttm/journal-query',
		title: 'Journal Query',
		isActive: [ 'namespace' ],
		scope: [ 'inserter', 'transform' ],
		attributes: {
			namespace: 'ttm/journal-query',
			query: {
				perPage: 3,
				postType: 'post',
				inherit: false,
				ttmSection: 'journal',
				ttmExcludeLead: false,
				ttmPrimaryOnly: true,
			},
		},
	} );

	wp.blocks.registerBlockVariation( 'core/navigation', {
		name: 'ttm/sections-nav',
		title: 'Sections Navigation',
		isActive: [ 'namespace' ],
		scope: [ 'inserter', 'transform' ],
		attributes: {
			namespace: 'ttm/sections-nav',
			overlayMenu: 'always',
			hasIcon: false,
			className: 'ttm-nav',
		},
	} );
} );
