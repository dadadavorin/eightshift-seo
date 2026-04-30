<?php

/**
 * The file that renders the AI bot traffic dashboard widget.
 *
 * @package EightshiftSeo\BotInsights
 */

declare(strict_types=1);

namespace EightshiftSeo\BotInsights;

use EightshiftSeo\Config\AiBotRegistry;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * BotInsightsDashboard — small WP dashboard widget that summarises AI bot
 * crawl activity for the last 30 days.
 *
 * Renders a stacked bar chart with inline SVG (no charting library) and a
 * compact table of top bots over 7 / 30 day windows. Hidden entirely when
 * the feature is disabled in settings.
 */
class BotInsightsDashboard implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_dashboard_setup', [$this, 'addWidget']);
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @return void
	 */
	public function addWidget(): void
	{
		if (!Options::getOptionChecked(['botInsights', 'enabled'])) {
			return;
		}

		$cap = Options::getCap('manage') ?: 'manage_options';
		if (!\current_user_can($cap)) {
			return;
		}

		\wp_add_dashboard_widget(
			'es_seo_bot_insights',
			\__('AI bot traffic (30 days)', 'eightshift-seo'),
			[$this, 'render']
		);
	}

	/**
	 * Render the widget body.
	 *
	 * @return void
	 */
	public function render(): void
	{
		$counters = (new BotCounters())->getStats(30);
		$registry = AiBotRegistry::getBots();
		$registry = \apply_filters(Options::getFilter('aiBotRegistry'), $registry);

		$totals  = $counters['totals'];
		$daily   = $counters['daily'];
		$total   = \array_sum($totals);

		$settingsUrl = \admin_url('admin.php?page=es-seo-settings#bot-insights');

		if ($total === 0) {
			echo '<p>' . \esc_html(\__('No AI bot hits recorded yet. Once tracking is enabled, daily counters appear here.', 'eightshift-seo')) . '</p>';
			echo '<p><a href="' . \esc_url($settingsUrl) . '">' . \esc_html(\__('Bot insights settings →', 'eightshift-seo')) . '</a></p>';
			return;
		}

		$this->renderChart($daily, $registry);
		$this->renderTopTable($totals, $registry);

		echo '<p style="margin:12px 0 0">';
		echo '<a href="' . \esc_url($settingsUrl) . '">' . \esc_html(\__('Bot insights settings →', 'eightshift-seo')) . '</a>';
		echo '</p>';
	}

	/**
	 * Render a tiny inline-SVG stacked bar chart for the last 30 days.
	 *
	 * @param array<int, array{day: string, bot_id: string, hits: int}> $daily    Daily rows.
	 * @param array<string, array{name: string, vendor: string, category: string}> $registry Bot registry.
	 *
	 * @return void
	 */
	private function renderChart(array $daily, array $registry): void
	{
		// Build a day → vendor → hits matrix for the last 30 days.
		$days = [];
		for ($i = 29; $i >= 0; $i--) {
			$days[\gmdate('Y-m-d', \strtotime("-{$i} days") ?: \time())] = [];
		}

		foreach ($daily as $row) {
			$day    = $row['day'];
			$botId  = $row['bot_id'];
			$vendor = (string) ($registry[$botId]['vendor'] ?? \__('Other', 'eightshift-seo'));

			if (!isset($days[$day])) {
				continue;
			}

			$days[$day][$vendor] = ($days[$day][$vendor] ?? 0) + (int) $row['hits'];
		}

		$max = 0;
		foreach ($days as $vendors) {
			$sum = \array_sum($vendors);
			if ($sum > $max) {
				$max = $sum;
			}
		}

		if ($max === 0) {
			$max = 1;
		}

		$width    = 600;
		$height   = 120;
		$barGap   = 1;
		$barWidth = ($width / count($days)) - $barGap;
		$colors   = $this->vendorColors();

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" style="width:100%;height:120px;display:block;margin:8px 0;background:#f6f7f7;border-radius:4px">';

		$x = 0;
		foreach ($days as $day => $vendors) {
			$y     = $height;
			$total = \array_sum($vendors);

			\arsort($vendors);

			foreach ($vendors as $vendor => $hits) {
				$barH = ($hits / $max) * ($height - 8);
				$y   -= $barH;

				$color = $colors[$vendor] ?? '#9ca3af';
				$title = \sprintf(
					/* translators: 1: ISO date, 2: vendor, 3: number of hits */
					\__('%1$s — %2$s: %3$d hits', 'eightshift-seo'),
					$day,
					$vendor,
					$hits
				);

				$svg .= \sprintf(
					'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s"><title>%s</title></rect>',
					$x,
					$y,
					$barWidth,
					$barH,
					\esc_attr($color),
					\esc_html($title)
				);
			}

			if ($total === 0) {
				$svg .= \sprintf(
					'<rect x="%.2f" y="%.2f" width="%.2f" height="2" fill="#e5e7eb"><title>%s: 0</title></rect>',
					$x,
					$height - 2,
					$barWidth,
					\esc_html($day)
				);
			}

			$x += $barWidth + $barGap;
		}

		$svg .= '</svg>';

		// SVG is built locally with escaped attribute / text nodes.
		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Legend.
		echo '<div style="font-size:11px;color:#646970;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 8px">';
		foreach ($colors as $vendor => $color) {
			echo '<span style="display:inline-flex;align-items:center;gap:4px">';
			echo '<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' . \esc_attr($color) . '"></span>';
			echo \esc_html($vendor);
			echo '</span>';
		}
		echo '</div>';
	}

	/**
	 * Render the top-bots table for 7 and 30 day windows.
	 *
	 * @param array<string, int> $totals Totals keyed by bot id over 30 days.
	 * @param array<string, array{name: string, vendor: string, category: string}> $registry Bot registry.
	 *
	 * @return void
	 */
	private function renderTopTable(array $totals, array $registry): void
	{
		$cutoff7 = \gmdate('Y-m-d', \strtotime('-7 days') ?: \time());
		$daily7  = (new BotCounters())->getStats(7)['totals'] ?? [];

		$top = \array_slice($totals, 0, 8, true);
		if (empty($top)) {
			return;
		}

		echo '<table style="width:100%;border-collapse:collapse;font-size:12px">';
		echo '<thead><tr style="text-align:left;border-bottom:1px solid #f0f0f1">';
		echo '<th style="padding:4px 0">' . \esc_html(\__('Bot', 'eightshift-seo')) . '</th>';
		echo '<th style="padding:4px 0;text-align:right">' . \esc_html(\__('7d', 'eightshift-seo')) . '</th>';
		echo '<th style="padding:4px 0;text-align:right">' . \esc_html(\__('30d', 'eightshift-seo')) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($top as $botId => $hits30) {
			$name   = (string) ($registry[$botId]['name']   ?? $botId);
			$vendor = (string) ($registry[$botId]['vendor'] ?? '');
			$hits7  = (int) ($daily7[$botId] ?? 0);

			echo '<tr style="border-bottom:1px solid #f6f7f7">';
			echo '<td style="padding:4px 0"><strong>' . \esc_html($name) . '</strong>';
			if ($vendor !== '') {
				echo ' <span style="color:#646970">· ' . \esc_html($vendor) . '</span>';
			}
			echo '</td>';
			echo '<td style="padding:4px 0;text-align:right">' . \esc_html((string) $hits7) . '</td>';
			echo '<td style="padding:4px 0;text-align:right">' . \esc_html((string) $hits30) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Stable colour palette per vendor for the chart.
	 *
	 * @return array<string, string>
	 */
	private function vendorColors(): array
	{
		return [
			'OpenAI'       => '#10a37f',
			'Anthropic'    => '#c44d3a',
			'Google'       => '#4285f4',
			'Perplexity'   => '#20808d',
			'Apple'        => '#999999',
			'Meta'         => '#0866ff',
			'xAI'          => '#1d1d1d',
			'Common Crawl' => '#f59e0b',
			'ByteDance'    => '#fe2c55',
			'Mistral'      => '#fa520f',
			'Cohere'       => '#39594d',
			'DuckDuckGo'   => '#de5833',
			'You.com'      => '#5a4fcf',
			'AI2'          => '#7c3aed',
		];
	}
}
