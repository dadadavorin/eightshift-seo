/**
 * Eightshift SEO — Block editor sidebar entry point.
 *
 * Registers a PluginDocumentSettingPanel that appears in the Gutenberg
 * Document sidebar for all SEO-enabled post types.
 */

import { registerPlugin } from '@wordpress/plugins';
import { SeoPanelPlugin } from './SeoPanelPlugin';
import './editor.css';

// Register Phase 8 authoring blocks alongside the sidebar plugin so they
// share the editor bundle.
import '../../blocks/statistic';
import '../../blocks/expert-quote';

registerPlugin('eightshift-seo', {
	render: SeoPanelPlugin,
	icon: null,
});
