<?php
/**
 * Title: Masthead — Front
 * Slug: ttm/masthead-front
 * Categories: ttm-front
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

// Fallback labels only: used when a section's category term doesn't exist yet (e.g. before
// starter content runs). Once the term exists, its own (possibly owner-renamed) name is used
// instead -- the theme reads the category, never re-derives a name of its own (rule 1).
$ttm_sections = [
	'technology' => __( 'Technology', 'ttm-theme' ),
	'business'   => __( 'Business', 'ttm-theme' ),
	'faith'      => __( 'Faith', 'ttm-theme' ),
	'journal'    => __( 'Journal', 'ttm-theme' ),
	'writing'    => __( 'Writing', 'ttm-theme' ),
	'security'   => __( 'Security', 'ttm-theme' ),
	'opinion'    => __( 'Opinion', 'ttm-theme' ),
];

$ttm_nav_links = '';
foreach ( $ttm_sections as $ttm_slug => $ttm_fallback_name ) {
	$ttm_term       = get_category_by_slug( $ttm_slug );
	$ttm_name       = $ttm_term ? $ttm_term->name : $ttm_fallback_name;
	$ttm_url        = $ttm_term ? get_category_link( $ttm_term ) : home_url( '/category/' . $ttm_slug . '/' );
	$ttm_nav_links .= sprintf(
		'<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->',
		esc_attr( $ttm_name ),
		esc_url( $ttm_url )
	);
}
$ttm_nav_links .= sprintf(
	'<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom","className":"ttm-nav__hub"} /-->',
	esc_attr__( 'Series', 'ttm-theme' ),
	esc_url( home_url( '/series/' ) )
);
?>
<!-- wp:group {"className":"ttm-masthead-front","layout":{"type":"constrained"}} -->
<div class="wp-block-group ttm-masthead-front">

	<!-- wp:group {"className":"ttm-masthead-front__meta","layout":{"type":"flex","justifyContent":"space-between"}} -->
	<div class="wp-block-group ttm-masthead-front__meta">
		<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"ttm/today","args":{"format":"masthead"}}}},"className":"is-style-meta"} -->
		<p class="is-style-meta"></p>
		<!-- /wp:paragraph -->

		<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex"}} -->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'Newsletter', 'ttm-theme' ); ?>","url":"<?php echo esc_url( home_url( '/newsletter/' ) ); ?>","kind":"custom"} /-->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'RSS', 'ttm-theme' ); ?>","url":"<?php echo esc_url( get_feed_link() ); ?>","kind":"custom"} /-->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'About', 'ttm-theme' ); ?>","url":"<?php echo esc_url( home_url( '/about/' ) ); ?>","kind":"custom"} /-->
		<!-- /wp:navigation -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"ttm-masthead-front__title","layout":{"type":"grid","columns":2}} -->
	<div class="wp-block-group ttm-masthead-front__title">
		<!-- wp:group {"layout":{"type":"constrained"}} -->
		<div class="wp-block-group">
			<!-- wp:site-title {"className":"is-style-display-l"} /-->
			<!-- wp:paragraph {"className":"ttm-masthead-front__byline"} -->
			<p class="ttm-masthead-front__byline"><span><?php esc_html_e( 'by', 'ttm-theme' ); ?></span> <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'Eric Mann', 'ttm-theme' ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<!-- wp:site-tagline {"className":"ttm-masthead-front__tagline"} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:separator {"className":"is-style-rule-2"} -->
	<hr class="wp-block-separator is-style-rule-2"/>
	<!-- /wp:separator -->

	<!-- wp:navigation {"className":"ttm-nav ttm-masthead-front__nav","overlayMenu":"never","ariaLabel":"<?php echo esc_attr__( 'Sections', 'ttm-theme' ); ?>"} -->
	<?php echo $ttm_nav_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr()/esc_url()-escaped pieces above. ?>
	<!-- /wp:navigation -->

	<!-- wp:separator {"className":"is-style-rule-2"} -->
	<hr class="wp-block-separator is-style-rule-2"/>
	<!-- /wp:separator -->

</div>
<!-- /wp:group -->
