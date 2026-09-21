/**
 * Editor registration for ttm/archive-by-year: an InnerBlocks container for one core/query.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import PreviewStateControl from '../../src/editor/PreviewStateControl';

const TEMPLATE = [ [ 'core/query', {} ] ];

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<PreviewStateControl
					previewState={ attributes.previewState }
					onChange={ ( value ) => setAttributes( { previewState: value } ) }
				/>
				<InnerBlocks template={ TEMPLATE } allowedBlocks={ [ 'core/query' ] } />
			</div>
		);
	},
	save() {
		return <InnerBlocks.Content />;
	},
} );
