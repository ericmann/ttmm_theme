<?php
/**
 * `ttm/verse-of-the-day` render: today's verse, else the last good verse with its own date (F6).
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Text;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_verse = get_option( 'ttm_verse' );
$ttm_today = Clock::today();

if ( empty( $ttm_verse ) || ( $ttm_verse['date'] ?? '' ) !== $ttm_today ) {
	$ttm_history = (array) get_option( 'ttm_verse_history', [] );
	$ttm_verse   = null;

	foreach ( $ttm_history as $ttm_candidate ) {
		if ( ( $ttm_candidate['date'] ?? '' ) <= $ttm_today ) {
			$ttm_verse = $ttm_candidate;
			break;
		}
	}
}

if ( empty( $ttm_verse ) ) {
	return '';
}

$ttm_verse_date  = Clock::at( $ttm_verse['date'] );
$ttm_date_label  = $ttm_verse_date ? $ttm_verse_date->format( 'M j' ) : '';
$ttm_url         = ! empty( $ttm_verse['url'] ) ? $ttm_verse['url'] : 'https://dailymedtoday.com/';
$ttm_text        = wp_kses( Text::curly_quotes( $ttm_verse['text'] ?? '' ), [ 'em' => [], 'strong' => [] ] );

$ttm_classes = [];
if ( ! empty( $attributes['compact'] ) ) {
	$ttm_classes[] = 'is-compact';
}

?>
<div <?php echo Helpers::wrapper( 'verse', $ttm_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<p class="is-style-kicker"><?php esc_html_e( 'Verse of the day', 'ttm-core' ); ?></p>
	<p class="ttm-verse__text">“<?php echo $ttm_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses()'d above. ?>”</p>
	<p class="ttm-verse__reference"><?php echo esc_html( $ttm_verse['reference'] ?? '' ); ?></p>
	<p class="ttm-verse__attribution">
		<?php
		printf(
			/* translators: 1: date, 2: linked domain */
			esc_html__( 'Meditation for %1$s from %2$s', 'ttm-core' ),
			esc_html( $ttm_date_label ),
			'<a href="' . esc_url( $ttm_url ) . '">dailymedtoday.com</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url()'d above.
		);
		?>
	</p>
	<?php if ( ! empty( $ttm_verse['copyright'] ) ) : ?>
		<small class="ttm-verse__copyright"><?php echo esc_html( $ttm_verse['copyright'] ); ?></small>
	<?php endif; ?>
</div>
