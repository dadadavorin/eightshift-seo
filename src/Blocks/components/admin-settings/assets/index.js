/**
 * Eightshift SEO — Admin settings page entry point.
 *
 * Mounts the React admin application on the DOM element rendered by
 * admin-settings.php. Reads/writes settings via the native WordPress
 * REST API (/wp/v2/settings) — zero custom endpoints.
 */

import { createRoot } from '@wordpress/element';
import { AdminApp } from './AdminApp';
import './admin.css';

const rootEl = document.getElementById('es-seo-admin-root');

if (rootEl) {
	createRoot(rootEl).render(<AdminApp />);
}
