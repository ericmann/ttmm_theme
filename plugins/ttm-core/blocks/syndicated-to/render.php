<?php
/**
 * `ttm/syndicated-to` render (01 §4.13, SPEC §6.4): single Journal posts only, F14 -> '' with no
 * URLs. Two spans (sentence + word count) inside the flex wrapper; no `<p>`.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block              $block      Block instance (postId context).
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Support\Html;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
if ( ! $ttm_post_id ) {
	return '';
}

$ttm_category_id = PrimaryCategory::id( $ttm_post_id );
$ttm_category    = $ttm_category_id ? get_term( $ttm_category_id, 'category' ) : null;
$ttm_is_journal  = $ttm_category && ! is_wp_error( $ttm_category ) && (string) Config::get( 'sections.journal_slug', 'journal' ) === $ttm_category->slug;

if ( ! $ttm_is_journal ) {
	return '';
}

$ttm_syndication = (array) get_post_meta( $ttm_post_id, 'ttm_syndication', true );
$ttm_labels      = [
	'x'        => __( 'X', 'ttm-core' ),
	'mastodon' => __( 'Mastodon', 'ttm-core' ),
	'bluesky'  => __( 'Bluesky', 'ttm-core' ),
];

$ttm_links = [];
foreach ( $ttm_labels as $ttm_network => $ttm_label ) {
	$ttm_url = (string) ( $ttm_syndication[ $ttm_network ] ?? '' );
	if ( '' !== $ttm_url ) {
		$ttm_links[] = Html::link( $ttm_url, $ttm_label );
	}
}

if ( empty( $ttm_links ) ) {
	return '';
}

if ( 1 === count( $ttm_links ) ) {
	$ttm_joined = $ttm_links[0];
} else {
	$ttm_last = array_pop( $ttm_links );
	/* translators: used to join the last two networks, e.g. "X and Mastodon". */
	$ttm_joined = implode( ', ', $ttm_links ) . ' ' . __( 'and', 'ttm-core' ) . ' ' . $ttm_last;
}

$ttm_words = (int) get_post_meta( $ttm_post_id, 'ttm_word_count', true );

// wp_kses (not just trusting Html::link()'s own escaping) so a hostile *translation* of
// "Syndicated to %s" -- e.g. via a gettext filter -- can't inject anything beyond a plain
// link (rule 14: every echo escaped, translator text included).
$ttm_sentence = wp_kses(
	sprintf(
		/* translators: %s: linked network names, e.g. "X and Mastodon". */
		__( 'Syndicated to %s', 'ttm-core' ),
		$ttm_joined
	),
	[ 'a' => [ 'href' => [] ] ]
);
?>
<div <?php echo Helpers::wrapper( 'syndication' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<span class="ttm-syndication__text"><?php echo $ttm_sentence; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses()'d above. ?></span>
	<span class="ttm-syndication__words">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: word count. */
				_n( '%d word', '%d words', $ttm_words, 'ttm-core' ),
				$ttm_words
			)
		);
		?>
	</span>
</div>
