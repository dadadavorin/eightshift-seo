/**
 * Search Appearance panel — title, description with counters and SERP preview.
 */

import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { SerpPreview } from '../SerpPreview';

const { separator, siteName } = window.esSeoEditorLocalization ?? {};

export const SearchAppearancePanel = ({ getMeta, setMetaKey }) => {
	const postTitle = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('title') ?? '',
		[]
	);
	const permalink = useSelect(
		(select) => select('core/editor').getPermalink() ?? '',
		[]
	);

	const seoTitle       = getMeta('title');
	const seoDescription = getMeta('description');

	// Resolve displayed title for preview: meta override → post title + separator + site name
	const previewTitle = seoTitle
		|| `${postTitle} ${separator ?? '–'} ${siteName ?? ''}`;

	return (
		<div className="es-seo-search-panel">
			<SerpPreview
				title={previewTitle}
				url={permalink}
				description={seoDescription}
			/>

			<TextControl
				label={__('SEO title', 'eightshift-seo')}
				help={`${seoTitle.length}/160`}
				value={seoTitle}
				onChange={(val) => setMetaKey('title', val)}
				placeholder={previewTitle}
				maxLength={160}
				__nextHasNoMarginBottom
			/>

			<TextareaControl
				label={__('Meta description', 'eightshift-seo')}
				help={`${seoDescription.length}/160`}
				value={seoDescription}
				onChange={(val) => setMetaKey('description', val)}
				maxLength={320}
				rows={3}
				__nextHasNoMarginBottom
			/>
		</div>
	);
};
