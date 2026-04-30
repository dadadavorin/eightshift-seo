<?php

/**
 * The file that exposes AI-bot counter stats over REST.
 *
 * @package EightshiftSeo\BotInsights
 */

declare(strict_types=1);

namespace EightshiftSeo\BotInsights;

use EightshiftSeo\Config\AiBotRegistry;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * BotInsightsRoute — read-only REST endpoint that powers the dashboard widget.
 *
 * Capability-gated by `eightshift_seo_manage`. Returns aggregated daily counters
 * plus 7-day and 30-day totals per bot, and joins in vendor metadata from the
 * bot registry so the front-end does not have to duplicate that mapping.
 */
class BotInsightsRoute implements ServiceInterface
{
	private const REST_NAMESPACE = 'es-seo/v1';
	private const REST_ROUTE     = '/bot-insights';
	private const RESET_ROUTE    = '/bot-insights/reset';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('rest_api_init', [$this, 'registerRoute']);
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function registerRoute(): void
	{
		\register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [$this, 'handle'],
				'permission_callback' => [$this, 'canRead'],
				'args'                => [
					'days' => [
						'type'              => 'integer',
						'default'           => 30,
						'minimum'           => 1,
						'maximum'           => 365,
						'sanitize_callback' => static fn ($v) => (int) $v,
					],
				],
			]
		);

		\register_rest_route(
			self::REST_NAMESPACE,
			self::RESET_ROUTE,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [$this, 'handleReset'],
				'permission_callback' => [$this, 'canRead'],
			]
		);
	}

	/**
	 * Capability gate — same `manage` cap used across the plugin.
	 *
	 * @return bool
	 */
	public function canRead(): bool
	{
		$cap = Options::getCap('manage') ?: 'manage_options';
		return \current_user_can($cap);
	}

	/**
	 * Handle the read request.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle(WP_REST_Request $request): WP_REST_Response
	{
		$days   = (int) $request->get_param('days');
		$counts = (new BotCounters())->getStats($days);

		$registry = AiBotRegistry::getBots();
		$registry = \apply_filters(Options::getFilter('aiBotRegistry'), $registry);

		$bots = [];
		if (\is_array($registry)) {
			foreach ($registry as $slug => $info) {
				$bots[(string) $slug] = [
					'name'     => (string) ($info['name']     ?? $slug),
					'vendor'   => (string) ($info['vendor']   ?? ''),
					'category' => (string) ($info['category'] ?? ''),
				];
			}
		}

		// Compute 7-day totals from the daily series.
		$totals7 = [];
		$cutoff7 = \gmdate('Y-m-d', \strtotime('-7 days') ?: \time());
		foreach ($counts['daily'] as $row) {
			if (($row['day'] ?? '') >= $cutoff7) {
				$id = $row['bot_id'];
				$totals7[$id] = ($totals7[$id] ?? 0) + (int) $row['hits'];
			}
		}
		\arsort($totals7);

		return new WP_REST_Response([
			'enabled'     => (bool) Options::getOptionChecked(['botInsights', 'enabled']),
			'days'        => $counts['days'],
			'retention'   => (int) Options::getOption(['botInsights', 'retentionDays']),
			'samplingRate' => (float) Options::getOption(['botInsights', 'samplingRate']),
			'bots'        => $bots,
			'totals'      => $counts['totals'],
			'totals7'     => $totals7,
			'daily'       => $counts['daily'],
		], 200);
	}

	/**
	 * Handle the reset request — truncate the bot counters table.
	 *
	 * @return WP_REST_Response
	 */
	public function handleReset(): WP_REST_Response
	{
		(new BotCounters())->reset();
		return new WP_REST_Response(['reset' => true], 200);
	}
}
