<?php

/**
 * The file that hooks into WordPress native sitemaps.
 *
 * @package EightshiftSeo\Sitemap
 */

declare(strict_types=1);

namespace EightshiftSeo\Sitemap;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * SitemapHooks class — integrates with WP 6.8+ native sitemap infrastructure.
 *
 * Zero custom sitemap generation — all hooks are native WP filters:
 *   - wp_sitemaps_post_types        — exclude configured post types
 *   - wp_sitemaps_taxonomies        — exclude configured taxonomies
 *   - wp_sitemaps_posts_query_args  — exclude noindexed posts via meta_query
 *   - wp_sitemaps_enabled           — global on/off toggle (always on by default)
 *   - robots_txt                    — append Sitemap: line when enabled
 */
class SitemapHooks implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('wp_sitemaps_add_provider', [$this, 'disableUsersSitemap'], 10, 2);
		\add_filter('wp_sitemaps_post_types', [$this, 'filterSitemapPostTypes']);
		\add_filter('wp_sitemaps_taxonomies', [$this, 'filterSitemapTaxonomies']);
		\add_filter('wp_sitemaps_posts_query_args', [$this, 'excludeNoindexedPosts'], 10, 2);
		\add_filter('robots_txt', [$this, 'appendSitemapToRobotsTxt'], 10, 2);
	}

	/**
	 * Disable the users sitemap provider.
	 *
	 * Author archive URLs expose admin usernames (brute-force risk) and typically
	 * contain thin or duplicate content. Excluded by default; can be re-enabled
	 * via the eightshift_seo_enable_users_sitemap filter.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider The provider instance or false to skip.
	 * @param string                      $name     Provider name (e.g. 'posts', 'users').
	 *
	 * @return \WP_Sitemaps_Provider|false
	 */
	public function disableUsersSitemap($provider, string $name)
	{
		if ('users' !== $name) {
			return $provider;
		}

		$enable = (bool) \apply_filters(Options::getFilter('enableUsersSitemap'), false);

		return $enable ? $provider : false;
	}

	/**
	 * Remove excluded post types from the native sitemap.
	 *
	 * @param array<string, \WP_Post_Type> $postTypes All public post types.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	public function filterSitemapPostTypes(array $postTypes): array
	{
		$excluded = Options::getOption(['sitemap', 'excludedPostTypes']);

		if (!\is_array($excluded) || empty($excluded)) {
			return $postTypes;
		}

		foreach ($excluded as $slug) {
			unset($postTypes[$slug]);
		}

		// Allow per-project overrides after settings exclusions are applied.
		return \apply_filters(Options::getFilter('sitemapPostTypes'), $postTypes);
	}

	/**
	 * Remove excluded taxonomies from the native sitemap.
	 *
	 * @param array<string, \WP_Taxonomy> $taxonomies All public taxonomies.
	 *
	 * @return array<string, \WP_Taxonomy>
	 */
	public function filterSitemapTaxonomies(array $taxonomies): array
	{
		$excluded = Options::getOption(['sitemap', 'excludedTaxonomies']);

		if (!\is_array($excluded) || empty($excluded)) {
			return $taxonomies;
		}

		foreach ($excluded as $slug) {
			unset($taxonomies[$slug]);
		}

		return \apply_filters(Options::getFilter('sitemapTaxonomies'), $taxonomies);
	}

	/**
	 * Exclude noindexed posts from sitemap queries via meta_query.
	 *
	 * Because es_seo_noindex is registered without a leading underscore it is
	 * queryable directly — no custom indexing required.
	 *
	 * @param array<mixed> $args      WP_Query args being built for the sitemap.
	 * @param string       $postType  Current post type slug.
	 *
	 * @return array<mixed>
	 */
	public function excludeNoindexedPosts(array $args, string $postType): array
	{
		$noindexKey = Options::getMetaKey('noindex');

		if (empty($noindexKey)) {
			return $args;
		}

		$existingMeta = $args['meta_query'] ?? [];

		$args['meta_query'] = \array_merge(
			$existingMeta,
			[
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[
						'key'     => $noindexKey,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => $noindexKey,
						'value'   => '1',
						'compare' => '!=',
					],
				],
			]
		);

		return $args;
	}

	/**
	 * Append the sitemap URL to robots.txt when the setting is enabled.
	 *
	 * @param string $output  Current robots.txt content.
	 * @param bool   $public  Whether the site is set to public.
	 *
	 * @return string
	 */
	public function appendSitemapToRobotsTxt(string $output, bool $public): string
	{
		if (!$public) {
			return $output;
		}

		$addToRobots = Options::getOption(['sitemap', 'addToRobotsTxt']);

		if (!$addToRobots) {
			return $output;
		}

		$sitemapUrl = \home_url('/wp-sitemap.xml');

		// Avoid duplicate entries if already present.
		if (!\str_contains($output, $sitemapUrl)) {
			$output = \rtrim($output) . "\nSitemap: " . $sitemapUrl . "\n";
		}

		// Phase 8 — also advertise the LLM-targeted sitemap variant when enabled.
		if (Options::getOptionChecked(['sitemap', 'llmSitemap', 'enabled'])) {
			$llmSitemapUrl = \home_url('/llm-sitemap.xml');

			if (!\str_contains($output, $llmSitemapUrl)) {
				$output = \rtrim($output) . "\nSitemap: " . $llmSitemapUrl . "\n";
			}
		}

		return $output;
	}
}
