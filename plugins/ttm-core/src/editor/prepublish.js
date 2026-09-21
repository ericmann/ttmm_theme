/**
 * Pre-publish warnings (05 §8). Never blocks publish.
 */
import { __ } from '@wordpress/i18n';
import { PluginPrePublishPanel } from '@wordpress/editor';
import { Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { runChecks } from './checks';

const editorData = window.ttmEditorData || {
	journalId: 0,
	writingId: 0,
	mostReadCounts: {},
	mostReadLimit: Infinity,
};

/**
 * The pre-publish panel itself.
 */
export default function TtmPrePublishChecks() {
	const state = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const meta = editor.getEditedPostAttribute( 'meta' ) || {};
		const categories = editor.getEditedPostAttribute( 'categories' ) || [];
		const seriesIds = editor.getEditedPostAttribute( 'series' ) || [];
		const media = editor.getEditedPostAttribute( 'featured_media' );
		const attachment = media ? select( 'core' ).getMedia( media ) : null;

		const primaryId = meta.ttm_primary_category || categories[ 0 ] || 0;
		const seriesId = seriesIds[ 0 ] || 0;

		return {
			isJournal: primaryId === editorData.journalId,
			excerpt: editor.getEditedPostAttribute( 'excerpt' ) || '',
			featuredAltMissing: !! media && ! attachment?.alt_text,
			seriesId,
			part: meta.ttm_series_part || 0,
			takenParts:
				( editorData.seriesParts?.[ seriesId ] &&
					Object.keys( editorData.seriesParts[ seriesId ] )
						.map( Number )
						.filter(
							( p ) =>
								editorData.seriesParts[ seriesId ][ p ] !==
								editor.getCurrentPostId()
						) ) ||
				[],
			inWriting: primaryId === editorData.writingId,
			form: meta.ttm_form || '',
			featuredInSection: !! meta.ttm_featured_in_section,
			mostReadCount: editorData.mostReadCounts?.[ primaryId ] || 0,
			mostReadLimit: editorData.mostReadLimit ?? Infinity,
		};
	}, [] );

	const warnings = runChecks( state );

	if ( ! warnings.length ) {
		return null;
	}

	return (
		<PluginPrePublishPanel
			title={ __( 'These Things Matter checks', 'ttm-core' ) }
			initialOpen
		>
			{ warnings.map( ( warning ) => (
				<Notice
					key={ warning.id }
					status="warning"
					isDismissible={ false }
				>
					{ warning.message }
				</Notice>
			) ) }
		</PluginPrePublishPanel>
	);
}
