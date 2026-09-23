<?php
/**
 * Title: Archive Header
 * Slug: ttm/archive-header
 * Categories: ttm-lists
 * Inserter: no
 *
 * SPEC §6.6 "Archive header": kicker (bound to `ttm/archive-kind`: "Section" / "Tag" / "Month"
 * ...; the static text is the plugin-inactive fallback), the prefix-less query title, the term
 * description and the stats column. Shared by category.html, category-journal.html and
 * archive.html.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-grid-8-4 ttm-archive-head","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-grid-8-4 ttm-archive-head">
	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"className":"is-style-kicker","metadata":{"bindings":{"content":{"source":"ttm/archive-kind"}}}} -->
		<p class="is-style-kicker"><?php esc_html_e( 'Section', 'ttm-theme' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"is-style-display-xl"} /-->

		<!-- wp:term-description /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:ttm/category-stats /-->
</div>
<!-- /wp:group -->
