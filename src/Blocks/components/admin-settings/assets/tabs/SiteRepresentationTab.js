/**
 * Site Representation tab — configure Organization or Person schema emitted
 * on the homepage, including logo, social profiles, and sameAs entries.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	BaseControl,
	Button,
	RadioControl,
	SelectControl,
	Notice,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const KNOWN_SOCIALS = [
	{ key: 'facebook',  label: __('Facebook', 'eightshift-seo') },
	{ key: 'instagram', label: __('Instagram', 'eightshift-seo') },
	{ key: 'linkedin',  label: __('LinkedIn', 'eightshift-seo') },
	{ key: 'youtube',   label: __('YouTube', 'eightshift-seo') },
	{ key: 'twitter',   label: __('Twitter / X', 'eightshift-seo') },
	{ key: 'github',    label: __('GitHub', 'eightshift-seo') },
	{ key: 'wikipedia', label: __('Wikipedia', 'eightshift-seo') },
];

/**
 * Reusable image picker (scoped to this tab; mirrors the GeneralTab picker).
 */
const AdminImagePicker = ({ label, help, value, onChange }) => {
	const [previewUrl, setPreviewUrl] = useState(null);

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
					<Button variant="link" isDestructive onClick={() => onChange(0)}>
						{__('Remove', 'eightshift-seo')}
					</Button>
				)}
			</div>
		</BaseControl>
	);
};

/**
 * User selector — fetches users with edit_posts capability from /wp/v2/users.
 */
const UserSelect = ({ value, onChange }) => {
	const [users, setUsers] = useState(null);

	useEffect(() => {
		apiFetch({ path: '/wp/v2/users?context=edit&per_page=100&_fields=id,name' })
			.then((data) => setUsers(Array.isArray(data) ? data : []))
			.catch(() => setUsers([]));
	}, []);

	if (users === null) {
		return <p>{__('Loading users…', 'eightshift-seo')}</p>;
	}

	const options = [
		{ label: __('— Select a user —', 'eightshift-seo'), value: '0' },
		...users.map((u) => ({ label: u.name, value: String(u.id) })),
	];

	return (
		<SelectControl
			label={__('Person', 'eightshift-seo')}
			help={__('Which site user represents this site.', 'eightshift-seo')}
			value={String(value ?? 0)}
			options={options}
			onChange={(val) => onChange(parseInt(val, 10) || 0)}
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
};

export const SiteRepresentationTab = ({ settings, onChange }) => {
	const rep = settings.siteRepresentation ?? {
		type: 'organization',
		name: '',
		logo: 0,
		personId: 0,
		social: {},
	};

	const social = rep.social ?? {};

	const setRep = (partial) =>
		onChange({ ...settings, siteRepresentation: { ...rep, ...partial } });

	const setSocial = (key, value) =>
		setRep({ social: { ...social, [key]: value } });

	const otherUrls = Array.isArray(social.other) ? social.other : [];

	const setOtherAt = (index, value) => {
		const next = [...otherUrls];
		next[index] = value;
		setSocial('other', next);
	};

	const addOther = () => setSocial('other', [...otherUrls, '']);

	const removeOther = (index) => {
		const next = otherUrls.filter((_, i) => i !== index);
		setSocial('other', next);
	};

	return (
		<div className="es-seo-tab">
			<h2>{__('Site representation', 'eightshift-seo')}</h2>

			<Notice status="info" isDismissible={false}>
				{__('This information produces Organization or Person JSON-LD on the homepage. It helps search engines associate your social profiles and logo with the site.', 'eightshift-seo')}
			</Notice>

			<RadioControl
				label={__('This site represents', 'eightshift-seo')}
				selected={rep.type ?? 'organization'}
				options={[
					{ label: __('An organization', 'eightshift-seo'), value: 'organization' },
					{ label: __('A person',       'eightshift-seo'), value: 'person' },
				]}
				onChange={(val) => setRep({ type: val })}
			/>

			{rep.type === 'person' ? (
				<UserSelect
					value={rep.personId ?? 0}
					onChange={(id) => setRep({ personId: id })}
				/>
			) : (
				<>
					<TextControl
						label={__('Organization name', 'eightshift-seo')}
						help={__('Leave empty to fall back to the site title.', 'eightshift-seo')}
						value={rep.name ?? ''}
						onChange={(val) => setRep({ name: val })}
						__nextHasNoMarginBottom
					/>

					<AdminImagePicker
						label={__('Organization logo', 'eightshift-seo')}
						help={__('Used as the Organization logo in structured data. Minimum 112×112 px.', 'eightshift-seo')}
						value={rep.logo ?? 0}
						onChange={(id) => setRep({ logo: id })}
					/>
				</>
			)}

			<h3>{__('Social profiles (sameAs)', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Full URLs to your official profiles. These appear in the sameAs array, helping search engines link your site to your social presence.', 'eightshift-seo')}
			</p>

			{KNOWN_SOCIALS.map((s) => (
				<TextControl
					key={s.key}
					label={s.label}
					value={social[s.key] ?? ''}
					onChange={(val) => setSocial(s.key, val)}
					placeholder="https://…"
					__nextHasNoMarginBottom
				/>
			))}

			<h3>{__('Other profiles', 'eightshift-seo')}</h3>
			{otherUrls.map((url, idx) => (
				<div key={idx} className="es-seo-media-field" style={{ marginBottom: 8 }}>
					<div style={{ flex: 1 }}>
						<TextControl
							label={__('URL', 'eightshift-seo')}
							hideLabelFromVision
							value={url}
							onChange={(val) => setOtherAt(idx, val)}
							placeholder="https://…"
							__nextHasNoMarginBottom
						/>
					</div>
					<Button variant="link" isDestructive onClick={() => removeOther(idx)}>
						{__('Remove', 'eightshift-seo')}
					</Button>
				</div>
			))}
			<Button variant="secondary" onClick={addOther}>
				{__('Add profile URL', 'eightshift-seo')}
			</Button>
		</div>
	);
};
