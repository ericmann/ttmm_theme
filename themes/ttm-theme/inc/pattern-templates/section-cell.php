<?php
/**
 * Section cell template (no pattern header: not auto-registered from this directory).
 *
 * Included once per section by `inc/patterns.php`, with `$ttm_section` set to
 * `['slug' => ..., 'name' => ..., 'per_page' => ...]`; the captured output is registered as
 * `ttm/section-cell-{slug}` via `register_block_pattern()`.
 *
 * @package TTM\Theme
 *
 * @var array{slug: string, name: string, per_page: int} $ttm_section Section context. The dek
 *      (`core/post-excerpt` below) is always included here; the plugin's `Query\Cells` suppresses
 *      its rendering, per request, when the section is a stale year (F9, SPEC §3.1 rule 1).
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-cell ttm-cell"} -->
<div class="wp-block-group is-style-cell ttm-cell">
	<!-- wp:group {"className":"ttm-cell-heading","layout":{"type":"flex","justifyContent":"space-between"}} -->
	<div class="wp-block-group ttm-cell-heading">
		<!-- wp:heading {"level":6,"className":"ttm-cell-heading__label"} -->
		<h6 class="ttm-cell-heading__label"><?php echo esc_html( $ttm_section['name'] ); ?></h6>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-cell-heading__link","metadata":{"bindings":{"content":{"source":"ttm/category-count","args":{"category":"<?php echo esc_attr( $ttm_section['slug'] ); ?>","format":"articles"}}}}} -->
		<p class="ttm-cell-heading__link"></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"namespace":"ttm/section-query","query":{"perPage":<?php echo (int) $ttm_section['per_page']; ?>,"postType":"post","inherit":false,"ttmSection":"<?php echo esc_attr( $ttm_section['slug'] ); ?>","ttmExcludeLead":true,"ttmPrimaryOnly":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:group {"className":"ttm-item"} -->
			<div class="wp-block-group ttm-item">
				<!-- wp:post-title {"isLink":true,"className":"is-style-cell-lead"} /-->

				<!-- wp:post-excerpt {"className":"ttm-item__dek"} /-->

				<!-- wp:paragraph {"className":"ttm-item__meta","metadata":{"bindings":{"content":{"source":"ttm/meta-line","args":{"parts":["date","reading"]}}}}} -->
				<p class="ttm-item__meta"></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
