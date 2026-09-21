/**
 * Shared InspectorControls SelectControl for a block's `previewState` attribute.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

/**
 * @param {Object}   props              Props.
 * @param {string}   props.previewState Current value.
 * @param {Function} props.onChange     Called with the new value.
 * @return {Element} The controls.
 */
export default function PreviewStateControl( { previewState, onChange } ) {
	return (
		<InspectorControls>
			<PanelBody title={ __( 'Preview', 'ttm-core' ) }>
				<SelectControl
					label={ __( 'Preview state', 'ttm-core' ) }
					value={ previewState || 'normal' }
					options={ [
						{ label: __( 'Normal', 'ttm-core' ), value: 'normal' },
						{ label: __( 'Empty', 'ttm-core' ), value: 'empty' },
						{ label: __( 'Thin', 'ttm-core' ), value: 'thin' },
					] }
					onChange={ onChange }
				/>
			</PanelBody>
		</InspectorControls>
	);
}
