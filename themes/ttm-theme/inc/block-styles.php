<?php
/**
 * Block styles from 04 §3. CSS lives in assets/css/ttm.css; no inline_style here.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * Pure: every {block, name, label} pair from 04 §3. Multi-block/multi-slug rows
 * are expanded to one entry per (block, slug) pair.
 *
 * @return array<int, array{block: string, name: string, label: string, is_default?: bool}>
 */
function block_styles(): array {
	return [
		[
			'block' => 'core/separator',
			'name'  => 'rule-2',
			'label' => __( 'Rule 2', 'ttm-theme' ),
		],
		[
			'block' => 'core/separator',
			'name'  => 'rule-1',
			'label' => __( 'Rule 1', 'ttm-theme' ),
		],

		[
			'block' => 'core/image',
			'name'  => 'grayscale',
			'label' => __( 'Grayscale', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-featured-image',
			'name'  => 'grayscale',
			'label' => __( 'Grayscale', 'ttm-theme' ),
		],
		[
			'block' => 'core/image',
			'name'  => 'cover',
			'label' => __( 'Cover', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-featured-image',
			'name'  => 'cover',
			'label' => __( 'Cover', 'ttm-theme' ),
		],
		[
			'block' => 'core/image',
			'name'  => 'cover-shadow',
			'label' => __( 'Cover Shadow', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-featured-image',
			'name'  => 'cover-shadow',
			'label' => __( 'Cover Shadow', 'ttm-theme' ),
		],

		[
			'block' => 'core/group',
			'name'  => 'poster',
			'label' => __( 'Poster', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'surface-box',
			'label' => __( 'Surface Box', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'tile',
			'label' => __( 'Tile', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'zone',
			'label' => __( 'Zone', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'cell',
			'label' => __( 'Cell', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-4',
			'label' => __( 'Grid 4', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-8-4',
			'label' => __( 'Grid 8-4', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-3-7-2',
			'label' => __( 'Grid 3-7-2', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-5-7',
			'label' => __( 'Grid 5-7', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-7-5',
			'label' => __( 'Grid 7-5', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-3',
			'label' => __( 'Grid 3', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'grid-2',
			'label' => __( 'Grid 2', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'span-2',
			'label' => __( 'Span 2', 'ttm-theme' ),
		],
		[
			'block' => 'core/group',
			'name'  => 'sticky-aside',
			'label' => __( 'Sticky Aside', 'ttm-theme' ),
		],

		[
			'block' => 'core/heading',
			'name'  => 'cell-heading',
			'label' => __( 'Cell Heading', 'ttm-theme' ),
		],

		[
			'block' => 'core/paragraph',
			'name'  => 'kicker',
			'label' => __( 'Kicker', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'dek',
			'label' => __( 'Dek', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'dek-l',
			'label' => __( 'Dek Large', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'meta',
			'label' => __( 'Meta', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'micro',
			'label' => __( 'Micro', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'display-xl',
			'label' => __( 'Display XL', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'display-l',
			'label' => __( 'Display Large', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'display-m',
			'label' => __( 'Display Medium', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'lead',
			'label' => __( 'Lead', 'ttm-theme' ),
		],
		[
			'block' => 'core/paragraph',
			'name'  => 'poster',
			'label' => __( 'Poster', 'ttm-theme' ),
		],

		[
			'block' => 'core/post-title',
			'name'  => 'lead',
			'label' => __( 'Lead', 'ttm-theme' ),
		],
		[
			'block' => 'core/heading',
			'name'  => 'lead',
			'label' => __( 'Lead', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-title',
			'name'  => 'cell-lead',
			'label' => __( 'Cell Lead', 'ttm-theme' ),
		],
		[
			'block' => 'core/heading',
			'name'  => 'cell-lead',
			'label' => __( 'Cell Lead', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-title',
			'name'  => 'cell-lead-l',
			'label' => __( 'Cell Lead Large', 'ttm-theme' ),
		],
		[
			'block' => 'core/heading',
			'name'  => 'cell-lead-l',
			'label' => __( 'Cell Lead Large', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-title',
			'name'  => 'headline-s',
			'label' => __( 'Headline Small', 'ttm-theme' ),
		],
		[
			'block' => 'core/heading',
			'name'  => 'headline-s',
			'label' => __( 'Headline Small', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-title',
			'name'  => 'journal-title',
			'label' => __( 'Journal Title', 'ttm-theme' ),
		],
		[
			'block' => 'core/heading',
			'name'  => 'journal-title',
			'label' => __( 'Journal Title', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-title',
			'name'  => 'journal-date',
			'label' => __( 'Journal Date', 'ttm-theme' ),
		],
		[
			'block' => 'core/heading',
			'name'  => 'journal-date',
			'label' => __( 'Journal Date', 'ttm-theme' ),
		],

		[
			'block' => 'core/post-date',
			'name'  => 'journal-date',
			'label' => __( 'Journal Date', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-date',
			'name'  => 'short',
			'label' => __( 'Short', 'ttm-theme' ),
		],

		[
			'block' => 'core/quote',
			'name'  => 'pull',
			'label' => __( 'Pull Quote', 'ttm-theme' ),
		],

		[
			'block'      => 'core/buttons',
			'name'       => 'primary',
			'label'      => __( 'Primary', 'ttm-theme' ),
			'is_default' => true,
		],
		[
			'block'      => 'core/button',
			'name'       => 'primary',
			'label'      => __( 'Primary', 'ttm-theme' ),
			'is_default' => true,
		],
		[
			'block' => 'core/buttons',
			'name'  => 'secondary',
			'label' => __( 'Secondary', 'ttm-theme' ),
		],
		[
			'block' => 'core/button',
			'name'  => 'secondary',
			'label' => __( 'Secondary', 'ttm-theme' ),
		],
		[
			'block' => 'core/buttons',
			'name'  => 'ghost',
			'label' => __( 'Ghost', 'ttm-theme' ),
		],
		[
			'block' => 'core/button',
			'name'  => 'ghost',
			'label' => __( 'Ghost', 'ttm-theme' ),
		],
		[
			'block' => 'core/buttons',
			'name'  => 'ghost-on-poster',
			'label' => __( 'Ghost on Poster', 'ttm-theme' ),
		],
		[
			'block' => 'core/button',
			'name'  => 'ghost-on-poster',
			'label' => __( 'Ghost on Poster', 'ttm-theme' ),
		],
		[
			'block' => 'core/buttons',
			'name'  => 'block',
			'label' => __( 'Block', 'ttm-theme' ),
		],
		[
			'block' => 'core/button',
			'name'  => 'block',
			'label' => __( 'Block', 'ttm-theme' ),
		],

		[
			'block' => 'core/post-terms',
			'name'  => 'kicker',
			'label' => __( 'Kicker', 'ttm-theme' ),
		],
		[
			'block' => 'core/post-terms',
			'name'  => 'tags',
			'label' => __( 'Tags', 'ttm-theme' ),
		],

		[
			'block' => 'core/list',
			'name'  => 'numbered-rows',
			'label' => __( 'Numbered Rows', 'ttm-theme' ),
		],
	];
}

/**
 * Register every block style on `init`.
 */
function register_block_styles(): void {
	foreach ( block_styles() as $style ) {
		register_block_style(
			$style['block'],
			[
				'name'       => $style['name'],
				'label'      => $style['label'],
				'is_default' => $style['is_default'] ?? false,
			]
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles' );
