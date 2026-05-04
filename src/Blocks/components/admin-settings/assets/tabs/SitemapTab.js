/**
 * Sitemap tab — excluded post types, excluded taxonomies, robots.txt toggle.
 */

import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

const { postTypes, taxonomies } = window.esSeoLocalization ?? {};

export const SitemapTab = ({ settings, onChange }) => {
	const sitemap = settings.sitemap ?? {};

	const setSitemap = (key, val) =>
		onChange({ ...settings, sitemap: { ...sitemap, [key]: val } });

	const toggleArray = (arr, value) => {
		const current = arr ?? [];
		return current.includes(value)
			? current.filter((v) => v !== value)
			: [...current, value];
	};

	// Detect duplicate display names to append the slug for disambiguation.
	const ptNameCounts  = (postTypes  ?? []).reduce((acc, p) => { acc[p.name] = (acc[p.name] ?? 0) + 1; return acc; }, {});
	const taxNameCounts = (taxonomies ?? []).reduce((acc, t) => { acc[t.name] = (acc[t.name] ?? 0) + 1; return acc; }, {});

	return (
		<div className="es-seo-tab">
			<h2>{__('Sitemap', 'eightshift-seo')}</h2>
			<p className="description">
				{__('Exclusions apply to the WordPress native sitemap (/wp-sitemap.xml). Posts marked noindex are always excluded.', 'eightshift-seo')}
			</p>

			<h3>{__('Excluded post types', 'eightshift-seo')}</h3>
			{(postTypes ?? []).map((pt) => (
				<CheckboxControl
					key={pt.slug}
					label={ptNameCounts[pt.name] > 1 ? `${pt.name} (${pt.slug})` : pt.name}
					checked={(sitemap.excludedPostTypes ?? []).includes(pt.slug)}
					onChange={() =>
						setSitemap('excludedPostTypes', toggleArray(sitemap.excludedPostTypes, pt.slug))
					}
					__nextHasNoMarginBottom
				/>
			))}

			<h3>{__('Excluded taxonomies', 'eightshift-seo')}</h3>
			{(taxonomies ?? []).map((tax) => (
				<CheckboxControl
					key={tax.slug}
					label={taxNameCounts[tax.name] > 1 ? `${tax.name} (${tax.slug})` : tax.name}
					checked={(sitemap.excludedTaxonomies ?? []).includes(tax.slug)}
					onChange={() =>
						setSitemap('excludedTaxonomies', toggleArray(sitemap.excludedTaxonomies, tax.slug))
					}
					__nextHasNoMarginBottom
				/>
			))}

			<h3>{__('robots.txt', 'eightshift-seo')}</h3>
			<CheckboxControl
				label={__('Add sitemap URL to robots.txt', 'eightshift-seo')}
				checked={sitemap.addToRobotsTxt ?? true}
				onChange={(val) => setSitemap('addToRobotsTxt', val)}
				__nextHasNoMarginBottom
			/>
		</div>
	);
};
