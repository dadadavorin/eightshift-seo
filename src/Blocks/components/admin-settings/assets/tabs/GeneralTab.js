/**
 * General settings tab — separator, default OG image, Twitter handle,
 * webmaster verification codes.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, BaseControl, Button, Notice, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Simple image picker using wp.media directly.
 *
 * MediaUpload/MediaUploadCheck from @wordpress/block-editor depend on a
 * slot-fill that is only registered inside the block editor. On plain admin
 * pages the slot is never filled, so the render prop never fires. Using
 * wp.media() directly works on any admin page that loads wp-media.
 */
const AdminImagePicker = ({ label, help, value, onChange }) => {
	const [previewUrl, setPreviewUrl] = useState(null);

	// Fetch the attachment URL whenever the stored ID changes.
	useEffect(() => {
		if (!value || value <= 0) {
			setPreviewUrl(null);
			return;
		}
		apiFetch({ path: `/wp/v2/media/${value}?_fields=source_url` })
			.then((data) => setPreviewUrl(data?.source_url ?? null))
			.catch(() => setPreviewUrl(null));
	}, [value]);

	const openPicker = () => {
		if (!window.wp?.media) return;

		const frame = window.wp.media({
			title: label,
			button: { text: __('Select', 'eightshift-seo') },
			multiple: false,
			library: { type: 'image' },
		});

		frame.on('select', () => {
			const attachment = frame.state().get('selection').first().toJSON();
			onChange(attachment.id);
		});

		frame.open();
	};

	return (
		<BaseControl label={label} help={help} __nextHasNoMarginBottom>
			{previewUrl && (
				<div className="es-seo-image-preview">
					<img src={previewUrl} alt="" />
				</div>
			)}
			<div className="es-seo-media-field">
				<Button variant="secondary" onClick={openPicker}>
					{value > 0
						? __('Change image', 'eightshift-seo')
						: __('Select image', 'eightshift-seo')}
				</Button>
				{value > 0 && (
					<Button
						variant="link"
						isDestructive
						onClick={() => onChange(0)}
					>
						{__('Remove', 'eightshift-seo')}
					</Button>
				)}
			</div>
		</BaseControl>
	);
};

const WEBMASTER_ENGINES = [
	{
		key: 'google',
		label: __('Google Search Console', 'eightshift-seo'),
		help: __('From Search Console → Settings → Ownership verification → HTML tag.', 'eightshift-seo'),
		placeholder: 'e.g. abc123DEF456...',
	},
	{
		key: 'bing',
		label: __('Bing Webmaster Tools', 'eightshift-seo'),
		help: __('From Bing Webmaster Tools → Site ownership verification → Meta tag.', 'eightshift-seo'),
		placeholder: 'e.g. 1234ABCD5678...',
	},
	{
		key: 'yandex',
		label: __('Yandex Webmaster', 'eightshift-seo'),
		help: __('From Yandex.Webmaster → Settings → Verification → Meta tag.', 'eightshift-seo'),
		placeholder: 'e.g. abcdef1234567890',
	},
	{
		key: 'pinterest',
		label: __('Pinterest', 'eightshift-seo'),
		help: __('From Pinterest → Settings → Claimed accounts → Websites.', 'eightshift-seo'),
		placeholder: 'e.g. abcd1234efgh5678',
	},
	{
		key: 'baidu',
		label: __('Baidu Webmaster Tools', 'eightshift-seo'),
		help: __('From Baidu Ziyuan → Site verification → HTML tag.', 'eightshift-seo'),
		placeholder: 'e.g. code30digits',
	},
];

/**
 * Detect when the user pastes a full <meta> tag instead of just the code.
 */
const looksLikeFullMetaTag = (value) => /<\s*meta/i.test(value ?? '');

export const GeneralTab = ({ settings, onChange }) => {
	const set = (key, value) => onChange({ ...settings, [key]: value });

	const webmaster = settings.webmaster ?? {};
	const setWebmaster = (engineKey, value) =>
		onChange({
			...settings,
			webmaster: { ...webmaster, [engineKey]: value },
		});

	return (
		<div className="es-seo-tab">
			<h2>{__('General', 'eightshift-seo')}</h2>

			<TextControl
				label={__('Title separator', 'eightshift-seo')}
				help={__('Used between title parts, e.g. Post Title – Site Name', 'eightshift-seo')}
				value={settings.separator ?? '–'}
				onChange={(val) => set('separator', val)}
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={__('Twitter / X handle', 'eightshift-seo')}
				help={__('Without the @ sign, e.g. eightshift', 'eightshift-seo')}
				value={settings.twitterHandle ?? ''}
				onChange={(val) => set('twitterHandle', val)}
				__nextHasNoMarginBottom
			/>

			<SelectControl
				label={__('Default Twitter card type', 'eightshift-seo')}
				help={__('Site-wide default. Individual posts can override this in the editor sidebar.', 'eightshift-seo')}
				value={settings.twitterCardDefault ?? 'summary_large_image'}
				options={[
					{ label: __('Summary large image (recommended)', 'eightshift-seo'), value: 'summary_large_image' },
					{ label: __('Summary (small image)', 'eightshift-seo'),             value: 'summary' },
				]}
				onChange={(val) => set('twitterCardDefault', val)}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<AdminImagePicker
				label={__('Default OG image', 'eightshift-seo')}
				help={__('Fallback image used when a post has no featured image or OG image set.', 'eightshift-seo')}
				value={settings.defaultOgImage ?? 0}
				onChange={(id) => set('defaultOgImage', id)}
			/>

			<h3>{__('Webmaster verification', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Paste the verification code from each search engine. Only the code — the plugin emits the full <meta> tag for you.', 'eightshift-seo')}
			</p>

			{WEBMASTER_ENGINES.map((engine) => {
				const value = webmaster[engine.key] ?? '';
				const showWarning = looksLikeFullMetaTag(value);

				return (
					<div key={engine.key}>
						<TextControl
							label={engine.label}
							help={engine.help}
							value={value}
							placeholder={engine.placeholder}
							onChange={(val) => setWebmaster(engine.key, val)}
							__nextHasNoMarginBottom
						/>
						{showWarning && (
							<Notice status="warning" isDismissible={false}>
								{__('Paste only the verification code, not the entire <meta> tag. The plugin will extract the content automatically, but removing the surrounding markup is recommended.', 'eightshift-seo')}
							</Notice>
						)}
					</div>
				);
			})}
		</div>
	);
};
