<?php
/**
 * `ttm/writing-cell` render (01 §4.15): active serial, shelf (F1), or plain cell (F2).
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Fiction\Serials;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_also_limit = (int) ( $attributes['alsoRunningLimit'] ?? 0 );
if ( $ttm_also_limit <= 0 ) {
	$ttm_also_limit = (int) Config::get( 'writing.also_running_limit', 3 );
}

$ttm_thin    = 'thin' === Helpers::preview_state( $attributes );
$ttm_active  = $ttm_thin ? null : Serials::active();
$ttm_chapter = $ttm_active ? Serials::latest_chapter( $ttm_active ) : null;

if ( $ttm_active && ! $ttm_chapter ) {
	$ttm_active = null;
}

/**
 * "Novella · complete · 9 chapters" (a serial row), or "Story" (a standalone story post).
 *
 * @param array<string, mixed> $row Series index row.
 * @return string
 */
$ttm_serial_row_meta = static function ( array $row ): string {
	$stats = Serials::stats( $row );
	$form  = ucfirst( str_replace( '-', ' ', $row['form'] ) );
	$state = str_replace( '-', ' ', $row['status'] );

	return sprintf(
		/* translators: 1: form (e.g. Novella), 2: status (e.g. complete), 3: published chapter count. */
		__( '%1$s · %2$s · %3$d chapters', 'ttm-core' ),
		$form,
		$state,
		$stats['published']
	);
};

$ttm_mode = 'plain';
if ( $ttm_active ) {
	$ttm_mode = 'active';
} elseif ( Serials::has_any_fiction() ) {
	$ttm_mode = 'shelf';
}

$ttm_also_rows = [];
if ( 'active' === $ttm_mode || 'shelf' === $ttm_mode ) {
	foreach ( Serials::completed() as $ttm_row ) {
		if ( $ttm_active && (int) $ttm_row['id'] === (int) $ttm_active['id'] ) {
			continue;
		}
		$ttm_also_rows[] = [
			'title' => $ttm_row['name'],
			'url'   => home_url( '/series/' . $ttm_row['slug'] . '/' ),
			'meta'  => $ttm_serial_row_meta( $ttm_row ),
		];
	}

	foreach ( Serials::stories( $ttm_also_limit ) as $ttm_story_id ) {
		$ttm_also_rows[] = [
			'title' => get_the_title( $ttm_story_id ),
			'url'   => (string) get_permalink( $ttm_story_id ),
			'meta'  => __( 'Story', 'ttm-core' ),
		];
	}

	$ttm_also_rows = array_slice( $ttm_also_rows, 0, $ttm_also_limit );
}//end if
?>
<div <?php echo Helpers::wrapper( 'writing-cell', [ 'is-' . $ttm_mode ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<div class="ttm-cell-heading">
		<h2><?php esc_html_e( 'Writing', 'ttm-core' ); ?></h2>
		<a href="<?php echo esc_url( home_url( '/writing/' ) ); ?>"><?php esc_html_e( 'All serials →', 'ttm-core' ); ?></a>
	</div>

	<?php if ( 'active' === $ttm_mode ) : ?>
		<div class="ttm-writing-cell__featured">
			<p class="is-style-kicker">
				<?php
				printf(
					/* translators: %s: cadence (e.g. "monthly"). */
					esc_html__( 'Serial · new chapter %s', 'ttm-core' ),
					esc_html( Serials::stats( $ttm_active )['cadence'] )
				);
				?>
			</p>
			<h3 class="ttm-writing-cell__headline">
				<?php
				printf(
					/* translators: 1: serial name, 2: chapter number, 3: chapter title. */
					esc_html__( '%1$s — Ch. %2$d: %3$s', 'ttm-core' ),
					esc_html( $ttm_active['name'] ),
					(int) $ttm_chapter['part'],
					esc_html( $ttm_chapter['title'] )
				);
				?>
			</h3>
			<p class="ttm-writing-cell__dek"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $ttm_chapter['post_id'] ) ) ); ?></p>
			<p class="ttm-writing-cell__actions">
				<a class="btn btn-primary" href="<?php echo esc_url( (string) get_permalink( $ttm_chapter['post_id'] ) ); ?>">
					<?php
					printf(
						/* translators: %d: chapter number. */
						esc_html__( 'Read chapter %d', 'ttm-core' ),
						(int) $ttm_chapter['part']
					);
					?>
				</a>
				<a class="btn btn-secondary" href="<?php echo esc_url( Serials::first_chapter_url( $ttm_active ) ); ?>"><?php esc_html_e( 'From chapter 1', 'ttm-core' ); ?></a>
			</p>
		</div>
		<div class="ttm-writing-cell__also">
			<p class="is-style-kicker"><?php esc_html_e( 'Also running', 'ttm-core' ); ?></p>
			<?php foreach ( $ttm_also_rows as $ttm_also ) : ?>
				<a class="ttm-writing-cell__also-row" href="<?php echo esc_url( $ttm_also['url'] ); ?>">
					<span class="ttm-writing-cell__also-title"><?php echo esc_html( $ttm_also['title'] ); ?></span>
					<span class="ttm-writing-cell__also-meta"><?php echo esc_html( $ttm_also['meta'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php elseif ( 'shelf' === $ttm_mode ) : ?>
		<p class="is-style-kicker"><?php esc_html_e( 'From the shelf', 'ttm-core' ); ?></p>
		<?php foreach ( $ttm_also_rows as $ttm_also ) : ?>
			<a class="ttm-writing-cell__also-row" href="<?php echo esc_url( $ttm_also['url'] ); ?>">
				<span class="ttm-writing-cell__also-title"><?php echo esc_html( $ttm_also['title'] ); ?></span>
				<span class="ttm-writing-cell__also-meta"><?php echo esc_html( $ttm_also['meta'] ); ?></span>
			</a>
		<?php endforeach; ?>
		<p class="ttm-writing-cell__footnote"><?php esc_html_e( 'Short fiction and the full index live on the Writing page.', 'ttm-core' ); ?></p>
	<?php else : ?>
		<?php
		$ttm_writing    = get_term_by( 'slug', (string) Config::get( 'sections.writing_slug', 'writing' ), 'category' );
		$ttm_writing_id = $ttm_writing && ! is_wp_error( $ttm_writing ) ? $ttm_writing->term_id : 0;

		$ttm_query = new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'category__in'   => [ $ttm_writing_id ],
				'posts_per_page' => 3,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);
		?>
		<?php foreach ( $ttm_query->posts as $ttm_index => $ttm_post ) : ?>
			<a class="ttm-item<?php echo 0 === $ttm_index ? ' is-featured' : ''; ?>" href="<?php echo esc_url( (string) get_permalink( $ttm_post ) ); ?>">
				<h3><?php echo esc_html( get_the_title( $ttm_post ) ); ?></h3>
				<?php if ( 0 === $ttm_index ) : ?>
				<p class="ttm-item__dek"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $ttm_post ) ) ); ?></p>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
