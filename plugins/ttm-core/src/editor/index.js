/**
 * Registers the "These Things Matter" document sidebar panel and pre-publish checks.
 */
import { registerPlugin } from '@wordpress/plugins';
import TtmPanel from './panel';
import TtmPrePublishChecks from './prepublish';

function TtmEditor() {
	return (
		<>
			<TtmPanel />
			<TtmPrePublishChecks />
		</>
	);
}

registerPlugin( 'ttm-core', { render: TtmEditor } );
