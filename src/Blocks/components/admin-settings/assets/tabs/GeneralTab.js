/**
 * General settings tab — separator, default OG image, Twitter handle.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, BaseControl, Button } from '@wordpress/components';
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

export const GeneralTab = ({ settings, onChange }) => {
	const set = (key, value) => onChange({ ...settings, [key]: value });

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

			<AdminImagePicker
				label={__('Default OG image', 'eightshift-seo')}
				help={__('Fallback image used when a post has no featured image or OG image set.', 'eightshift-seo')}
				value={settings.defaultOgImage ?? 0}
				onChange={(id) => set('defaultOgImage', id)}
			/>
		</div>
	);
};
