/**
 * es-seo/statistic — minimal authoring block for a standalone statistic.
 *
 * Save outputs semantic HTML; the parent post's ArticleSchema collects all
 * `es-seo/statistic` blocks server-side and contributes a `Claim` entry to
 * the @graph node.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { TextControl, PanelBody } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import metadata from './block.json';

const Edit = ({ attributes, setAttributes }) => {
	const { value, label, source, sourceUrl, datePublished } = attributes;
	const blockProps = useBlockProps({ className: 'es-seo-statistic-edit' });

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Source attribution', 'eightshift-seo')} initialOpen={true}>
					<TextControl
						label={__('Source name', 'eightshift-seo')}
						value={source}
						onChange={(val) => setAttributes({ source: val })}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={__('Source URL', 'eightshift-seo')}
						type="url"
						value={sourceUrl}
						onChange={(val) => setAttributes({ sourceUrl: val })}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={__('Date published', 'eightshift-seo')}
						type="date"
						value={datePublished}
						onChange={(val) => setAttributes({ datePublished: val })}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<figure {...blockProps}>
				<TextControl
					label={__('Value', 'eightshift-seo')}
					placeholder="42%"
					value={value}
					onChange={(val) => setAttributes({ value: val })}
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Label', 'eightshift-seo')}
					placeholder={__('What does this number describe?', 'eightshift-seo')}
					value={label}
					onChange={(val) => setAttributes({ label: val })}
					__nextHasNoMarginBottom
				/>
			</figure>
		</>
	);
};

const Save = ({ attributes }) => {
	const { value, label, source, sourceUrl } = attributes;
	const blockProps = useBlockProps.save({ className: 'es-seo-statistic' });

	return (
		<figure {...blockProps}>
			<strong className="es-seo-statistic__value">{value}</strong>
			<figcaption className="es-seo-statistic__caption">
				{label}
				{source && (
					<>
						{' '}
						<cite className="es-seo-statistic__source">
							{sourceUrl
								? <a href={sourceUrl} rel="noopener noreferrer">{source}</a>
								: source}
						</cite>
					</>
				)}
			</figcaption>
		</figure>
	);
};

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: Save,
});
