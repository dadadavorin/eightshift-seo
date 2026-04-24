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

	const tokens = [
		{ token: '%title%',            help: __('Post or archive title', 'eightshift-seo') },
		{ token: '%sitename%',         help: __('Blog name (Settings → General)', 'eightshift-seo') },
		{ token: '%tagline%',          help: __('Blog tagline / description', 'eightshift-seo') },
		{ token: '%sep%',              help: __('Configured title separator', 'eightshift-seo') },
		{ token: '%excerpt%',          help: __('Post excerpt (auto-generated if empty)', 'eightshift-seo') },
		{ token: '%author%',           help: __('Post author display name', 'eightshift-seo') },
		{ token: '%date%',             help: __('Post publish date', 'eightshift-seo') },
		{ token: '%modified_date%',    help: __('Post modified date', 'eightshift-seo') },
		{ token: '%id%',               help: __('Post or term ID', 'eightshift-seo') },
		{ token: '%parent_title%',     help: __('Parent post title (hierarchical types)', 'eightshift-seo') },
		{ token: '%primary_category%', help: __('Primary category (falls back to first)', 'eightshift-seo') },
		{ token: '%category%',         help: __('Comma-separated category names', 'eightshift-seo') },
		{ token: '%tag%',              help: __('Comma-separated tag names', 'eightshift-seo') },
		{ token: '%archive_title%',    help: __('Post type or term archive label', 'eightshift-seo') },
		{ token: '%page%',             help: __('Current page number on paginated content', 'eightshift-seo') },
		{ token: '%pagetotal%',        help: __('Total pages on paginated content', 'eightshift-seo') },
		{ token: '%search_phrase%',    help: __('Search phrase on search result pages', 'eightshift-seo') },
		{ token: '%current_year%',     help: __('Current year', 'eightshift-seo') },
	];

	const copyToken = (token) => {
		if (navigator?.clipboard?.writeText) {
			navigator.clipboard.writeText(token);
		}
	};

	return (
		<div className="es-seo-tab">
			<h2>{__('Default templates', 'eightshift-seo')}</h2>

			<div>
				<p className="description" style={{ margin: '0 0 8px' }}>
					{__('Available tokens (click to copy):', 'eightshift-seo')}
				</p>
				<div className="es-seo-token-list">
					{tokens.map(({ token, help }) => (
						<button
							key={token}
							type="button"
							title={help}
							aria-label={`${token} — ${help}`}
							className="es-seo-token es-seo-token--clickable"
							onClick={() => copyToken(token)}
						>
							{token}
						</button>
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
