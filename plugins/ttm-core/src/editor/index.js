/**
 * Registers the "These Things Matter" document sidebar panel.
 */
import { registerPlugin } from '@wordpress/plugins';
import TtmPanel from './panel';

registerPlugin( 'ttm-core', { render: TtmPanel } );
