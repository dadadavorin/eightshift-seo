/**
 * Advanced settings tab — per-taxonomy robots defaults.
 *
 * Lets editors configure default robots directives (noindex, nofollow) for
 * all taxonomy archive pages without touching individual terms.
 */

import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

const { taxonomies } = window.esSeoLocalization ?? {};

export const AdvancedTab = ({ settings, onChange }) => {
	const robotsDefaults = settings.robotsDefaults ?? { taxonomies: {} };
	const taxRobots      = robotsDefaults.taxonomies ?? {};

	const setTaxRobot = (taxSlug, key, value) => {
		onChange({
			...settings,
			robotsDefaults: {
				...robotsDefaults,
				taxonomies: {
					...taxRobots,
					[taxSlug]: {
						...(taxRobots[taxSlug] ?? {}),
						[key]: value,
					},
				},
			},
		});
	};

	if (!taxonomies || taxonomies.length === 0) {
		return (
			<div className="es-seo-tab">
				<h2>{__('Advanced', 'eightshift-seo')}</h2>
				<p>{__('No public taxonomies found.', 'eightshift-seo')}</p>
			</div>
		);
	}

	return (
		<div className="es-seo-tab">
			<h2>{__('Advanced', 'eightshift-seo')}</h2>

			<h3>{__('Per-taxonomy robots defaults', 'eightshift-seo')}</h3>
			<p className="description">
				{__(
					'Set default robots directives for all archive pages of each taxonomy. Per-term settings take precedence over these defaults.',
					'eightshift-seo'
				)}
			</p>

			{taxonomies.map((tax) => {
				const current = taxRobots[tax.slug] ?? {};

				return (
					<div key={tax.slug} style={{ marginBottom: '16px' }}>
						<h4 style={{ margin: '0 0 6px' }}>{tax.name}</h4>

						<CheckboxControl
							label={__('noindex — exclude archives from search engines', 'eightshift-seo')}
							checked={!!current.noindex}
							onChange={(val) => setTaxRobot(tax.slug, 'noindex', val)}
							__nextHasNoMarginBottom
						/>

						<CheckboxControl
							label={__('nofollow — do not follow links on archive pages', 'eightshift-seo')}
							checked={!!current.nofollow}
							onChange={(val) => setTaxRobot(tax.slug, 'nofollow', val)}
							__nextHasNoMarginBottom
						/>
					</div>
				);
			})}
		</div>
	);
};
