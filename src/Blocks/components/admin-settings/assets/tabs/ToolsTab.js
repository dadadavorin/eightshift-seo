/**
 * Tools tab — settings import/export and IndexNow management.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, TextControl, ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const { optionName, homeUrl } = window.esSeoLocalization ?? {};

export const ToolsTab = ({ settings, onChange }) => {
	const [importError, setImportError]   = useState(null);
	const [importSuccess, setImportSuccess] = useState(false);

	const indexNow   = settings.indexNow ?? { enabled: false, key: '' };

	const setIndexNow = (key, value) =>
		onChange({
			...settings,
			indexNow: { ...indexNow, [key]: value },
		});

	// Export settings as JSON file download.
	const handleExport = () => {
		const json = JSON.stringify(settings, null, 2);
		const blob = new Blob([json], { type: 'application/json' });
		const url  = URL.createObjectURL(blob);
		const date = new Date().toISOString().slice(0, 10);
		const a    = document.createElement('a');
		a.href     = url;
		a.download = `es-seo-settings-${date}.json`;
		a.click();
		URL.revokeObjectURL(url);
	};

	// Import settings from JSON file.
	const handleImport = (e) => {
		const file = e.target.files?.[0];
		if (!file) return;

		setImportError(null);
		setImportSuccess(false);

		const reader = new FileReader();
		reader.onload = (evt) => {
			try {
				const parsed = JSON.parse(evt.target.result);
				if (typeof parsed !== 'object' || Array.isArray(parsed)) {
					throw new Error(__('Invalid JSON: expected an object.', 'eightshift-seo'));
				}
				onChange(parsed);
				setImportSuccess(true);
			} catch (err) {
				setImportError(err.message ?? __('Could not parse the file.', 'eightshift-seo'));
			}
		};
		reader.readAsText(file);
		// Reset so the same file can be re-imported after fixes.
		e.target.value = '';
	};

	// Regenerate the IndexNow key.
	const handleRegenerateKey = () => {
		if (!window.confirm(__('Regenerating the key will invalidate the current one. Search engines will need to re-verify. Continue?', 'eightshift-seo'))) {
			return;
		}

		apiFetch({
			path: '/es-seo/v1/indexnow-regenerate',
			method: 'POST',
		})
			.then((data) => {
				if (data?.key) {
					setIndexNow('key', data.key);
				}
			})
			.catch(() => {/* silently handled by WP notice */});
	};

	const keyFileUrl = indexNow.key ? `${homeUrl}${indexNow.key}.txt` : '';

	return (
		<div className="es-seo-tab">
			<h2>{__('Tools', 'eightshift-seo')}</h2>

			<h3>{__('Settings export / import', 'eightshift-seo')}</h3>
			<p className="description">
				{__('Export your SEO settings to a JSON file or import a previously exported file.', 'eightshift-seo')}
			</p>

			{importError && (
				<Notice status="error" isDismissible onRemove={() => setImportError(null)}>
					{importError}
				</Notice>
			)}
			{importSuccess && (
				<Notice status="success" isDismissible onRemove={() => setImportSuccess(false)}>
					{__('Settings imported. Remember to save to persist the changes.', 'eightshift-seo')}
				</Notice>
			)}

			<div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16 }}>
				<Button variant="secondary" onClick={handleExport}>
					{__('Export settings', 'eightshift-seo')}
				</Button>

				<label className="button button-secondary" style={{ cursor: 'pointer' }}>
					{__('Import settings…', 'eightshift-seo')}
					<input
						type="file"
						accept=".json,application/json"
						style={{ display: 'none' }}
						onChange={handleImport}
					/>
				</label>
			</div>

			<hr />

			<h3>{__('IndexNow', 'eightshift-seo')}</h3>
			<p className="description">
				{__('IndexNow notifies Bing and Yandex instantly when you publish or update content, so search engines can re-crawl it faster.', 'eightshift-seo')}
			</p>

			<ToggleControl
				label={__('Enable IndexNow submissions', 'eightshift-seo')}
				checked={!!indexNow.enabled}
				onChange={(val) => setIndexNow('enabled', val)}
				__nextHasNoMarginBottom
			/>

			{indexNow.enabled && (
				<>
					<TextControl
						label={__('API key', 'eightshift-seo')}
						help={
							keyFileUrl
								? `${__('Key file served at:', 'eightshift-seo')} ${keyFileUrl}`
								: __('Key will be auto-generated on save.', 'eightshift-seo')
						}
						value={indexNow.key || ''}
						readOnly
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						isDestructive
						style={{ marginTop: 8 }}
						onClick={handleRegenerateKey}
					>
						{__('Regenerate key', 'eightshift-seo')}
					</Button>
				</>
			)}
		</div>
	);
};
