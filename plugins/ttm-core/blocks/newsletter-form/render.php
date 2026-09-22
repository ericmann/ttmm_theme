<?php
/**
 * `ttm/newsletter-form` render: the provider switch (jetpack/mailto/none), F26.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Newsletter\Providers;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_placement = 'box' === ( $attributes['placement'] ?? 'poster' ) ? 'box' : 'poster';
$ttm_provider  = Providers::current();

// Read-only display flag; no value is reflected, only its presence (SPEC §6.1/§6.3/F26).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no nonce on cacheable output (rule 7).
$ttm_subscribe_query = isset( $_GET['subscribe'] ) ? sanitize_text_field( wp_unslash( $_GET['subscribe'] ) ) : '';
$ttm_subscribed      = isset( $_GET['subscribed'] ) || 'success' === $ttm_subscribe_query; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no nonce on cacheable output (rule 7).

$ttm_extra = [ 'data-provider' => $ttm_provider->slug() ];
if ( $ttm_subscribed ) {
	$ttm_extra['data-state'] = 'subscribed';
}
?>
<div <?php echo Helpers::wrapper( 'newsletter-form', [ 'is-' . $ttm_placement ], $ttm_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php echo $ttm_provider->render( $ttm_placement ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each provider escapes its own output. ?>
	<p class="ttm-newsletter-form__done"><?php esc_html_e( 'Check your inbox.', 'ttm-core' ); ?></p>
</div>
