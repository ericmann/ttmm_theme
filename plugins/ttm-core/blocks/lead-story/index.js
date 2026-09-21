/**
 * Editor registration for ttm/lead-story: server-side rendered, previewState + imageRatio controls.
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
					<PanelBody title={ __( 'Image', 'ttm-core' ) }>
						<SelectControl
							label={ __( 'Image ratio', 'ttm-core' ) }
							value={ attributes.imageRatio }
							options={ [
								{ label: '16:9', value: '16-9' },
								{ label: '4:3', value: '4-3' },
							] }
							onChange={ ( value ) => setAttributes( { imageRatio: value } ) }
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
