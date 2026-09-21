/**
 * Pure form derivation, mirroring TTM\Core\Meta\Form::derive() (SPEC §5.2).
 *
 * @param {Object}      args            Arguments.
 * @param {string|null} args.seriesForm The post's series term's ttm_form, or null when no series.
 * @param {boolean}     args.inWriting  Whether the post is in the Writing section.
 * @return {string} "chapter" | "story" | "article".
 */
export function deriveForm( { seriesForm, inWriting } ) {
	if (
		seriesForm !== null &&
		seriesForm !== undefined &&
		seriesForm !== 'nonfiction'
	) {
		return 'chapter';
	}

	if ( ( seriesForm === null || seriesForm === undefined ) && inWriting ) {
		return 'story';
	}

	return 'article';
}
