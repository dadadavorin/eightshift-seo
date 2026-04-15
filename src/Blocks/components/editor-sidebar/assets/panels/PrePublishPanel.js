/**
 * Pre-publish SEO checks panel — runs keyphrase analysis and surfaces missing-field
 * warnings when the user clicks "Publish" or "Schedule".
 */

import { __ } from '@wordpress/i18n';
import { PluginPrePublishPanel } from '@wordpress/editor';
import { TextControl, Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';

const { metaKeys } = window.esSeoEditorLocalization ?? {};

/**
 * Strips HTML tags and returns plain text, capped at `limit` characters.
 *
 * @param {string} html  Raw HTML string.
 * @param {number} limit Max character count.
 * @returns {string}
 */
const stripHtml = (html, limit = 500) =>
	html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, limit).toLowerCase();

export const PrePublishPanel = () => {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);
	const postTitle = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('title') ?? '',
		[]
	);
	const permalink = useSelect(
		(select) => select('core/editor').getPermalink() ?? '',
		[]
	);
	const postContent = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('content') ?? '',
		[]
	);

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');
	const getMeta = (key) => meta?.[metaKeys?.[key]] ?? '';
	const setMetaKey = (key, value) =>
		setMeta({ ...meta, [metaKeys?.[key]]: value });

	const keyphrase    = getMeta('focusKeyphrase');
	const seoTitle     = getMeta('title');
	const seoDesc      = getMeta('description');

	// Build comparison strings.
	const displayTitle  = (seoTitle || postTitle).toLowerCase();
	const descLower     = seoDesc.toLowerCase();
	const contentPlain  = stripHtml(postContent);
	const kw            = keyphrase.toLowerCase().trim();

	let slugPart = '';
	try {
		slugPart = new URL(permalink).pathname.toLowerCase();
	} catch {
		// use empty string
	}

	// Keyphrase checks — only shown when a keyphrase is set.
	const keyphraseChecks = kw
		? [
			{
				label: __('Keyphrase in SEO title', 'eightshift-seo'),
				pass:  displayTitle.includes(kw),
			},
			{
				label: __('Keyphrase in URL slug', 'eightshift-seo'),
				pass:  slugPart.includes(kw.replace(/\s+/g, '-')) || slugPart.includes(kw),
			},
			{
				label: __('Keyphrase in first paragraph', 'eightshift-seo'),
				pass:  contentPlain.includes(kw),
			},
			{
				label: __('Keyphrase in meta description', 'eightshift-seo'),
				pass:  descLower.includes(kw),
			},
		]
		: [];

	const hasIssues = !seoTitle || !seoDesc || keyphraseChecks.some((c) => !c.pass);

	return (
		<PluginPrePublishPanel
			name="eightshift-seo-prepublish"
			title={__('SEO', 'eightshift-seo')}
			initialOpen={hasIssues}
		>
			<TextControl
				label={__('Focus keyphrase', 'eightshift-seo')}
				help={__('The main keyword or phrase this post should rank for.', 'eightshift-seo')}
				value={keyphrase}
				onChange={(val) => setMetaKey('focusKeyphrase', val)}
				__nextHasNoMarginBottom
			/>

			{!seoTitle && (
				<Notice status="warning" isDismissible={false}>
					{__('No SEO title set — the post title will be used.', 'eightshift-seo')}
				</Notice>
			)}

			{!seoDesc && (
				<Notice status="warning" isDismissible={false}>
					{__('No meta description set — search engines may auto-generate one.', 'eightshift-seo')}
				</Notice>
			)}

			{!kw && (
				<Notice status="info" isDismissible={false}>
					{__('Enter a focus keyphrase above to run on-page SEO checks.', 'eightshift-seo')}
				</Notice>
			)}

			{keyphraseChecks.length > 0 && (
				<ul style={{ margin: '8px 0 0', padding: 0, listStyle: 'none' }}>
					{keyphraseChecks.map((check, i) => (
						<li
							key={i}
							style={{
								display: 'flex',
								alignItems: 'center',
								gap: '6px',
								marginBottom: '4px',
								color: check.pass ? '#00a32a' : '#d63638',
								fontSize: '13px',
							}}
						>
							<span aria-hidden="true">{check.pass ? '✓' : '✗'}</span>
							{check.label}
						</li>
					))}
				</ul>
			)}
		</PluginPrePublishPanel>
	);
};
