/**
 * Advanced panel — noindex, nofollow, canonical override, and advanced robots directives.
 */

import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl, SelectControl } from '@wordpress/components';

export const AdvancedPanel = ({ getMeta, setMetaKey }) => {
	const noindex         = getMeta('noindex');
	const nofollow        = getMeta('nofollow');
	const canonical       = getMeta('canonical');
	const maxSnippet      = getMeta('maxSnippet');
	const maxImagePreview = getMeta('maxImagePreview') || 'large';
	const maxVideoPreview = getMeta('maxVideoPreview');

	return (
		<div className="es-seo-advanced-panel">
			<CheckboxControl
				label={__('noindex — exclude from search engines', 'eightshift-seo')}
				help={__('Adds noindex to the robots meta tag and excludes the post from the sitemap.', 'eightshift-seo')}
				checked={!!noindex}
				onChange={(val) => setMetaKey('noindex', val)}
				__nextHasNoMarginBottom
			/>

			<CheckboxControl
				label={__('nofollow — do not follow links', 'eightshift-seo')}
				help={__('Adds nofollow to the robots meta tag.', 'eightshift-seo')}
				checked={!!nofollow}
				onChange={(val) => setMetaKey('nofollow', val)}
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={__('Canonical URL override', 'eightshift-seo')}
				help={__('Leave empty to use the default permalink.', 'eightshift-seo')}
				value={canonical}
				onChange={(val) => setMetaKey('canonical', val)}
				type="url"
				__nextHasNoMarginBottom
			/>

			<hr />

			<p style={{ fontWeight: 600, margin: '0 0 8px' }}>
				{__('Advanced robots directives', 'eightshift-seo')}
			</p>

			<TextControl
				label={__('Max snippet length', 'eightshift-seo')}
				help={__('-1 = no limit. Sets the max-snippet robots directive (characters).', 'eightshift-seo')}
				value={maxSnippet === '' ? '-1' : String(maxSnippet)}
				onChange={(val) => setMetaKey('maxSnippet', parseInt(val, 10) || -1)}
				type="number"
				min="-1"
				__nextHasNoMarginBottom
			/>

			<SelectControl
				label={__('Max image preview', 'eightshift-seo')}
				help={__('Controls how large an image preview Google may show.', 'eightshift-seo')}
				value={maxImagePreview}
				options={[
					{ label: __('Large (default)', 'eightshift-seo'), value: 'large' },
					{ label: __('Standard', 'eightshift-seo'),         value: 'standard' },
					{ label: __('None', 'eightshift-seo'),             value: 'none' },
				]}
				onChange={(val) => setMetaKey('maxImagePreview', val)}
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={__('Max video preview duration', 'eightshift-seo')}
				help={__('-1 = no limit. Sets the max-video-preview directive (seconds).', 'eightshift-seo')}
				value={maxVideoPreview === '' ? '-1' : String(maxVideoPreview)}
				onChange={(val) => setMetaKey('maxVideoPreview', parseInt(val, 10) || -1)}
				type="number"
				min="-1"
				__nextHasNoMarginBottom
			/>
		</div>
	);
};
