<?php
/**
 * Title: Masthead — Inner
 * Slug: ttm/masthead-inner
 * Categories: ttm-front
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

$ttm_sections = [
	'technology' => 'Technology',
	'business'   => 'Business',
	'faith'      => 'Faith',
	'journal'    => 'Journal',
	'writing'    => 'Writing',
	'security'   => 'Security',
	'opinion'    => 'Opinion',
];

$ttm_nav_links = '';
foreach ( $ttm_sections as $ttm_slug => $ttm_name ) {
	$ttm_term       = get_category_by_slug( $ttm_slug );
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
<!-- wp:group {"className":"ttm-masthead-inner","layout":{"type":"grid","columns":3}} -->
<div class="wp-block-group ttm-masthead-inner">

	<!-- wp:group {"className":"ttm-masthead-inner__title","layout":{"type":"flex"}} -->
	<div class="wp-block-group ttm-masthead-inner__title">
		<!-- wp:site-title /-->
		<!-- wp:paragraph {"className":"ttm-masthead-inner__by"} -->
		<p class="ttm-masthead-inner__by"><?php esc_html_e( 'by Eric Mann', 'ttm-theme' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:navigation {"className":"ttm-nav ttm-masthead-inner__nav","overlayMenu":"always","hasIcon":false,"ariaLabel":"<?php echo esc_attr__( 'Sections', 'ttm-theme' ); ?>"} -->
	<?php echo $ttm_nav_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr()/esc_url()-escaped pieces above. ?>
	<!-- /wp:navigation -->

	<!-- wp:paragraph {"className":"ttm-masthead-inner__aside"} -->
	<p class="ttm-masthead-inner__aside"><a href="<?php echo esc_url( home_url( '/newsletter/' ) ); ?>"><?php esc_html_e( 'Newsletter', 'ttm-theme' ); ?></a></p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
