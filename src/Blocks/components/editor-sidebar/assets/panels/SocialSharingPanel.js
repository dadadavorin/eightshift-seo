/**
 * Social Sharing panel — OG and Twitter Card fields with a social preview.
 */

import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

export const SocialSharingPanel = ({ getMeta, setMetaKey }) => {
	const ogTitle       = getMeta('ogTitle');
	const ogDescription = getMeta('ogDescription');
	const ogImage       = getMeta('ogImage');
	const twTitle       = getMeta('twitterTitle');
	const twDescription = getMeta('twitterDescription');
	const twImage       = getMeta('twitterImage');

	return (
		<div className="es-seo-social-panel">
			<h3>{__('Open Graph (Facebook/LinkedIn)', 'eightshift-seo')}</h3>

			<TextControl
				label={__('OG title', 'eightshift-seo')}
				help={__('Overrides SEO title for social sharing', 'eightshift-seo')}
				value={ogTitle}
				onChange={(val) => setMetaKey('ogTitle', val)}
				__nextHasNoMarginBottom
			/>

			<TextareaControl
				label={__('OG description', 'eightshift-seo')}
				value={ogDescription}
				onChange={(val) => setMetaKey('ogDescription', val)}
				rows={2}
				__nextHasNoMarginBottom
			/>

			<MediaUploadCheck>
				<MediaUpload
					onSelect={(media) => setMetaKey('ogImage', media.id)}
					allowedTypes={['image']}
					value={ogImage}
					render={({ open }) => (
						<div className="es-seo-media-field">
							<p className="components-base-control__label">{__('OG image', 'eightshift-seo')}</p>
							{ogImage ? (
								<>
									<button className="button" onClick={open} type="button">
										{__('Change OG image', 'eightshift-seo')}
									</button>
									<button
										className="button button-link-delete"
										onClick={() => setMetaKey('ogImage', 0)}
										type="button"
									>
										{__('Remove', 'eightshift-seo')}
									</button>
								</>
							) : (
								<button className="button" onClick={open} type="button">
									{__('Set OG image', 'eightshift-seo')}
								</button>
							)}
						</div>
					)}
				/>
			</MediaUploadCheck>

			<hr />

			<h3>{__('Twitter / X Card', 'eightshift-seo')}</h3>

			<TextControl
				label={__('Twitter title', 'eightshift-seo')}
				help={__('Leave empty to use OG title', 'eightshift-seo')}
				value={twTitle}
				onChange={(val) => setMetaKey('twitterTitle', val)}
				__nextHasNoMarginBottom
			/>

			<TextareaControl
				label={__('Twitter description', 'eightshift-seo')}
				value={twDescription}
				onChange={(val) => setMetaKey('twitterDescription', val)}
				rows={2}
				__nextHasNoMarginBottom
			/>

			<MediaUploadCheck>
				<MediaUpload
					onSelect={(media) => setMetaKey('twitterImage', media.id)}
					allowedTypes={['image']}
					value={twImage}
					render={({ open }) => (
						<div className="es-seo-media-field">
							<p className="components-base-control__label">{__('Twitter image', 'eightshift-seo')}</p>
							{twImage ? (
								<>
									<button className="button" onClick={open} type="button">
										{__('Change image', 'eightshift-seo')}
									</button>
									<button
										className="button button-link-delete"
										onClick={() => setMetaKey('twitterImage', 0)}
										type="button"
									>
										{__('Remove', 'eightshift-seo')}
									</button>
								</>
							) : (
								<button className="button" onClick={open} type="button">
									{__('Set Twitter image', 'eightshift-seo')}
								</button>
							)}
						</div>
					)}
				/>
			</MediaUploadCheck>
		</div>
	);
};
