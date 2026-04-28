<?php

/**
 * The file that powers the SEO health dashboard.
 *
 * @package EightshiftSeo\Health
 */

declare(strict_types=1);

namespace EightshiftSeo\Health;

use EightshiftSeo\Health\Checks\AiCrawlerPolicySetCheck;
use EightshiftSeo\Health\Checks\ArticleSchemaCoverageCheck;
use EightshiftSeo\Health\Checks\AttachmentPagesIndexableCheck;
use EightshiftSeo\Health\Checks\AuthorsHaveBioCheck;
use EightshiftSeo\Health\Checks\DefaultOgImageCheck;
use EightshiftSeo\Health\Checks\ExpiredUnavailableAfterCheck;
use EightshiftSeo\Health\Checks\HomepageTitleTemplateCheck;
use EightshiftSeo\Health\Checks\MissingMetaDescriptionCheck;
use EightshiftSeo\Health\Checks\SiteRepresentationCompleteCheck;
use EightshiftSeo\Health\Checks\SitemapReachableCheck;
use EightshiftSeo\Health\Checks\VerificationConfiguredCheck;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * HealthDashboard class — runs all SEO health checks, caches results in a
 * transient, renders a WP dashboard widget, and exposes a REST endpoint.
 *
 * Checks are cached for 1 hour (HOUR_IN_SECONDS). The REST endpoint at
 * /wp-json/es-seo/v1/health flushes the cache and re-runs all checks so
 * external monitoring tools can poll current status.
 */
class HealthDashboard implements ServiceInterface
{
	private const TRANSIENT_KEY = 'es_seo_health_status';
	private const REST_NAMESPACE = 'es-seo/v1';
	private const REST_ROUTE     = '/health';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
		\add_action('rest_api_init', [$this, 'registerRestRoute']);
	}

	/**
	 * Register the SEO health dashboard widget.
	 *
	 * @return void
	 */
	public function addDashboardWidget(): void
	{
		if (!\current_user_can('manage_options')) {
			return;
		}

		\wp_add_dashboard_widget(
			'es_seo_health',
			\__('SEO Health', 'eightshift-seo'),
			[$this, 'renderDashboardWidget']
		);
	}

	/**
	 * Render the compact dashboard widget.
	 *
	 * @return void
	 */
	public function renderDashboardWidget(): void
	{
		$results  = $this->getResults();
		$settingsUrl = \admin_url('admin.php?page=es-seo-settings');

		$icons = [
			'ok'   => '<span style="color:#00a32a">✓</span>',
			'warn' => '<span style="color:#dba617">⚠</span>',
			'fail' => '<span style="color:#d63638">✗</span>',
		];

		echo '<table style="width:100%;border-collapse:collapse">';

		foreach ($results as $result) {
			$icon    = $icons[$result['status']] ?? $icons['warn'];
			$message = \esc_html($result['message']);
			$label   = \esc_html($result['label']);
			echo "<tr style='border-bottom:1px solid #f0f0f1'>";
			echo "<td style='padding:6px 8px 6px 0;width:24px'>{$icon}</td>";
			echo "<td style='padding:6px 0'><strong>{$label}</strong><br><span style='color:#646970;font-size:12px'>{$message}</span></td>";
			echo "</tr>";
		}

		echo '</table>';
		echo '<p style="margin:12px 0 0"><a href="' . \esc_url($settingsUrl) . '#health">' . \esc_html(\__('View full SEO health report →', 'eightshift-seo')) . '</a></p>';
	}

	/**
	 * Register the REST endpoint for external monitoring.
	 *
	 * @return void
	 */
	public function registerRestRoute(): void
	{
		\register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [$this, 'handleRestRequest'],
				'permission_callback' => static fn() => \current_user_can('manage_options'),
			]
		);
	}

	/**
	 * Handle the REST request — flush cache and re-run checks.
	 *
	 * @return \WP_REST_Response
	 */
	public function handleRestRequest(): \WP_REST_Response
	{
		\delete_transient(self::TRANSIENT_KEY);
		$results = $this->getResults();
		return new \WP_REST_Response(['checks' => $results], 200);
	}

	/**
	 * Get check results, running them fresh or returning the cached set.
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, actionUrl: string}>
	 */
	public function getResults(): array
	{
		$cached = \get_transient(self::TRANSIENT_KEY);

		if (\is_array($cached)) {
			return $cached;
		}

		$checks = \apply_filters(Options::getFilter('healthChecks'), $this->buildChecks());

		$results = [];
		foreach ($checks as $check) {
			if (!$check instanceof HealthCheckInterface) {
				continue;
			}

			$result             = $check->run();
			$result['id']       = $check->getId();
			$result['label']    = $check->getLabel();
			$results[]          = $result;
		}

		\set_transient(self::TRANSIENT_KEY, $results, \HOUR_IN_SECONDS);

		return $results;
	}

	/**
	 * Instantiate all built-in health checks.
	 *
	 * @return array<HealthCheckInterface>
	 */
	private function buildChecks(): array
	{
		return [
			new HomepageTitleTemplateCheck(),
			new DefaultOgImageCheck(),
			new VerificationConfiguredCheck(),
			new SitemapReachableCheck(),
			new MissingMetaDescriptionCheck(),
			new AttachmentPagesIndexableCheck(),
			new ExpiredUnavailableAfterCheck(),
			// GEO checks (Phase 6).
			new AiCrawlerPolicySetCheck(),
			new AuthorsHaveBioCheck(),
			new SiteRepresentationCompleteCheck(),
			new ArticleSchemaCoverageCheck(),
		];
	}
}
