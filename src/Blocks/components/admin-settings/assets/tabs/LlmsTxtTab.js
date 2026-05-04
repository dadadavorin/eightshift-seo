/**
 * llms.txt tab — configure the llms.txt file served from the root of the site.
 *
 * Phase 7 (Citable Content). Settings are stored under the `llmsTxt` key of
 * the global settings object.
 */

import { __ } from '@wordpress/i18n';
import { ToggleControl, TextareaControl, RangeControl, CheckboxControl } from '@wordpress/components';

/** Post types exposed in the UI. Hardcoded for now. */
const POST_TYPES = [
	{ id: 'page', label: __('Pages', 'eightshift-seo') },
	{ id: 'post', label: __('Posts', 'eightshift-seo') },
];

export const LlmsTxtTab = ({ settings, onChange }) => {
	const llmsTxt = settings.llmsTxt ?? {};

	/** Update a single key inside the llmsTxt sub-object. */
	const set = (key, value) =>
		onChange({ ...settings, llmsTxt: { ...llmsTxt, [key]: value } });

	const enabled      = llmsTxt.enabled      ?? true;
	const intro        = llmsTxt.intro        ?? '';
	const outro        = llmsTxt.outro        ?? '';
	const postTypes    = llmsTxt.postTypes    ?? ['page', 'post'];
	const perTypeLimit = llmsTxt.perTypeLimit ?? 200;

	const llmsTxtUrl = (typeof window !== 'undefined' ? window.location.origin : '') + '/llms.txt';

	/** Toggle a post-type id in/out of the array. */
	const togglePostType = (id, checked) => {
		const next = checked
			? [...postTypes, id]
			: postTypes.filter((t) => t !== id);
		set('postTypes', next);
	};

	return (
		<div className="es-seo-tab">
			<h2>{__('llms.txt', 'eightshift-seo')}</h2>
			<p className="description">
				{__('llms.txt is a plain-text / Markdown file at the root of your site that lets AI systems discover your key content and understand your site structure.', 'eightshift-seo')}
			</p>

			<ToggleControl
				label={__('Enable llms.txt', 'eightshift-seo')}
				checked={enabled}
				onChange={(val) => set('enabled', val)}
				__nextHasNoMarginBottom
			/>

			{!enabled && (
				<p
					className="description"
					style={{
						marginTop: 8,
						padding: '8px 12px',
						background: '#fff3cd',
						border: '1px solid #f0b849',
						borderRadius: 4,
						color: '#856404',
					}}
				>
					{__('llms.txt is disabled. Requests to /llms.txt will return 404.', 'eightshift-seo')}
				</p>
			)}

			{enabled && (
				<>
					<hr />

					<TextareaControl
						label={__('Intro (Markdown)', 'eightshift-seo')}
						help={__('Appears below the site name. Markdown is allowed.', 'eightshift-seo')}
						value={intro}
						onChange={(val) => set('intro', val)}
						rows={4}
						__nextHasNoMarginBottom
					/>

					<div style={{ marginTop: 16 }}>
						<TextareaControl
							label={__('Outro (Markdown)', 'eightshift-seo')}
							help={__('Appended at the end of the file.', 'eightshift-seo')}
							value={outro}
							onChange={(val) => set('outro', val)}
							rows={4}
							__nextHasNoMarginBottom
						/>
					</div>

					<hr />

					<p style={{ margin: '0 0 8px', fontWeight: 600 }}>
						{__('Include post types', 'eightshift-seo')}
					</p>

					<div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
						{POST_TYPES.map(({ id, label }) => (
							<CheckboxControl
								key={id}
								label={label}
								checked={postTypes.includes(id)}
								onChange={(checked) => togglePostType(id, checked)}
								__nextHasNoMarginBottom
							/>
						))}
					</div>

					<div style={{ marginTop: 16 }}>
						<RangeControl
							label={__('Per-type limit', 'eightshift-seo')}
							help={__('Maximum number of entries to include per post type. Total output is capped at 256 KB regardless of this setting.', 'eightshift-seo')}
							value={perTypeLimit}
							onChange={(val) => set('perTypeLimit', val)}
							min={50}
							max={500}
							step={50}
							__nextHasNoMarginBottom
						/>
					</div>

					<hr />

					<p className="description">
						{__('After saving, visit ', 'eightshift-seo')}
						<a
							href={llmsTxtUrl}
							target="_blank"
							rel="noreferrer noopener"
						>
							{llmsTxtUrl}
						</a>
					</p>
				</>
			)}
		</div>
	);
};
