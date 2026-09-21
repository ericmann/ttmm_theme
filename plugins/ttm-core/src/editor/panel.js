/**
 * "These Things Matter" document sidebar panel (05 §8).
 */
import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	SelectControl,
	ComboboxControl,
	TextControl,
	ToggleControl,
	Button,
} from '@wordpress/components';
import {
	useEntityProp,
	useEntityRecords,
	useEntityId,
} from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { deriveForm } from './derive';

const editorData = window.ttmEditorData || {
	sections: [],
	journalId: 0,
	writingId: 0,
	seriesForms: {},
};

/**
 * The panel itself.
 */
export default function TtmPanel() {
	const [ meta, setMeta ] = useEntityProp( 'postType', 'post', 'meta' );
	const [ categories ] = useEntityProp( 'postType', 'post', 'categories' );
	const postId = useEntityId( 'postType', 'post' );

	const { records: seriesTerms } = useEntityRecords( 'taxonomy', 'series', {
		per_page: 100,
	} );
	const { editEntityRecord, saveEntityRecord } = useDispatch( 'core' );

	const postSeriesIds = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecord( 'postType', 'post', postId )
				?.series || [],
		[ postId ]
	);

	const [ newSeriesName, setNewSeriesName ] = useState( '' );

	const primarySection = meta.ttm_primary_category || categories?.[ 0 ] || 0;
	const inWriting = primarySection === editorData.writingId;
	const inJournal = primarySection === editorData.journalId;

	const selectedSeriesId = postSeriesIds?.[ 0 ] || 0;
	const selectedSeries = ( seriesTerms || [] ).find(
		( t ) => t.id === selectedSeriesId
	);
	const seriesForm = selectedSeries
		? selectedSeries.meta?.ttm_form || 'nonfiction'
		: null;
	const totalParts = selectedSeries
		? selectedSeries.meta?.ttm_total_parts || 0
		: 0;

	const updateMeta = ( patch ) => setMeta( { ...meta, ...patch } );

	const setSeries = ( id ) => {
		editEntityRecord( 'postType', 'post', postId, {
			series: id ? [ id ] : [],
		} );
	};

	const createSeries = async () => {
		if ( ! newSeriesName ) {
			return;
		}
		const created = await saveEntityRecord( 'taxonomy', 'series', {
			name: newSeriesName,
		} );
		if ( created ) {
			setSeries( created.id );
			setNewSeriesName( '' );
		}
	};

	const resetForm = () => {
		updateMeta( {
			ttm_form_locked: false,
			ttm_form: deriveForm( { seriesForm, inWriting } ),
		} );
	};

	return (
		<PluginDocumentSettingPanel
			name="ttm-panel"
			title={ __( 'These Things Matter', 'ttm-core' ) }
		>
			<SelectControl
				label={ __( 'Primary section', 'ttm-core' ) }
				value={ primarySection }
				options={ editorData.sections.map( ( s ) => ( {
					label: s.name,
					value: s.id,
				} ) ) }
				onChange={ ( value ) =>
					updateMeta( { ttm_primary_category: Number( value ) } )
				}
			/>

			<ComboboxControl
				label={ __( 'Series', 'ttm-core' ) }
				value={ selectedSeriesId || undefined }
				options={ ( seriesTerms || [] ).map( ( t ) => ( {
					label: t.name,
					value: t.id,
				} ) ) }
				onChange={ ( value ) =>
					setSeries( value ? Number( value ) : 0 )
				}
			/>
			<TextControl
				label={ __( 'Create new series', 'ttm-core' ) }
				value={ newSeriesName }
				onChange={ setNewSeriesName }
			/>
			<Button variant="secondary" onClick={ createSeries }>
				{ __( 'Create', 'ttm-core' ) }
			</Button>

			{ !! selectedSeriesId && (
				<>
					<TextControl
						type="number"
						min={ 1 }
						label={
							totalParts > 0
								? `${ __( 'Part number', 'ttm-core' ) } (${ __( 'of', 'ttm-core' ) } ${ totalParts })`
								: `${ __( 'Part number', 'ttm-core' ) } (${ __( 'open-ended', 'ttm-core' ) })`
						}
						value={ meta.ttm_series_part || '' }
						onChange={ ( value ) =>
							updateMeta( {
								ttm_series_part: Number( value ) || 0,
							} )
						}
					/>
					<TextControl
						label={ __( 'Part title', 'ttm-core' ) }
						value={ meta.ttm_part_title || '' }
						onChange={ ( value ) =>
							updateMeta( { ttm_part_title: value } )
						}
					/>
				</>
			) }

			<SelectControl
				label={ __( 'Form', 'ttm-core' ) }
				value={ meta.ttm_form || 'article' }
				options={ [
					{ label: __( 'Article', 'ttm-core' ), value: 'article' },
					{ label: __( 'Chapter', 'ttm-core' ), value: 'chapter' },
					{ label: __( 'Story', 'ttm-core' ), value: 'story' },
				] }
				onChange={ ( value ) =>
					updateMeta( { ttm_form: value, ttm_form_locked: true } )
				}
			/>
			{ !! meta.ttm_form_locked && (
				<Button variant="link" onClick={ resetForm }>
					{ __( 'Reset to automatic', 'ttm-core' ) }
				</Button>
			) }

			<TextControl
				type="url"
				label={ __( 'X (Twitter) syndication URL', 'ttm-core' ) }
				value={ meta.ttm_syndication?.x || '' }
				onChange={ ( value ) =>
					updateMeta( {
						ttm_syndication: { ...meta.ttm_syndication, x: value },
					} )
				}
			/>
			<TextControl
				type="url"
				label={ __( 'Mastodon syndication URL', 'ttm-core' ) }
				value={ meta.ttm_syndication?.mastodon || '' }
				onChange={ ( value ) =>
					updateMeta( {
						ttm_syndication: {
							...meta.ttm_syndication,
							mastodon: value,
						},
					} )
				}
			/>
			<TextControl
				type="url"
				label={ __( 'Bluesky syndication URL', 'ttm-core' ) }
				value={ meta.ttm_syndication?.bluesky || '' }
				onChange={ ( value ) =>
					updateMeta( {
						ttm_syndication: {
							...meta.ttm_syndication,
							bluesky: value,
						},
					} )
				}
			/>

			{ inJournal && (
				<TextControl
					label={ __( 'Location', 'ttm-core' ) }
					value={ meta.ttm_location || '' }
					onChange={ ( value ) =>
						updateMeta( { ttm_location: value } )
					}
				/>
			) }

			<ToggleControl
				label={ __( 'Most read', 'ttm-core' ) }
				checked={ !! meta.ttm_featured_in_section }
				onChange={ ( value ) =>
					updateMeta( { ttm_featured_in_section: value } )
				}
			/>
		</PluginDocumentSettingPanel>
	);
}
