/**
 * Health tab — displays SEO configuration and content health checks.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const STATUS_ICONS = {
	ok:   { icon: '✓', color: '#00a32a' },
	warn: { icon: '⚠', color: '#dba617' },
	fail: { icon: '✗', color: '#d63638' },
};

const StatusIcon = ({ status }) => {
	const { icon, color } = STATUS_ICONS[status] ?? STATUS_ICONS.warn;
	return <span style={{ color, fontWeight: 700, marginRight: 6 }} aria-hidden="true">{icon}</span>;
};

export const HealthTab = () => {
	const [checks, setChecks]     = useState(null);
	const [loading, setLoading]   = useState(true);
	const [error, setError]       = useState(null);

	const loadChecks = (flush = false) => {
		setLoading(true);
		setError(null);

		const path = flush ? '/es-seo/v1/health?flush=1' : '/es-seo/v1/health';

		apiFetch({ path })
			.then((data) => {
				setChecks(Array.isArray(data?.checks) ? data.checks : []);
			})
			.catch(() => {
				setError(__('Could not load health checks. Make sure you are logged in as an administrator.', 'eightshift-seo'));
			})
			.finally(() => setLoading(false));
	};

	useEffect(() => { loadChecks(); }, []);

	const grouped = {
		fail: (checks ?? []).filter((c) => c.status === 'fail'),
		warn: (checks ?? []).filter((c) => c.status === 'warn'),
		ok:   (checks ?? []).filter((c) => c.status === 'ok'),
	};

	const renderGroup = (status, label) => {
		const items = grouped[status];
		if (!items || items.length === 0) return null;

		return (
			<div style={{ marginBottom: 20 }}>
				<h3 style={{ marginBottom: 8 }}>{label}</h3>
				{items.map((check) => (
					<div
						key={check.id}
						style={{
							display: 'flex',
							alignItems: 'flex-start',
							gap: 8,
							padding: '10px 12px',
							marginBottom: 6,
							background: '#fff',
							border: '1px solid #dcdcde',
							borderRadius: 4,
						}}
					>
						<StatusIcon status={check.status} />
						<div style={{ flex: 1 }}>
							<strong style={{ display: 'block', marginBottom: 2 }}>{check.label}</strong>
							<span style={{ color: '#646970', fontSize: 13 }}>{check.message}</span>
						</div>
						{check.actionUrl && (
							<a
								href={check.actionUrl}
								className="button button-small"
								style={{ whiteSpace: 'nowrap', flexShrink: 0 }}
							>
								{__('Fix', 'eightshift-seo')}
							</a>
						)}
					</div>
				))}
			</div>
		);
	};

	return (
		<div className="es-seo-tab">
			<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
				<h2 style={{ margin: 0 }}>{__('SEO Health', 'eightshift-seo')}</h2>
				<Button
					variant="secondary"
					onClick={() => loadChecks(true)}
					isBusy={loading}
					disabled={loading}
				>
					{loading ? __('Checking…', 'eightshift-seo') : __('Refresh', 'eightshift-seo')}
				</Button>
			</div>

			<p className="description" style={{ marginTop: 8 }}>
				{__('Results are cached for one hour. Click Refresh to re-run all checks immediately.', 'eightshift-seo')}
			</p>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{loading && !checks && <Spinner />}

			{checks && (
				<>
					{renderGroup('fail', __('Issues to fix', 'eightshift-seo'))}
					{renderGroup('warn', __('Warnings', 'eightshift-seo'))}
					{renderGroup('ok',   __('Passing checks', 'eightshift-seo'))}
					{checks.length === 0 && (
						<Notice status="info" isDismissible={false}>
							{__('No checks returned. This may indicate a configuration issue.', 'eightshift-seo')}
						</Notice>
					)}
				</>
			)}
		</div>
	);
};
