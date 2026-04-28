/**
 * Advanced panel — noindex, nofollow, canonical override, advanced robots directives,
 * extended robots (noarchive, nosnippet, noimageindex, notranslate, unavailable_after),
 * and primary category picker.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl, SelectControl, DateTimePicker, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

export const AdvancedPanel = ({ getMeta, setMetaKey, postType }) => {
	const noindex         = getMeta('noindex');
	const nofollow        = getMeta('nofollow');
	const canonical       = getMeta('canonical');
	const maxSnippet      = getMeta('maxSnippet');
	const maxImagePreview = getMeta('maxImagePreview') || 'large';
	const maxVideoPreview = getMeta('maxVideoPreview');
	const noarchive       = getMeta('noarchive');
	const nosnippet       = getMeta('nosnippet');
	const noimageindex    = getMeta('noimageindex');
	const notranslate     = getMeta('notranslate');
	const noai            = getMeta('noai');
	const noimageai       = getMeta('noimageai');
	const unavailableAfter = getMeta('unavailableAfter') || '';
	const primaryCategory  = getMeta('primaryCategory') || 0;

	const [showAdvancedRobots, setShowAdvancedRobots] = useState(false);
	const [showDatePicker, setShowDatePicker] = useState(false);

	// Assigned categories for this post — used to populate primary category picker.
	const assignedCategoryIds = useSelect(
		(select) => select('core/editor')?.getEditedPostAttribute('categories') ?? [],
		[]
	);

	const [categoryOptions, setCategoryOptions] = useState(null);

	useEffect(() => {
		if (!assignedCategoryIds || assignedCategoryIds.length < 2) {
			setCategoryOptions(null);
			return;
		}
		const ids = assignedCategoryIds.join(',');
		apiFetch({ path: `/wp/v2/categories?include=${ids}&per_page=100&_fields=id,name` })
			.then((data) => {
				if (Array.isArray(data)) {
					setCategoryOptions(data.map((c) => ({ label: c.name, value: c.id })));
				}
			})
			.catch(() => setCategoryOptions(null));
	}, [assignedCategoryIds.join(',')]);

	const unavailableAfterLabel = unavailableAfter
		? new Date(unavailableAfter).toLocaleString()
		: __('Not set', 'eightshift-seo');

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

			<p className="es-seo-section-heading">
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

			<Button
				variant="link"
				onClick={() => setShowAdvancedRobots((v) => !v)}
				style={{ marginTop: 8 }}
			>
				{showAdvancedRobots
					? __('Hide extended directives ▲', 'eightshift-seo')
					: __('Show extended directives ▼', 'eightshift-seo')}
			</Button>

			{showAdvancedRobots && (
				<div style={{ marginTop: 8, display: 'flex', flexDirection: 'column', gap: 8 }}>
					<CheckboxControl
						label={__('noarchive — no cached copy', 'eightshift-seo')}
						help={__('Tells crawlers not to show a cached version of this page.', 'eightshift-seo')}
						checked={!!noarchive}
						onChange={(val) => setMetaKey('noarchive', val)}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={__('nosnippet — no text snippet', 'eightshift-seo')}
						help={__('Prevents Google from showing a text snippet in search results.', 'eightshift-seo')}
						checked={!!nosnippet}
						onChange={(val) => setMetaKey('nosnippet', val)}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={__('noimageindex — no image indexing', 'eightshift-seo')}
						help={__('Prevents images on this page from appearing in Google Image Search.', 'eightshift-seo')}
						checked={!!noimageindex}
						onChange={(val) => setMetaKey('noimageindex', val)}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={__('notranslate — no translation', 'eightshift-seo')}
						help={__('Tells Google not to offer a translation of this page.', 'eightshift-seo')}
						checked={!!notranslate}
						onChange={(val) => setMetaKey('notranslate', val)}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={__('noai — block AI training crawlers', 'eightshift-seo')}
						help={__('Emits noai in the robots meta tag. Honoured by training-category AI bots (GPTBot, ClaudeBot, Google-Extended, etc.).', 'eightshift-seo')}
						checked={!!noai}
						onChange={(val) => setMetaKey('noai', val)}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={__('noimageai — block AI image training', 'eightshift-seo')}
						help={__('Emits noimageai in the robots meta tag to prevent images on this page from being used for AI training.', 'eightshift-seo')}
						checked={!!noimageai}
						onChange={(val) => setMetaKey('noimageai', val)}
						__nextHasNoMarginBottom
					/>

					<div>
						<p style={{ margin: '0 0 4px', fontSize: 12, fontWeight: 500 }}>
							{__('Unavailable after', 'eightshift-seo')}
						</p>
						<p style={{ margin: '0 0 6px', fontSize: 11, color: '#757575' }}>
							{__('Crawlers should not index this page after the selected date.', 'eightshift-seo')}
						</p>
						<div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
							<Button
								variant="secondary"
								size="small"
								onClick={() => setShowDatePicker((v) => !v)}
							>
								{unavailableAfterLabel}
							</Button>
							{unavailableAfter && (
								<Button
									variant="link"
									isDestructive
									size="small"
									onClick={() => { setMetaKey('unavailableAfter', ''); setShowDatePicker(false); }}
								>
									{__('Clear', 'eightshift-seo')}
								</Button>
							)}
						</div>
						{showDatePicker && (
							<DateTimePicker
								currentDate={unavailableAfter || undefined}
								onChange={(val) => { setMetaKey('unavailableAfter', val || ''); setShowDatePicker(false); }}
								is12Hour={false}
							/>
						)}
					</div>
				</div>
			)}

			{categoryOptions && categoryOptions.length >= 2 && (
				<>
					<hr />
					<SelectControl
						label={__('Primary category', 'eightshift-seo')}
						help={__('Used in breadcrumbs and the %primary_category% token.', 'eightshift-seo')}
						value={String(primaryCategory || 0)}
						options={[
							{ label: __('— Auto (first assigned) —', 'eightshift-seo'), value: '0' },
							...categoryOptions.map((c) => ({ label: c.label, value: String(c.value) })),
						]}
						onChange={(val) => setMetaKey('primaryCategory', parseInt(val, 10) || 0)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</>
			)}
		</div>
	);
};
