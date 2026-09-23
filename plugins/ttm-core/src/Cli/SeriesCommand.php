<?php
/**
 * `wp ttm series:assign <series-slug> --from-tags=a,b [--from-tag=a] [--form=] [--status=]
 * [--total=] [--name=] [--dry-run]` and `wp ttm series:rebuild`.
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
	 * `series:assign <series-slug> --from-tags=a,b [--from-tag=a] [--form=] [--status=]
	 * [--total=] [--name=] [--dry-run]`.
	 *
	 * {@inheritDoc}
	 *
	 * @param string[]             $args  [0] is the series slug.
	 * @param array<string, mixed> $assoc --from-tags=a,b, --from-tag=a, --form=, --status=,
	 *                                    --total=, --name=, --dry-run.
	 */
	public function run( array $args, array $assoc ): array {
		$slug = $args[0] ?? '';

		// --from-tags is the union form (SPEC §6.7); --from-tag stays for a single tag.
		$tags = [];
		if ( ! empty( $assoc['from-tags'] ) ) {
			$tags = array_filter( array_map( 'trim', explode( ',', (string) $assoc['from-tags'] ) ) );
		} elseif ( ! empty( $assoc['from-tag'] ) ) {
			$tags = [ (string) $assoc['from-tag'] ];
		}

		if ( '' === $slug || empty( $tags ) ) {
			return [
				'ok'       => false,
				'rows'     => [],
				'messages' => [ 'Usage: series:assign <series-slug> --from-tags=a,b (or --from-tag=a)' ],
			];
		}

		$dry_run = ! empty( $assoc['dry-run'] );
		$form    = $assoc['form'] ?? null;
		$status  = isset( $assoc['status'] ) ? (string) $assoc['status'] : null;
		$total   = isset( $assoc['total'] ) ? (int) $assoc['total'] : null;
		$name    = isset( $assoc['name'] ) ? (string) $assoc['name'] : null;

		$tag_term_ids = [];
		foreach ( $tags as $tag ) {
			$tag_term = get_term_by( 'slug', $tag, 'post_tag' );
			if ( ! $tag_term ) {
				return [
					'ok'       => false,
					'rows'     => [],
					'messages' => [ "Tag '{$tag}' does not exist." ],
				];
			}
			$tag_term_ids[] = (int) $tag_term->term_id;
		}

		$candidates = [];
		foreach ( $tag_term_ids as $tag_term_id ) {
			$candidates = array_merge( $candidates, $this->tagged_posts( $tag_term_id ) );
		}
		$candidates = array_unique( $candidates );

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
			// SPEC §6.7: --name sets the term name on creation only.
			$term_name = $name ?? ucwords( str_replace( '-', ' ', $slug ) );
			$created   = wp_insert_term( $term_name, 'series', [ 'slug' => $slug ] );
			$term_id   = (int) $created['term_id'];
		} else {
			$term_id = (int) $term->term_id;
		}

		if ( $form ) {
			update_term_meta( $term_id, 'ttm_form', $form );
		}

		if ( null !== $total ) {
			update_term_meta( $term_id, 'ttm_total_parts', $total );
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

		if ( null !== $status ) {
			// SPEC §6.7: an explicit --status overrides the inferred one below.
			update_term_meta( $term_id, 'ttm_status', $status );
		} elseif ( ! empty( $rows ) ) {
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
