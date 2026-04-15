/**
 * Google SERP preview component — shows a live preview of how the page
 * will appear in Google search results.
 */

import { __ } from '@wordpress/i18n';

export const SerpPreview = ({ title, url, description }) => {
	// Trim URL to the path for display.
	let displayUrl = url;
	try {
		const parsed = new URL(url);
		displayUrl = parsed.hostname + parsed.pathname;
	} catch {
		// use url as-is
	}

	return (
		<div className="es-seo-serp-preview">
			<p className="es-seo-serp-preview__label">{__('Search preview', 'eightshift-seo')}</p>
			<div className="es-seo-serp-preview__box">
				<div className="es-seo-serp-preview__url">{displayUrl}</div>
				<div className="es-seo-serp-preview__title">{title || __('(no title)', 'eightshift-seo')}</div>
				{description && (
					<div className="es-seo-serp-preview__description">
						{description.substring(0, 160)}
					</div>
				)}
			</div>
		</div>
	);
};
