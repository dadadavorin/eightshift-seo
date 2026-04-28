<?php

/**
 * The file that handles AI crawler robots.txt governance.
 *
 * @package EightshiftSeo\Sitemap
 */

declare(strict_types=1);

namespace EightshiftSeo\Sitemap;

use EightshiftSeo\Config\AiBotRegistry;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * AiCrawlerRobotsTxt — appends per-bot Allow / Disallow stanzas to robots.txt
 * based on the aiCrawlers settings.
 *
 * Default policy is "allow" (fully open). Only bots whose individual policy
 * differs from the default policy produce a stanza — keeping robots.txt small
 * on fresh installs where nothing has been changed.
 *
 * Stanzas are inserted before the existing sitemap line so the ordering
 * (User-agent directives → Sitemap: declarations) follows the robots.txt spec.
 *
 * Filter: es_seo_ai_crawler_robots_txt (string $output, bool $public) — allows
 * final string manipulation after all stanzas are assembled.
 */
class AiCrawlerRobotsTxt implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		// Priority 9 — fires just before SitemapHooks appends the Sitemap: line at 10.
		\add_filter('robots_txt', [$this, 'appendAiCrawlerStanzas'], 9, 2);
	}

	/**
	 * Append AI crawler User-agent stanzas to the robots.txt output.
	 *
	 * @param string $output  Current robots.txt content.
	 * @param bool   $public  Whether the site is set to public.
	 *
	 * @return string
	 */
	public function appendAiCrawlerStanzas(string $output, bool $public): string
	{
		if (!$public) {
			return $output;
		}

		$enabled = (bool) Options::getOption(['aiCrawlers', 'enabled']);
		if (!$enabled) {
			return $output;
		}

		$defaultPolicy = (string) (Options::getOption(['aiCrawlers', 'defaultPolicy']) ?: 'allow');
		$perBot        = Options::getOption(['aiCrawlers', 'perBot']) ?: [];

		if (!\is_array($perBot)) {
			$perBot = [];
		}

		$registry = AiBotRegistry::getBots();
		$registry = \apply_filters(Options::getFilter('aiBotRegistry'), $registry);

		$stanzas = [];

		foreach ($registry as $slug => $bot) {
			$botPolicy = isset($perBot[$slug]) ? (string) ($perBot[$slug]['policy'] ?? $defaultPolicy) : $defaultPolicy;

			// Only emit stanzas that differ from the global default — keeps the file minimal.
			if ($botPolicy === $defaultPolicy) {
				continue;
			}

			$stanza = 'User-agent: ' . $bot['name'] . "\n";
			$stanza .= ($botPolicy === 'disallow') ? "Disallow: /\n" : "Allow: /\n";

			$crawlDelay = isset($perBot[$slug]['crawlDelay']) ? (int) $perBot[$slug]['crawlDelay'] : 0;
			if ($crawlDelay > 0) {
				$stanza .= 'Crawl-delay: ' . $crawlDelay . "\n";
			}

			$stanzas[] = $stanza;
		}

		if (empty($stanzas)) {
			return $output;
		}

		$block = "\n# AI crawlers — managed by Eightshift SEO (last verified " . AiBotRegistry::LAST_VERIFIED . ")\n";
		$block .= \implode("\n", $stanzas);

		$combined = \rtrim($output) . $block . "\n";

		return \apply_filters(Options::getFilter('aiCrawlerRobotsTxt'), $combined, $public);
	}
}
