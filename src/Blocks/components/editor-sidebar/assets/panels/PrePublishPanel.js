/**
 * Pre-publish SEO checks panel — runs keyphrase analysis and surfaces missing-field
 * warnings when the user clicks "Publish" or "Schedule".
 */

import { __ } from '@wordpress/i18n';
import { PluginPrePublishPanel } from '@wordpress/editor';
import { TextControl, Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';

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

	// Block tree for GEO structural checks.
	const blocks = useSelect(
		(select) => select('core/block-editor')?.getBlocks() ?? [],
		[]
	);

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');
	const getMeta = (key) => meta?.[metaKeys?.[key]] ?? '';
	const setMetaKey = (key, value) =>
		setMeta({ ...meta, [metaKeys?.[key]]: value });

	const keyphrase    = getMeta('focusKeyphrase');
	const seoTitle     = getMeta('title');
	const seoDesc      = getMeta('description');

	// Featured image alt check.
	const featuredImageId = useSelect(
		(select) => select('core/editor')?.getEditedPostAttribute('featured_media') ?? 0,
		[]
	);
	const featuredImageAlt = useSelect(
		(select) => {
			if (!featuredImageId) return null;
			const media = select('core').getMedia(featuredImageId);
			return media ? (media.alt_text ?? '') : null;
		},
		[featuredImageId]
	);
	const featuredImageMissingAlt = featuredImageId > 0 && featuredImageAlt === '';

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

	// ── GEO checks ──────────────────────────────────────────────────────────

	const tldr = getMeta('tldr');

	const citations = (() => {
		try {
			const v = getMeta('citations');
			return Array.isArray(v) ? v : [];
		} catch {
			return [];
		}
	})();

	const faq = (() => {
		try {
			const v = getMeta('faq');
			return Array.isArray(v) ? v : [];
		} catch {
			return [];
		}
	})();

	// Flatten block tree to extract headings and images.
	const headingLevels = [];
	let imagesWithoutAlt = 0;

	const flattenBlocks = (bs) =>
		bs.forEach((b) => {
			if (b.name === 'core/heading') headingLevels.push(b.attributes.level);
			if (b.name === 'core/image' && !b.attributes.alt) imagesWithoutAlt++;
			if (b.innerBlocks?.length) flattenBlocks(b.innerBlocks);
		});

	flattenBlocks(blocks);

	let headingHierarchyOk = true;
	for (let i = 1; i < headingLevels.length; i++) {
		if (headingLevels[i] > headingLevels[i - 1] + 1) {
			headingHierarchyOk = false;
			break;
		}
	}

	// Definition-first: first 200 chars of content contain is/are/means/refers to.
	const definitionFirstOk = /^[^.!?]*\b(is|are|means|refers to)\b/i.test(contentPlain.slice(0, 200));

	// At least one statistic.
	const hasStatistic = /\b\d+(\.\d+)?\s?%|\b(in|by|since)\s\d{4}\b/.test(contentPlain);

	const geoChecks = [
		{
			label: __('TL;DR / Direct Answer filled', 'eightshift-seo'),
			pass:  !!tldr,
			type:  'warn',
		},
		{
			label: __('Definition-first opener', 'eightshift-seo'),
			pass:  definitionFirstOk,
			type:  'info',
		},
		{
			label: __('Contains a statistic or data point', 'eightshift-seo'),
			pass:  hasStatistic,
			type:  'info',
		},
		{
			label: __('Contains at least one citation', 'eightshift-seo'),
			pass:  citations.length > 0,
			type:  'info',
		},
		{
			label: __('Heading hierarchy correct (no skipped levels)', 'eightshift-seo'),
			pass:  headingHierarchyOk || headingLevels.length === 0,
			type:  'warn',
		},
		{
			label: imagesWithoutAlt === 0
				? __('All images have alt text', 'eightshift-seo')
				: `${imagesWithoutAlt} ${__('image(s) missing alt text', 'eightshift-seo')}`,
			pass:  imagesWithoutAlt === 0,
			type:  'warn',
		},
		{
			label: __('FAQ schema added', 'eightshift-seo'),
			pass:  faq.length > 0,
			type:  'info',
		},
	];

	const tldrMissing = !tldr;

	const hasIssues =
		!seoTitle ||
		!seoDesc  ||
		featuredImageMissingAlt ||
		tldrMissing ||
		citations.length === 0 ||
		keyphraseChecks.some((c) => !c.pass);

	// GEO check icon helper.
	const geoIcon = (check) => {
		if (check.pass) return '✓';
		return check.type === 'warn' ? '⚠' : 'ⓘ';
	};
	const geoColor = (check) => {
		if (check.pass)               return '#00a32a'; // green
		if (check.type === 'warn')    return '#f0b849'; // orange
		return '#757575';                               // gray (info)
	};

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

			{featuredImageMissingAlt && (
				<Notice status="warning" isDismissible={false}>
					{__('Featured image has no alt text set.', 'eightshift-seo')}
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

			{/* ── GEO readiness ─────────────────────────────────────────── */}
			<hr style={{ margin: '12px 0 8px' }} />

			<p style={{ margin: '0 0 6px', fontWeight: 600, fontSize: 13 }}>
				{__('GEO readiness', 'eightshift-seo')}
			</p>

			<ul style={{ margin: 0, padding: 0, listStyle: 'none' }}>
				{geoChecks.map((check, i) => (
					<li
						key={i}
						style={{
							display: 'flex',
							alignItems: 'center',
							gap: '6px',
							marginBottom: '4px',
							color: geoColor(check),
							fontSize: '13px',
						}}
					>
						<span aria-hidden="true">{geoIcon(check)}</span>
						{check.label}
					</li>
				))}
			</ul>
		</PluginPrePublishPanel>
	);
};
