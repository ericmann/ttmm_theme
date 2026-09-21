import { __, sprintf } from '@wordpress/i18n';

/**
 * Pure pre-publish checks (05 §8). Never blocks publish; only warns.
 *
 * @param {Object}   state                    Current editor state.
 * @param {boolean}  state.isJournal          Whether the primary section is Journal.
 * @param {string}   state.excerpt            The post's excerpt/dek.
 * @param {boolean}  state.featuredAltMissing Whether the featured image is missing alt text.
 * @param {number}   state.seriesId           The selected series term id, or 0.
 * @param {number}   state.part               The entered part number, or 0.
 * @param {number[]} state.takenParts         Part numbers already used by other posts in the series.
 * @param {boolean}  state.inWriting          Whether the primary section is Writing.
 * @param {string}   state.form               The post's ttm_form.
 * @return {Array<{id: string, message: string}>} Warnings.
 */
export function runChecks( state ) {
	const {
		isJournal = false,
		excerpt = '',
		featuredAltMissing = false,
		seriesId = 0,
		part = 0,
		takenParts = [],
		inWriting = false,
		form = '',
		featuredInSection = false,
		mostReadCount = 0,
		mostReadLimit = Infinity,
	} = state;

	const warnings = [];

	if ( ! isJournal && ! excerpt ) {
		warnings.push( {
			id: 'missing-dek',
			message: __( 'This post has no dek.', 'ttm-core' ),
		} );
	}

	if ( featuredAltMissing ) {
		warnings.push( {
			id: 'missing-alt',
			message: __(
				'The featured image is missing alt text.',
				'ttm-core'
			),
		} );
	}

	if ( seriesId && ! part ) {
		warnings.push( {
			id: 'missing-part',
			message: __(
				'This post is in a series but has no part number.',
				'ttm-core'
			),
		} );
	}

	if ( seriesId && part && takenParts.includes( part ) ) {
		warnings.push( {
			id: 'duplicate-part',
			message: sprintf(
				/* translators: %d: part number */
				__(
					'Part %d is already used by another post in this series.',
					'ttm-core'
				),
				part
			),
		} );
	}

	if ( inWriting && ! seriesId && ! form ) {
		warnings.push( {
			id: 'missing-form',
			message: __( 'This Writing post has no form set.', 'ttm-core' ),
		} );
	}

	if ( featuredInSection && mostReadCount >= mostReadLimit ) {
		warnings.push( {
			id: 'most-read-limit',
			message: __(
				'This section already has its maximum number of "most read" posts.',
				'ttm-core'
			),
		} );
	}

	return warnings;
}
