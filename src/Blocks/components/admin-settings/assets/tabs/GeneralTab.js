/**
 * General settings tab — separator, default OG image, Twitter handle.
 */

import { __ } from '@wordpress/i18n';
import { TextControl, BaseControl } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

const { canUpload } = window.esSeoLocalization ?? {};

export const GeneralTab = ({ settings, onChange }) => {
	const set = (key, value) => onChange({ ...settings, [key]: value });

	const defaultOgImage = settings.defaultOgImage ?? 0;

	return (
		<div className="es-seo-tab">
			<h2>{__('General', 'eightshift-seo')}</h2>

			<TextControl
				label={__('Title separator', 'eightshift-seo')}
				help={__('Used between title parts, e.g. Post Title – Site Name', 'eightshift-seo')}
				value={settings.separator ?? '–'}
				onChange={(val) => set('separator', val)}
			/>

			<TextControl
				label={__('Twitter / X handle', 'eightshift-seo')}
				help={__('Without the @ sign, e.g. eightshift', 'eightshift-seo')}
				value={settings.twitterHandle ?? ''}
				onChange={(val) => set('twitterHandle', val)}
			/>

			{canUpload && (
				<BaseControl label={__('Default OG image', 'eightshift-seo')} __nextHasNoMarginBottom>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={(media) => set('defaultOgImage', media.id)}
							allowedTypes={['image']}
							value={defaultOgImage}
							render={({ open }) => (
								<div className="es-seo-media-field">
									{defaultOgImage > 0 ? (
										<>
											<button
												className="button"
												onClick={open}
												type="button"
											>
												{__('Change image', 'eightshift-seo')}
											</button>
											<button
												className="button button-link-delete"
												onClick={() => set('defaultOgImage', 0)}
												type="button"
											>
												{__('Remove', 'eightshift-seo')}
											</button>
										</>
									) : (
										<button
											className="button"
											onClick={open}
											type="button"
										>
											{__('Select image', 'eightshift-seo')}
										</button>
									)}
								</div>
							)}
						/>
					</MediaUploadCheck>
				</BaseControl>
			)}
		</div>
	);
};
