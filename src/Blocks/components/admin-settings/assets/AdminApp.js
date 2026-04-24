/**
 * Eightshift SEO — Admin application root.
 *
 * Reads/writes the single 'es-seo-settings' option (JSON string) via
 * apiFetch to /wp/v2/settings. No custom REST routes.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import { GeneralTab } from './tabs/GeneralTab';
import { DefaultsTab } from './tabs/DefaultsTab';
import { SitemapTab } from './tabs/SitemapTab';
import { SocialTab } from './tabs/SocialTab';
import { AdvancedTab } from './tabs/AdvancedTab';
import { SiteRepresentationTab } from './tabs/SiteRepresentationTab';
import { ToolsTab } from './tabs/ToolsTab';
import { HealthTab } from './tabs/HealthTab';

const { optionName } = window.esSeoLocalization ?? {};

const TABS = [
	{ id: 'general',  label: __('General',  'eightshift-seo'), Component: GeneralTab },
	{ id: 'site',     label: __('Site',     'eightshift-seo'), Component: SiteRepresentationTab },
	{ id: 'defaults', label: __('Defaults', 'eightshift-seo'), Component: DefaultsTab },
	{ id: 'sitemap',  label: __('Sitemap',  'eightshift-seo'), Component: SitemapTab },
	{ id: 'social',   label: __('Social',   'eightshift-seo'), Component: SocialTab },
	{ id: 'advanced', label: __('Advanced', 'eightshift-seo'), Component: AdvancedTab },
	{ id: 'tools',    label: __('Tools',    'eightshift-seo'), Component: ToolsTab },
	{ id: 'health',   label: __('Health',   'eightshift-seo'), Component: HealthTab },
];

export const AdminApp = () => {
	const [settings, setSettings] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [activeTab, setActiveTab] = useState('general');

	// Load settings on mount.
	useEffect(() => {
		apiFetch({ path: '/wp/v2/settings' })
			.then((data) => {
				const raw = data[optionName];
				try {
					setSettings(typeof raw === 'string' ? JSON.parse(raw) : raw ?? {});
				} catch {
					setSettings({});
				}
			})
			.catch(() => setSettings({}));
	}, []);

	const save = useCallback(() => {
		setIsSaving(true);
		setNotice(null);

		apiFetch({
			path: '/wp/v2/settings',
			method: 'POST',
			data: { [optionName]: JSON.stringify(settings) },
		})
			.then(() => {
				setNotice({ type: 'success', message: __('Settings saved.', 'eightshift-seo') });
			})
			.catch(() => {
				setNotice({ type: 'error', message: __('Failed to save settings.', 'eightshift-seo') });
			})
			.finally(() => setIsSaving(false));
	}, [settings]);

	if (settings === null) {
		return (
			<div className="es-seo-admin">
				<h1 className="es-seo-admin__title">{__('Eightshift SEO', 'eightshift-seo')}</h1>
				<Spinner />
			</div>
		);
	}

	const activeTabDef = TABS.find((t) => t.id === activeTab);

	return (
		<div className="es-seo-admin">
			<h1 className="es-seo-admin__title">{__('Eightshift SEO', 'eightshift-seo')}</h1>
			<p className="es-seo-admin__subtitle">
				{__('Manage titles, meta descriptions, Open Graph, sitemaps and more.', 'eightshift-seo')}
			</p>

			{notice && (
				<Notice
					status={notice.type}
					isDismissible
					onRemove={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			<nav className="nav-tab-wrapper">
				{TABS.map((tab) => (
					<button
						key={tab.id}
						className={`nav-tab${activeTab === tab.id ? ' nav-tab-active' : ''}`}
						onClick={() => setActiveTab(tab.id)}
						type="button"
					>
						{tab.label}
					</button>
				))}
			</nav>

			<div className="es-seo-admin__panel">
				{activeTabDef && (
					<activeTabDef.Component settings={settings} onChange={setSettings} />
				)}
			</div>

			<div className="es-seo-admin__footer">
				<Button
					variant="primary"
					onClick={save}
					isBusy={isSaving}
					disabled={isSaving}
				>
					{isSaving ? __('Saving…', 'eightshift-seo') : __('Save settings', 'eightshift-seo')}
				</Button>
			</div>
		</div>
	);
};
