<?php
/**
 * Every `block:name` pair from docs/04-theme-spec.md §3, for BlockStylesTest set-equality.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

return [
	'core/separator:rule-2',
	'core/separator:rule-1',

	'core/image:grayscale',
	'core/post-featured-image:grayscale',
	'core/image:cover',
	'core/post-featured-image:cover',
	'core/image:cover-shadow',
	'core/post-featured-image:cover-shadow',

	'core/group:poster',
	'core/group:surface-box',
	'core/group:tile',
	'core/group:zone',
	'core/group:cell',
	'core/group:grid-4',
	'core/group:grid-8-4',
	'core/group:grid-3-7-2',
	'core/group:grid-5-7',
	'core/group:grid-7-5',
	'core/group:grid-3',
	'core/group:grid-2',
	'core/group:span-2',
	'core/group:sticky-aside',

	'core/heading:cell-heading',

	'core/paragraph:kicker',
	'core/paragraph:dek',
	'core/paragraph:dek-l',
	'core/paragraph:meta',
	'core/paragraph:micro',
	'core/paragraph:display-xl',
	'core/paragraph:display-l',
	'core/paragraph:display-m',
	'core/paragraph:lead',
	'core/paragraph:poster',

	'core/post-title:lead',
	'core/heading:lead',
	'core/post-title:cell-lead',
	'core/heading:cell-lead',
	'core/post-title:cell-lead-l',
	'core/heading:cell-lead-l',
	'core/post-title:headline-s',
	'core/heading:headline-s',
	'core/post-title:journal-title',
	'core/heading:journal-title',
	'core/post-title:journal-date',
	'core/heading:journal-date',

	'core/post-date:journal-date',
	'core/post-date:short',

	'core/quote:pull',

	'core/buttons:primary',
	'core/button:primary',
	'core/buttons:secondary',
	'core/button:secondary',
	'core/buttons:ghost',
	'core/button:ghost',
	'core/buttons:ghost-on-poster',
	'core/button:ghost-on-poster',
	'core/buttons:block',
	'core/button:block',

	'core/post-terms:kicker',
	'core/post-terms:tags',

	'core/list:numbered-rows',
];
