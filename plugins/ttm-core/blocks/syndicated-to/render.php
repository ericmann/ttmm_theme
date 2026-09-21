<?php
/**
 * `ttm/syndicated-to` render (01 §4.13): single Journal posts only, F14 -> '' with no URLs.
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
?>
<p <?php echo Helpers::wrapper( 'syndication' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php
	printf(
		/* translators: %s: linked network names, e.g. "X and Mastodon". */
		__( 'Syndicated to %s', 'ttm-core' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- interpolates only Html::link()'d fragments and trusted translator text.
		$ttm_joined // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::link() output is already escaped.
	);
	?>
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
</p>
