/**
 * es-seo/expert-quote — minimal authoring block for a pull quote with
 * structured author attribution.
 *
 * Save outputs semantic HTML; the parent post's ArticleSchema collects all
 * `es-seo/expert-quote` blocks server-side and contributes a `Quotation`
 * entry to the @graph node.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, PanelBody } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import metadata from './block.json';

const Edit = ({ attributes, setAttributes }) => {
	const { quote, author, authorTitle, authorUrl } = attributes;
	const blockProps = useBlockProps({ className: 'es-seo-quote-edit' });

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Author attribution', 'eightshift-seo')} initialOpen={true}>
					<TextControl
						label={__('Author URL', 'eightshift-seo')}
						type="url"
						value={authorUrl}
						onChange={(val) => setAttributes({ authorUrl: val })}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<figure {...blockProps}>
				<TextareaControl
					label={__('Quote', 'eightshift-seo')}
					value={quote}
					onChange={(val) => setAttributes({ quote: val })}
					rows={3}
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Author', 'eightshift-seo')}
					value={author}
					onChange={(val) => setAttributes({ author: val })}
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Author title / role', 'eightshift-seo')}
					value={authorTitle}
					onChange={(val) => setAttributes({ authorTitle: val })}
					__nextHasNoMarginBottom
				/>
			</figure>
		</>
	);
};

const Save = ({ attributes }) => {
	const { quote, author, authorTitle, authorUrl } = attributes;
	const blockProps = useBlockProps.save({ className: 'es-seo-quote' });

	return (
		<figure {...blockProps}>
			<blockquote cite={authorUrl || undefined}>{quote}</blockquote>
			{(author || authorTitle) && (
				<figcaption>
					{author}
					{author && authorTitle && ', '}
					{authorTitle}
				</figcaption>
			)}
		</figure>
	);
};

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: Save,
});
