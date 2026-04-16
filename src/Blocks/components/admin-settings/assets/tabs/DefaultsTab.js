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

	const tokens = ['%title%', '%sitename%', '%sep%', '%excerpt%', '%author%', '%date%'];

	return (
		<div className="es-seo-tab">
			<h2>{__('Default templates', 'eightshift-seo')}</h2>

			<div>
				<p className="description" style={{ margin: '0 0 8px' }}>
					{__('Available tokens:', 'eightshift-seo')}
				</p>
				<div className="es-seo-token-list">
					{tokens.map((token) => (
						<code key={token} className="es-seo-token">{token}</code>
					))}
				</div>
			</div>

			{(postTypes ?? []).map((pt) => (
				<div key={pt.slug} className="es-seo-post-type-group">
					<h3>{pt.name}</h3>

					<TextControl
						label={__('Title template', 'eightshift-seo')}
						value={titleTemplates[pt.slug] ?? '%title% %sep% %sitename%'}
						onChange={(val) => setTitle(pt.slug, val)}
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={__('Description template', 'eightshift-seo')}
						value={descTemplates[pt.slug] ?? '%excerpt%'}
						onChange={(val) => setDesc(pt.slug, val)}
						__nextHasNoMarginBottom
					/>
				</div>
			))}
		</div>
	);
};
