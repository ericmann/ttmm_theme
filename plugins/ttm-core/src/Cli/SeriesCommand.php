<?php
/**
 * `wp ttm series:assign <series-slug> --from-tag=<tag> [--form=<form>] [--dry-run]` and
 * `wp ttm series:rebuild`.
 *
 * @package TTM\Core\Cli
 */

declare( strict_types=1 );

namespace TTM\Core\Cli;

use TTM\Core\Config;
use TTM\Core\Meta\Form;
use TTM\Core\Query\SeriesIndex;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Dates;

/**
 * Series:assign core (run()) and series:rebuild core (rebuild()).
 */
class SeriesCommand extends Command {

	/**
	 * `series:assign <series-slug> --from-tag=<tag> [--form=<form>] [--dry-run]`.
	 *
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  [0] is the series slug.
	 * @param array<string, mixed> $assoc --from-tag=<tag>, --form=<form>, --dry-run.
	 */
	public function run( array $args, array $assoc ): array {
		$slug = $args[0] ?? '';
		$tag  = $assoc['from-tag'] ?? '';

		if ( '' === $slug || '' === $tag ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: series:assign <series-slug> --from-tag=<tag>' ],
			];
		}

		$dry_run = ! empty( $assoc['dry-run'] );
		$form    = $assoc['form'] ?? null;

		$tag_term = get_term_by( 'slug', $tag, 'post_tag' );
		if ( ! $tag_term ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ "Tag '{$tag}' does not exist." ],
			];
		}

		$candidates = $this->tagged_posts( (int) $tag_term->term_id );

		$rows    = [];
		$skipped = [];
		foreach ( $candidates as $post_id ) {
			if ( ! empty( wp_get_post_terms( $post_id, 'series' ) ) ) {
				$skipped[] = $post_id;
				continue;
			}
			$rows[] = $post_id;
		}

		usort( $rows, static fn ( int $a, int $b ): int => strcmp( get_post( $a )->post_date, get_post( $b )->post_date ) );

		$messages = [];

		if ( $dry_run ) {
			$messages[] = sprintf( 'Would assign %d post(s) to series "%s" (%d already in a series, skipped).', count( $rows ), $slug, count( $skipped ) );

			return [
				'ok'       => true,
				'rows'     => array_map( static fn ( int $id ): array => [ 'post_id' => $id ], $rows ),
				'messages' => $messages,
			];
		}

		$term = get_term_by( 'slug', $slug, 'series' );
		if ( ! $term ) {
			$created = wp_insert_term( ucwords( str_replace( '-', ' ', $slug ) ), 'series', [ 'slug' => $slug ] );
			$term_id = (int) $created['term_id'];
		} else {
			$term_id = (int) $term->term_id;
		}

		if ( $form ) {
			update_term_meta( $term_id, 'ttm_form', $form );
		}

		$part = 1;
		$out  = [];
		foreach ( $rows as $post_id ) {
			wp_set_object_terms( $post_id, [ $term_id ], 'series' );
			update_post_meta( $post_id, 'ttm_series_part', $part );

			// The series' `ttm_form` is set above (before this loop) precisely so that
			// Meta\Form::on_save() -- which reads the post's *current* series term meta --
			// derives 'chapter' for a fiction series right away, instead of waiting for the
			// next unrelated save_post_post fire.
			$post = get_post( $post_id );
			if ( $post ) {
				Form::on_save( $post_id, $post );
			}

			$out[] = [
				'post_id' => $post_id,
				'part'    => $part,
			];
			++$part;
		}

		if ( ! empty( $rows ) ) {
			$newest = get_post( end( $rows ) );
			$days   = Dates::days_between( Clock::at( $newest->post_date ), Clock::now() );
			$stale  = (int) Config::get( 'lead.stale_days', 30 );
			update_term_meta( $term_id, 'ttm_status', $days > $stale ? 'complete' : 'in-progress' );
		}

		SeriesIndex::rebuild();

		$messages[] = sprintf( 'Assigned %d post(s) to series "%s" (%d already in a series, skipped).', count( $rows ), $slug, count( $skipped ) );

		return [
			'ok'       => true,
			'rows'     => $out,
			'messages' => $messages,
		];
	}

	/**
	 * `series:rebuild`: force an immediate SeriesIndex rebuild.
	 *
	 * @return array{ok: bool, rows: array<int, array<string, mixed>>, messages: string[]}
	 */
	public function rebuild(): array {
		$rows = SeriesIndex::rebuild();

		return [
			'ok'       => true,
			'rows'     => $rows,
			'messages' => [ sprintf( 'Rebuilt the series index (%d series).', count( $rows ) ) ],
		];
	}

	/**
	 * Post ids tagged with a given term, batched.
	 *
	 * @param int $tag_term_id Tag term id.
	 * @return int[]
	 */
	private function tagged_posts( int $tag_term_id ): array {
		$batch = (int) Config::get( 'cli.batch', 200 );
		$ids   = [];
		$paged = 1;

		do {
			$query = new \WP_Query(
				[
					'post_type'      => 'post',
					'post_status'    => 'any',
					'posts_per_page' => $batch,
					'paged'          => $paged,
					'fields'         => 'ids',
					'tax_query'      => [
						[
							'taxonomy' => 'post_tag',
							'terms'    => [ $tag_term_id ],
						],
					],
				]
			);
			$ids   = array_merge( $ids, $query->posts );
			$found = count( $query->posts );
			++$paged;
		} while ( $found === $batch );

		return $ids;
	}
}
