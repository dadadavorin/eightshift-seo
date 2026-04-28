/**
 * GEO panel — TL;DR, citations, FAQ, HowTo, and speakable selectors.
 *
 * Citable Content (Phase 7). All data is stored as post meta via the
 * getMeta / setMetaKey helpers passed from SeoPanelPlugin.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, Button } from '@wordpress/components';

// ─── Helpers ────────────────────────────────────────────────────────────────

/** Normalise any falsy / non-array meta value to an empty array. */
const toArray = (v) => (Array.isArray(v) ? v : []);

/** Count words in a string (split on whitespace, filter empty). */
const wordCount = (str) => (str ? str.trim().split(/\s+/).filter(Boolean).length : 0);

// ─── Word-count colour helper ────────────────────────────────────────────────
const wordCountColor = (n) => {
	if (n >= 40 && n <= 80) return '#00a32a';   // green
	if (n < 40)             return '#f0b849';   // orange
	return '#d63638';                           // red  (>80)
};

// ─── Sub-components ─────────────────────────────────────────────────────────

/**
 * A small row separator used between repeater items.
 */
const ItemDivider = () => (
	<hr style={{ margin: '8px 0', borderColor: '#e0e0e0' }} />
);

// ─── GeoPanel ───────────────────────────────────────────────────────────────

