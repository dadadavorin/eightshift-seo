/**
 * Eightshift SEO — Block editor sidebar entry point.
 *
 * Registers a PluginDocumentSettingPanel that appears in the Gutenberg
 * Document sidebar for all SEO-enabled post types.
 */

import { registerPlugin } from '@wordpress/plugins';
import { SeoPanelPlugin } from './SeoPanelPlugin';

registerPlugin('eightshift-seo', {
	render: SeoPanelPlugin,
	icon: null,
});
