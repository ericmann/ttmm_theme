<?php
/**
 * `ttm/archive-by-year` render: wraps the already-year-grouped inner content (F15).
 *
 * The actual grouping happens in `Blocks\Helpers::group_by_year()` on the
 * `render_block_core/post-template` filter, while `Helpers::$archive_scope` is > 0 — that
 * filter fires while this block's inner `core/query` is rendering, before this file runs.
 * This file only owns the wrapper and closing the scope back down.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Already-rendered (and year-grouped) inner content.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;

Helpers::$archive_scope = max( 0, Helpers::$archive_scope - 1 );

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

// Rule 50: this block has no context of its own (no postId, no queried term) -- it only ever
// wraps an inner query's already-rendered rows. No inner content (e.g. used with no inner
// `core/query` at all) means nothing to wrap, so it returns '' rather than an empty shell.
if ( '' === trim( (string) $content ) ) {
	return '';
}
?>
<div <?php echo Helpers::wrapper( 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-rendered, escaped inner block HTML. ?>
</div>
