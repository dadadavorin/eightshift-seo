/**
 * Eightshift SEO — Gutenberg sidebar panel plugin.
 *
 * Uses useEntityProp to read/write postmeta — no Redux slice, no custom store.
 * All writes go through the standard WP REST API (POST /wp/v2/posts/{id}).
 */

import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';
import { SearchAppearancePanel } from './panels/SearchAppearancePanel';
import { SocialSharingPanel } from './panels/SocialSharingPanel';
import { AdvancedPanel } from './panels/AdvancedPanel';
import { GeoPanel } from './panels/GeoPanel';
import { PrePublishPanel } from './panels/PrePublishPanel';

const { metaKeys } = window.esSeoEditorLocalization ?? {};

export const SeoPanelPlugin = () => {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

	const getMeta = (key) => meta?.[metaKeys?.[key]] ?? '';
	const setMetaKey = (key, value) =>
		setMeta({ ...meta, [metaKeys?.[key]]: value });

	return (
		<>
			<PluginDocumentSettingPanel
				name="eightshift-seo-panel"
				title={__('SEO', 'eightshift-seo')}
				className="es-seo-panel"
			>
				<TabPanel
					tabs={[
						{
							name: 'search',
							title: __('Search', 'eightshift-seo'),
						},
						{
							name: 'social',
							title: __('Social', 'eightshift-seo'),
						},
						{
							name: 'advanced',
							title: __('Advanced', 'eightshift-seo'),
						},
						{
							name: 'geo',
							title: __('GEO', 'eightshift-seo'),
						},
					]}
				>
					{(tab) => {
						if (tab.name === 'search') {
							return (
								<SearchAppearancePanel
									getMeta={getMeta}
									setMetaKey={setMetaKey}
								/>
							);
						}
						if (tab.name === 'social') {
							return (
								<SocialSharingPanel
									getMeta={getMeta}
									setMetaKey={setMetaKey}
								/>
							);
						}
						if (tab.name === 'geo') {
							return (
								<GeoPanel
									getMeta={getMeta}
									setMetaKey={setMetaKey}
								/>
							);
						}
						return (
							<AdvancedPanel
								getMeta={getMeta}
								setMetaKey={setMetaKey}
							/>
						);
					}}
				</TabPanel>
			</PluginDocumentSettingPanel>

			<PrePublishPanel />
		</>
	);
};
