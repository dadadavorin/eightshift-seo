/**
 * Social tab — breadcrumb schema settings.
 */

import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl } from '@wordpress/components';

export const SocialTab = ({ settings, onChange }) => {
	const breadcrumbs = settings.breadcrumbs ?? {};

	const setBreadcrumbs = (key, val) =>
		onChange({ ...settings, breadcrumbs: { ...breadcrumbs, [key]: val } });

	return (
		<div className="es-seo-tab">
			<h2>{__('Social & Schema', 'eightshift-seo')}</h2>

			<h3>{__('BreadcrumbList schema', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Outputs BreadcrumbList JSON-LD in the page head for rich results.', 'eightshift-seo')}
			</p>

			<CheckboxControl
				label={__('Enable BreadcrumbList structured data', 'eightshift-seo')}
				checked={breadcrumbs.enableSchema ?? true}
				onChange={(val) => setBreadcrumbs('enableSchema', val)}
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={__('Home crumb label', 'eightshift-seo')}
				help={__('Label used for the first (home) item in the breadcrumb schema.', 'eightshift-seo')}
				value={breadcrumbs.homeLabel ?? 'Home'}
				onChange={(val) => setBreadcrumbs('homeLabel', val)}
			/>
		</div>
	);
};
