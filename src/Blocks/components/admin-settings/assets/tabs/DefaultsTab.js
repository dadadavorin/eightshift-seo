/**
 * Defaults tab — per-post-type title and description templates.
 */

import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

const { postTypes } = window.esSeoLocalization ?? {};

export const DefaultsTab = ({ settings, onChange }) => {
	const titleTemplates = settings.titleTemplates ?? {};
	const descTemplates  = settings.descriptionTemplates ?? {};

	const setTitle = (slug, val) =>
		onChange({ ...settings, titleTemplates: { ...titleTemplates, [slug]: val } });

	const setDesc = (slug, val) =>
		onChange({ ...settings, descriptionTemplates: { ...descTemplates, [slug]: val } });

	return (
		<div className="es-seo-tab">
			<h2>{__('Default templates', 'eightshift-seo')}</h2>
			<p className="description">
				{__('Available tokens: %title% %sitename% %sep% %excerpt% %author% %date%', 'eightshift-seo')}
			</p>

			{(postTypes ?? []).map((pt) => (
				<div key={pt.slug} className="es-seo-post-type-group">
					<h3>{pt.name}</h3>

					<TextControl
						label={__('Title template', 'eightshift-seo')}
						value={titleTemplates[pt.slug] ?? '%title% %sep% %sitename%'}
						onChange={(val) => setTitle(pt.slug, val)}
					/>

					<TextControl
						label={__('Description template', 'eightshift-seo')}
						value={descTemplates[pt.slug] ?? '%excerpt%'}
						onChange={(val) => setDesc(pt.slug, val)}
					/>
				</div>
			))}
		</div>
	);
};