export const GeoPanel = ({ getMeta, setMetaKey }) => {
	// ── Expand/collapse state ────────────────────────────────────────────────
	const [showCitations,  setShowCitations]  = useState(false);
	const [showFaq,        setShowFaq]        = useState(false);
	const [showHowTo,      setShowHowTo]      = useState(false);
	const [showSpeakable,  setShowSpeakable]  = useState(false);

	// ── TL;DR ────────────────────────────────────────────────────────────────
	const tldr     = getMeta('tldr') || '';
	const tldrWords = wordCount(tldr);

	// ── Citations ────────────────────────────────────────────────────────────
	const citations = toArray(getMeta('citations'));

	const updateCitation = (index, field, value) => {
		const next = citations.map((item, i) =>
			i === index ? { ...item, [field]: value } : item
		);
		setMetaKey('citations', next);
	};

	const addCitation = () => {
		setMetaKey('citations', [
			...citations,
			{ label: '', url: '', publisher: '', datePublished: '' },
		]);
	};

	const removeCitation = (index) => {
		setMetaKey('citations', citations.filter((_, i) => i !== index));
	};

	// ── FAQ ──────────────────────────────────────────────────────────────────
	const faq = toArray(getMeta('faq'));

	const updateFaq = (index, field, value) => {
		const next = faq.map((item, i) =>
			i === index ? { ...item, [field]: value } : item
		);
		setMetaKey('faq', next);
	};

	const addFaq = () => {
		setMetaKey('faq', [...faq, { question: '', answer: '' }]);
	};

	const removeFaq = (index) => {
		setMetaKey('faq', faq.filter((_, i) => i !== index));
	};

	// ── HowTo ────────────────────────────────────────────────────────────────
	const howtoRaw  = getMeta('howto') || '{}';
	let howto       = {};
	try { howto = JSON.parse(howtoRaw); } catch { howto = {}; }

	const setHowTo = (data) => setMetaKey('howto', JSON.stringify(data));

	const updateHowToField = (field, value) => setHowTo({ ...howto, [field]: value });

	const howtoSteps = Array.isArray(howto.steps) ? howto.steps : [];

	const updateStep = (index, field, value) => {
		const next = howtoSteps.map((s, i) =>
			i === index ? { ...s, [field]: value } : s
		);
		setHowTo({ ...howto, steps: next });
	};

	const addStep = () => {
		setHowTo({ ...howto, steps: [...howtoSteps, { name: '', text: '', imageUrl: '' }] });
	};

	const removeStep = (index) => {
		setHowTo({ ...howto, steps: howtoSteps.filter((_, i) => i !== index) });
	};

	// ── Speakable ────────────────────────────────────────────────────────────
	const speakableRaw  = toArray(getMeta('speakableSelectors'));
	const speakableText = speakableRaw.join('\n');

	const setSpeakable = (text) => {
		const arr = text.split('\n').map((s) => s.trim()).filter(Boolean);
		setMetaKey('speakableSelectors', arr);
	};

	// ── Render ───────────────────────────────────────────────────────────────
	return (
		<div className="es-seo-geo-panel">

			{/* ── TL;DR (always visible) ──────────────────────────────────── */}
			<div style={{ marginBottom: 16 }}>
				<TextareaControl
					label={__('TL;DR / Direct Answer', 'eightshift-seo')}
					help={__('40–80 words recommended. Used in llms.txt, .md variant, and speakable annotations.', 'eightshift-seo')}
					value={tldr}
					onChange={(val) => setMetaKey('tldr', val)}
					rows={3}
					__nextHasNoMarginBottom
				/>
				<p
					style={{
						margin: '4px 0 0',
						fontSize: 11,
						color: wordCountColor(tldrWords),
					}}
				>
					{`(${tldrWords} ${__('words', 'eightshift-seo')})`}
				</p>
			</div>

			<hr />

			{/* ── Citations ───────────────────────────────────────────────── */}
			<Button
				variant="link"
				onClick={() => setShowCitations((v) => !v)}
				style={{ marginBottom: 8 }}
			>
				{showCitations
					? __('Hide citations ▲', 'eightshift-seo')
					: __('Show citations ▼', 'eightshift-seo')}
			</Button>

			{showCitations && (
				<div style={{ marginTop: 4 }}>
					{citations.length === 0 && (
						<p style={{ fontSize: 12, color: '#757575', margin: '0 0 8px' }}>
							{__('No citations yet. Click "Add citation" to begin.', 'eightshift-seo')}
						</p>
					)}

					{citations.map((item, index) => (
						<div key={index}>
							<div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
								<TextControl
									label={__('Label (required)', 'eightshift-seo')}
									value={item.label || ''}
									onChange={(val) => updateCitation(index, 'label', val)}
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={__('URL', 'eightshift-seo')}
									type="url"
									value={item.url || ''}
									onChange={(val) => updateCitation(index, 'url', val)}
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={__('Publisher (optional)', 'eightshift-seo')}
									value={item.publisher || ''}
									onChange={(val) => updateCitation(index, 'publisher', val)}
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={__('Date (optional)', 'eightshift-seo')}
									type="date"
									value={item.datePublished || ''}
									onChange={(val) => updateCitation(index, 'datePublished', val)}
									__nextHasNoMarginBottom
								/>
								<Button
									variant="link"
									isDestructive
									size="small"
									onClick={() => removeCitation(index)}
								>
									{__('Remove', 'eightshift-seo')}
								</Button>
							</div>
							{index < citations.length - 1 && <ItemDivider />}
						</div>
					))}

					<Button
						variant="secondary"
						size="small"
						onClick={addCitation}
						style={{ marginTop: 8 }}
					>
						{__('Add citation', 'eightshift-seo')}
					</Button>
				</div>
			)}

			<hr />

			{/* ── FAQ ─────────────────────────────────────────────────────── */}
			<Button
				variant="link"
				onClick={() => setShowFaq((v) => !v)}
				style={{ marginBottom: 8 }}
			>
				{showFaq
					? __('Hide FAQ ▲', 'eightshift-seo')
					: __('Show FAQ ▼', 'eightshift-seo')}
			</Button>

			{showFaq && (
				<div style={{ marginTop: 4 }}>
					{faq.length === 0 && (
						<p style={{ fontSize: 12, color: '#757575', margin: '0 0 8px' }}>
							{__('No FAQ items yet. Click "Add Q&A" to begin.', 'eightshift-seo')}
						</p>
					)}

					{faq.map((item, index) => (
						<div key={index}>
							<div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
								<TextControl
									label={__('Question', 'eightshift-seo')}
									value={item.question || ''}
									onChange={(val) => updateFaq(index, 'question', val)}
									__nextHasNoMarginBottom
								/>
								<TextareaControl
									label={__('Answer', 'eightshift-seo')}
									value={item.answer || ''}
									onChange={(val) => updateFaq(index, 'answer', val)}
									rows={3}
									__nextHasNoMarginBottom
								/>
								<Button
									variant="link"
									isDestructive
									size="small"
									onClick={() => removeFaq(index)}
								>
									{__('Remove', 'eightshift-seo')}
								</Button>
							</div>
							{index < faq.length - 1 && <ItemDivider />}
						</div>
					))}

					<Button
						variant="secondary"
						size="small"
						onClick={addFaq}
						style={{ marginTop: 8 }}
					>
						{__('Add Q&A', 'eightshift-seo')}
					</Button>
				</div>
			)}

			<hr />

			{/* ── HowTo ───────────────────────────────────────────────────── */}
			<Button
				variant="link"
				onClick={() => setShowHowTo((v) => !v)}
				style={{ marginBottom: 8 }}
			>
				{showHowTo
					? __('Hide HowTo ▲', 'eightshift-seo')
					: __('Show HowTo ▼', 'eightshift-seo')}
			</Button>

			{showHowTo && (
				<div style={{ marginTop: 4, display: 'flex', flexDirection: 'column', gap: 8 }}>
					<TextControl
						label={__('HowTo name/title', 'eightshift-seo')}
						value={howto.name || ''}
						onChange={(val) => updateHowToField('name', val)}
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={__('Description (optional)', 'eightshift-seo')}
						value={howto.description || ''}
						onChange={(val) => updateHowToField('description', val)}
						rows={3}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={__('Total time', 'eightshift-seo')}
						help={__('ISO 8601 duration, e.g. PT30M', 'eightshift-seo')}
						placeholder="PT30M"
						value={howto.totalTime || ''}
						onChange={(val) => updateHowToField('totalTime', val)}
						__nextHasNoMarginBottom
					/>

					<p style={{ margin: '4px 0 0', fontWeight: 600, fontSize: 12 }}>
						{__('Steps', 'eightshift-seo')}
					</p>

					{howtoSteps.length === 0 && (
						<p style={{ fontSize: 12, color: '#757575', margin: 0 }}>
							{__('No steps yet. Click "Add step" to begin.', 'eightshift-seo')}
						</p>
					)}

					{howtoSteps.map((step, index) => (
						<div key={index}>
							<div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
								<TextControl
									label={`${__('Step', 'eightshift-seo')} ${index + 1} — ${__('Name', 'eightshift-seo')}`}
									value={step.name || ''}
									onChange={(val) => updateStep(index, 'name', val)}
									__nextHasNoMarginBottom
								/>
								<TextareaControl
									label={__('Text', 'eightshift-seo')}
									value={step.text || ''}
									onChange={(val) => updateStep(index, 'text', val)}
									rows={2}
									__nextHasNoMarginBottom
								/>
								<TextControl
									label={__('Image URL (optional)', 'eightshift-seo')}
									value={step.imageUrl || ''}
									onChange={(val) => updateStep(index, 'imageUrl', val)}
									__nextHasNoMarginBottom
								/>
								<Button
									variant="link"
									isDestructive
									size="small"
									onClick={() => removeStep(index)}
								>
									{__('Remove', 'eightshift-seo')}
								</Button>
							</div>
							{index < howtoSteps.length - 1 && <ItemDivider />}
						</div>
					))}

					<Button
						variant="secondary"
						size="small"
						onClick={addStep}
						style={{ marginTop: 4 }}
					>
						{__('Add step', 'eightshift-seo')}
					</Button>
				</div>
			)}

			<hr />

			{/* ── Speakable ───────────────────────────────────────────────── */}
			<Button
				variant="link"
				onClick={() => setShowSpeakable((v) => !v)}
				style={{ marginBottom: 8 }}
			>
				{showSpeakable
					? __('Hide speakable selectors ▲', 'eightshift-seo')
					: __('Show speakable selectors ▼', 'eightshift-seo')}
			</Button>

			{showSpeakable && (
				<div style={{ marginTop: 4 }}>
					<TextareaControl
						label={__('Speakable CSS selectors', 'eightshift-seo')}
						help={__('CSS selectors for AI voice readout. One per line. Leave empty to auto-detect.', 'eightshift-seo')}
						value={speakableText}
						onChange={setSpeakable}
						rows={4}
						__nextHasNoMarginBottom
					/>
				</div>
			)}
		</div>
	);
};
