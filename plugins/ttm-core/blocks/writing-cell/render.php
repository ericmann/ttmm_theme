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
use TTM\Core\Query\Lead;

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
 * "Novella · complete · 9 chapters" (a serial row); standalone story rows build their own
 * "Short story · {N} words" meta separately, below.
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

// F1's shelf list is capped by writing.shelf_limit, not writing.also_running_limit (which
// caps the "Also running" list next to an *active* serial -- the two are visually and
// semantically different lists that happened to share one variable before this fix).
$ttm_rows_limit = $ttm_also_limit;
if ( 'shelf' === $ttm_mode ) {
	$ttm_rows_limit = (int) Config::get( 'writing.shelf_limit', 4 );
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

	foreach ( Serials::stories( $ttm_rows_limit ) as $ttm_story_id ) {
		$ttm_also_rows[] = [
			'title' => get_the_title( $ttm_story_id ),
			'url'   => (string) get_permalink( $ttm_story_id ),
			'meta'  => sprintf(
				/* translators: %s: word count (e.g. "3,100"). */
				__( 'Short story · %s words', 'ttm-core' ),
				number_format_i18n( (int) get_post_meta( $ttm_story_id, 'ttm_word_count', true ) )
			),
		];
	}

	$ttm_also_rows = array_slice( $ttm_also_rows, 0, $ttm_rows_limit );
}//end if

$ttm_writing_term = get_term_by( 'slug', (string) Config::get( 'sections.writing_slug', 'writing' ), 'category' );
$ttm_writing_id   = $ttm_writing_term && ! is_wp_error( $ttm_writing_term ) ? $ttm_writing_term->term_id : 0;

if ( 'active' === $ttm_mode ) {
	$ttm_part_title = get_post_meta( $ttm_chapter['post_id'], 'ttm_part_title', true );
	$ttm_headline   = '' !== $ttm_part_title
		? sprintf(
			/* translators: 1: serial name, 2: chapter number, 3: chapter title. */
			__( '%1$s — Ch. %2$d: %3$s', 'ttm-core' ),
			$ttm_active['name'],
			(int) $ttm_chapter['part'],
			$ttm_part_title
		)
		: sprintf(
			/* translators: 1: serial name, 2: chapter number. */
			__( '%1$s — Ch. %2$d', 'ttm-core' ),
			$ttm_active['name'],
			(int) $ttm_chapter['part']
		);
	$ttm_dek = wp_strip_all_tags( get_the_excerpt( $ttm_chapter['post_id'] ) );
}
?>
<div <?php echo Helpers::wrapper( 'writing-cell', [ 'is-' . $ttm_mode ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<div class="ttm-cell-heading">
		<h2 class="ttm-cell-heading__label"><?php esc_html_e( 'Writing', 'ttm-core' ); ?></h2>
		<?php if ( 'plain' !== $ttm_mode ) : ?>
		<a class="ttm-cell-heading__link" href="<?php echo esc_url( home_url( '/writing/' ) ); ?>"><?php esc_html_e( 'All serials & stories →', 'ttm-core' ); ?></a>
		<?php else : ?>
		<a class="ttm-cell-heading__link" href="<?php echo esc_url( (string) get_category_link( $ttm_writing_id ) ); ?>">
			<?php
			printf(
				/* translators: %d: post count. */
				esc_html__( '%d →', 'ttm-core' ),
				$ttm_writing_term && ! is_wp_error( $ttm_writing_term ) ? (int) $ttm_writing_term->count : 0
			);
			?>
		</a>
		<?php endif; ?>
	</div>

	<?php if ( 'active' === $ttm_mode ) : ?>
	<div class="ttm-writing-cell__body">
		<div class="ttm-writing-cell__featured">
			<p class="ttm-writing-cell__kicker">
				<?php
				$ttm_cadence = Serials::stats( $ttm_active )['cadence'];
				echo '' !== $ttm_cadence
					? esc_html(
						sprintf(
							/* translators: %s: cadence (e.g. "monthly"). */
							__( 'Serial · new chapter %s', 'ttm-core' ),
							$ttm_cadence
						)
					)
					: esc_html__( 'Serial', 'ttm-core' );
				?>
			</p>
			<h3 class="ttm-writing-cell__headline"><?php echo esc_html( $ttm_headline ); ?></h3>
			<?php if ( '' !== $ttm_dek ) : ?>
			<p class="ttm-writing-cell__dek"><?php echo esc_html( $ttm_dek ); ?></p>
			<?php endif; ?>
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
			<p class="ttm-writing-cell__also-label"><?php esc_html_e( 'Also running', 'ttm-core' ); ?></p>
			<?php foreach ( $ttm_also_rows as $ttm_also ) : ?>
				<a class="ttm-writing-cell__also-row" href="<?php echo esc_url( $ttm_also['url'] ); ?>">
					<span class="ttm-writing-cell__also-title"><?php echo esc_html( $ttm_also['title'] ); ?></span>
					<span class="ttm-writing-cell__also-meta"><?php echo esc_html( $ttm_also['meta'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php elseif ( 'shelf' === $ttm_mode ) : ?>
	<div class="ttm-writing-cell__body">
		<p class="is-style-kicker"><?php esc_html_e( 'From the shelf', 'ttm-core' ); ?></p>
		<?php foreach ( $ttm_also_rows as $ttm_also ) : ?>
			<a class="ttm-writing-cell__also-row" href="<?php echo esc_url( $ttm_also['url'] ); ?>">
				<span class="ttm-writing-cell__also-title"><?php echo esc_html( $ttm_also['title'] ); ?></span>
				<span class="ttm-writing-cell__also-meta"><?php echo esc_html( $ttm_also['meta'] ); ?></span>
			</a>
		<?php endforeach; ?>
		<p class="ttm-writing-cell__footnote"><?php esc_html_e( 'Short fiction and the full index live on the Writing page.', 'ttm-core' ); ?></p>
	</div>
	<?php else : ?>
		<?php
		$ttm_lead_id = Lead::id();

		// F2: an ordinary section cell -- same ttm_primary_category-only and lead-exclusion
		// rules as every other front-page cell (Query\Cells::filter_query_vars()), just built
		// directly since this branch isn't a `core/query` Query Loop block.
		$ttm_query = new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'category__in'   => [ $ttm_writing_id ],
				'posts_per_page' => (int) Config::get( 'writing.plain_count', 3 ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- ttm_primary_category is a single, indexed meta key; bounded by posts_per_page above.
					[
						'key'   => 'ttm_primary_category',
						'value' => $ttm_writing_id,
					],
				],
				'post__not_in'   => $ttm_lead_id ? [ $ttm_lead_id ] : [],
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
