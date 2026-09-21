/**
 * Editor registration for ttm/story-tiles: server-side rendered, previewState control.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import PreviewStateControl from '../../src/editor/PreviewStateControl';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
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
