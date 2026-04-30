/**
 * Advanced settings tab — archive defaults, attachment redirect,
 * per-taxonomy robots defaults.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Notice, SelectControl, ToggleControl, RangeControl } from '@wordpress/components';

const { taxonomies } = window.esSeoLocalization ?? {};

const ARCHIVE_TOGGLES = [
	{ key: 'search',     label: __('Noindex search results', 'eightshift-seo') },
	{ key: 'date',       label: __('Noindex date archives (year / month / day)', 'eightshift-seo') },
	{ key: 'paged',      label: __('Noindex paginated archives (page 2+)', 'eightshift-seo') },
	{ key: '404',        label: __('Noindex 404 pages', 'eightshift-seo') },
	{ key: 'attachment', label: __('Noindex attachment pages', 'eightshift-seo') },
];

const AUTHOR_OPTIONS = [
	{ label: __('Always index', 'eightshift-seo'),                    value: 'never' },
	{ label: __('Never index (always noindex)', 'eightshift-seo'),    value: 'always' },
	{ label: __('Noindex if only one author on the site', 'eightshift-seo'), value: 'auto' },
];

const ATTACHMENT_REDIRECT_OPTIONS = [
	{ label: __('Redirect to attachment file URL (recommended)', 'eightshift-seo'), value: 'file' },
	{ label: __('Redirect to parent post (falls back to file if no parent)', 'eightshift-seo'), value: 'parent' },
	{ label: __('Do not redirect', 'eightshift-seo'), value: 'disabled' },
];

export const AdvancedTab = ({ settings, onChange }) => {
	const robotsDefaults = settings.robotsDefaults ?? { taxonomies: {}, archives: {} };
	const taxRobots      = robotsDefaults.taxonomies ?? {};
	const archives       = robotsDefaults.archives ?? {};

	const setArchive = (key, value) => {
		onChange({
			...settings,
			robotsDefaults: {
				...robotsDefaults,
				archives: { ...archives, [key]: value },
			},
		});
	};

	const setTaxRobot = (taxSlug, key, value) => {
		onChange({
			...settings,
			robotsDefaults: {
				...robotsDefaults,
				taxonomies: {
					...taxRobots,
					[taxSlug]: {
						...(taxRobots[taxSlug] ?? {}),
						[key]: value,
					},
				},
			},
		});
	};

	const attachmentRedirect = settings.attachmentRedirect ?? 'file';
	const images = settings.images ?? { autoFillAlt: true, includeSitemap: true };

	const setImages = (key, value) =>
		onChange({ ...settings, images: { ...images, [key]: value } });

	const freshness = settings.freshness ?? { preserveModifiedOnNonContentSave: false, stalenessThresholdDays: 365 };
	const setFreshness = (key, value) =>
		onChange({ ...settings, freshness: { ...freshness, [key]: value } });

	const botInsights = settings.botInsights ?? { enabled: false, samplingRate: 1.0, retentionDays: 90 };
	const setBotInsights = (key, value) =>
		onChange({ ...settings, botInsights: { ...botInsights, [key]: value } });

	const [resetNotice, setResetNotice] = useState(null);
	const [isResetting, setIsResetting] = useState(false);

	const resetBotCounters = () => {
		if (!window.confirm(__('Delete all recorded AI-bot counters? This cannot be undone.', 'eightshift-seo'))) {
			return;
		}
		setIsResetting(true);
		setResetNotice(null);
		apiFetch({
			path: '/es-seo/v1/bot-insights/reset',
			method: 'POST',
		})
			.then(() => setResetNotice({ type: 'success', message: __('Bot counters cleared.', 'eightshift-seo') }))
			.catch(() => setResetNotice({ type: 'error', message: __('Failed to clear bot counters.', 'eightshift-seo') }))
			.finally(() => setIsResetting(false));
	};

	return (
		<div className="es-seo-tab">
			<h2>{__('Advanced', 'eightshift-seo')}</h2>

			<h3>{__('Archive defaults', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Automatically hide low-value archive pages from search engines. Per-post settings take precedence.', 'eightshift-seo')}
			</p>

			{ARCHIVE_TOGGLES.map((t) => (
				<CheckboxControl
					key={t.key}
					label={t.label}
					checked={archives[t.key] !== false}
					onChange={(val) => setArchive(t.key, val)}
					__nextHasNoMarginBottom
				/>
			))}

			<SelectControl
				label={__('Author archives', 'eightshift-seo')}
				help={__('Single-author blogs usually noindex the author archive because it duplicates the homepage feed.', 'eightshift-seo')}
				value={archives.author ?? 'auto'}
				options={AUTHOR_OPTIONS}
				onChange={(val) => setArchive('author', val)}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<h3>{__('Attachment pages', 'eightshift-seo')}</h3>
			<SelectControl
				label={__('Redirect behavior', 'eightshift-seo')}
				help={__('WordPress generates a thin page for each media attachment. Redirecting to the file or parent post removes this duplicate content.', 'eightshift-seo')}
				value={attachmentRedirect}
				options={ATTACHMENT_REDIRECT_OPTIONS}
				onChange={(val) => onChange({ ...settings, attachmentRedirect: val })}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<h3>{__('Per-taxonomy robots defaults', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Set default robots directives for all archive pages of each taxonomy. Per-term settings take precedence over these defaults.', 'eightshift-seo')}
			</p>

			{(!taxonomies || taxonomies.length === 0) ? (
				<p>{__('No public taxonomies found.', 'eightshift-seo')}</p>
			) : (
				taxonomies.map((tax) => {
					const current = taxRobots[tax.slug] ?? {};

					return (
						<div key={tax.slug} style={{ marginBottom: '16px' }}>
							<h4 style={{ margin: '0 0 6px' }}>{tax.name}</h4>

							<CheckboxControl
								label={__('noindex — exclude archives from search engines', 'eightshift-seo')}
								checked={!!current.noindex}
								onChange={(val) => setTaxRobot(tax.slug, 'noindex', val)}
								__nextHasNoMarginBottom
							/>

							<CheckboxControl
								label={__('nofollow — do not follow links on archive pages', 'eightshift-seo')}
								checked={!!current.nofollow}
								onChange={(val) => setTaxRobot(tax.slug, 'nofollow', val)}
								__nextHasNoMarginBottom
							/>

							<CheckboxControl
								label={__('noarchive — no cached copy', 'eightshift-seo')}
								checked={!!current.noarchive}
								onChange={(val) => setTaxRobot(tax.slug, 'noarchive', val)}
								__nextHasNoMarginBottom
							/>

							<CheckboxControl
								label={__('nosnippet — no text snippet', 'eightshift-seo')}
								checked={!!current.nosnippet}
								onChange={(val) => setTaxRobot(tax.slug, 'nosnippet', val)}
								__nextHasNoMarginBottom
							/>

							<CheckboxControl
								label={__('noimageindex — no image indexing', 'eightshift-seo')}
								checked={!!current.noimageindex}
								onChange={(val) => setTaxRobot(tax.slug, 'noimageindex', val)}
								__nextHasNoMarginBottom
							/>

							<CheckboxControl
								label={__('notranslate — no translation', 'eightshift-seo')}
								checked={!!current.notranslate}
								onChange={(val) => setTaxRobot(tax.slug, 'notranslate', val)}
								__nextHasNoMarginBottom
							/>
						</div>
					);
				})
			)}

			<hr />

			<h3>{__('Image SEO', 'eightshift-seo')}</h3>

			<ToggleControl
				label={__('Auto-fill image alt text on upload', 'eightshift-seo')}
				help={__('Derives alt text from the file name when no alt is set during upload.', 'eightshift-seo')}
				checked={images.autoFillAlt !== false}
				onChange={(val) => setImages('autoFillAlt', val)}
				__nextHasNoMarginBottom
			/>

			<ToggleControl
				label={__('Include images in sitemap', 'eightshift-seo')}
				help={__('Adds an image sitemap at /wp-sitemap-es-seo-images-1.xml for featured and content images.', 'eightshift-seo')}
				checked={images.includeSitemap !== false}
				onChange={(val) => setImages('includeSitemap', val)}
				__nextHasNoMarginBottom
			/>

			<hr />

			<h3>{__('Content freshness', 'eightshift-seo')}</h3>
			<p className="description">
				{__('AI engines weight dateModified heavily. These settings keep that signal honest.', 'eightshift-seo')}
			</p>

			<ToggleControl
				label={__('Preserve modified date on non-content saves', 'eightshift-seo')}
				help={__('When enabled, post_modified is only bumped when the content body actually changes. Quick edits to taxonomies or sidebar fields will not refresh the timestamp.', 'eightshift-seo')}
				checked={!!freshness.preserveModifiedOnNonContentSave}
				onChange={(val) => setFreshness('preserveModifiedOnNonContentSave', val)}
				__nextHasNoMarginBottom
			/>

			<RangeControl
				label={__('Staleness threshold (days)', 'eightshift-seo')}
				help={__('Health check warns when published posts have not been updated in this many days.', 'eightshift-seo')}
				value={freshness.stalenessThresholdDays ?? 365}
				onChange={(val) => setFreshness('stalenessThresholdDays', val)}
				min={30}
				max={1095}
				step={30}
				__nextHasNoMarginBottom
			/>

			<hr id="bot-insights" />

			<h3>{__('AI bot traffic insights', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Track how often known AI crawlers hit your site. Counters are aggregated per day and per bot — no IPs, URLs, or request bodies are stored.', 'eightshift-seo')}
			</p>

			<ToggleControl
				label={__('Enable bot traffic tracking', 'eightshift-seo')}
				help={__('Adds a single INSERT per request only when a known AI bot is detected. Human visitors incur zero database writes.', 'eightshift-seo')}
				checked={!!botInsights.enabled}
				onChange={(val) => setBotInsights('enabled', val)}
				__nextHasNoMarginBottom
			/>

			{botInsights.enabled && (
				<>
					<RangeControl
						label={__('Retention (days)', 'eightshift-seo')}
						help={__('Older rows are pruned daily. Table size is bounded — at most ~20 bots × retention days.', 'eightshift-seo')}
						value={botInsights.retentionDays ?? 90}
						onChange={(val) => setBotInsights('retentionDays', val)}
						min={30}
						max={365}
						step={30}
						__nextHasNoMarginBottom
					/>

					<RangeControl
						label={__('Sampling rate', 'eightshift-seo')}
						help={__('Fraction of bot hits to record. Lower this on extremely high-traffic sites (e.g. 0.1 = log every tenth hit).', 'eightshift-seo')}
						value={Math.round(((botInsights.samplingRate ?? 1) * 100))}
						onChange={(val) => setBotInsights('samplingRate', Math.max(0.01, Math.min(1, val / 100)))}
						min={1}
						max={100}
						step={1}
						__nextHasNoMarginBottom
					/>

					<p style={{ marginTop: 12 }}>
						<Button variant="secondary" isDestructive isBusy={isResetting} onClick={resetBotCounters}>
							{__('Reset counters', 'eightshift-seo')}
						</Button>
					</p>

					{resetNotice && (
						<Notice status={resetNotice.type} isDismissible onRemove={() => setResetNotice(null)}>
							{resetNotice.message}
						</Notice>
					)}
				</>
			)}
		</div>
	);
};
