/**
 * Editor registration for ttm/newsletter-form: server-side rendered, previewState + placement controls.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import PreviewStateControl from '../../src/editor/PreviewStateControl';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Placement', 'ttm-core' ) }>
						<SelectControl
							label={ __( 'Placement', 'ttm-core' ) }
							value={ attributes.placement }
							options={ [
								{ label: __( 'Poster', 'ttm-core' ), value: 'poster' },
								{ label: __( 'Box', 'ttm-core' ), value: 'box' },
							] }
							onChange={ ( value ) => setAttributes( { placement: value } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<PreviewStateControl
					previewState={ attributes.previewState }
					onChange={ ( value ) => setAttributes( { previewState: value } ) }
				/>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save() {
		return null;
	},
} );
