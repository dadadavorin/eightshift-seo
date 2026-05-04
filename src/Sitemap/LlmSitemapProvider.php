<?php

/**
 * The file that generates the AI-targeted sitemap variant.
 *
 * @package EightshiftSeo\Sitemap
 */

declare(strict_types=1);

namespace EightshiftSeo\Sitemap;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * LlmSitemapProvider — serves /llm-sitemap.xml, a sitemap variant that lists
 * canonical singular URLs together with their `.md` variants and a
 * `<llm:dateReviewed>` namespaced extension when the dateReviewed meta is set.
 *
 * Caching: hashed under a key derived from the latest post_modified date so
 * the cached output is invalidated whenever any content is updated. Honours
 * the same noindex exclusions as the native sitemap.
 */
class LlmSitemapProvider implements ServiceInterface
{
	private const TRANSIENT_PREFIX = 'es_seo_llm_sitemap_';
	private const NS_SITEMAP       = 'http://www.sitemaps.org/schemas/sitemap/0.9';
	private const NS_LLM           = 'https://eightshift.com/sitemap-llm/0.1';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerRewriteRule']);
		\add_filter('query_vars', [$this, 'addQueryVars']);
		\add_action('template_redirect', [$this, 'serve'], 1);
		\add_filter('redirect_canonical', [$this, 'suppressCanonicalRedirect']);
		\add_action('save_post', [$this, 'invalidateCache']);
		\add_action('deleted_post', [$this, 'invalidateCache']);
		\add_action('update_option_' . Options::getOptionsName(), [$this, 'invalidateCache']);
	}

	/**
	 * Prevent WordPress from redirect-to-slash-ing /llm-sitemap.xml.
	 *
	 * @param string|false $redirectUrl The canonical redirect URL WP wants to issue.
	 *
	 * @return string|false
	 */
	public function suppressCanonicalRedirect(string|false $redirectUrl): string|false
	{
		if (\get_query_var('es_seo_llm_sitemap')) {
			return false;
		}

		return $redirectUrl;
	}

	/**
	 * Register the LLM sitemap rewrite rule.
	 *
	 * @return void
	 */
	public function registerRewriteRule(): void
	{
		\add_rewrite_rule('^llm-sitemap\.xml$', 'index.php?es_seo_llm_sitemap=1', 'top');
	}

	/**
	 * Add the custom query variable.
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string>
	 */
	public function addQueryVars(array $vars): array
	{
		$vars[] = 'es_seo_llm_sitemap';
		return $vars;
	}

	/**
	 * Serve the LLM sitemap.
	 *
	 * @return void
	 */
	public function serve(): void
	{
		if (!\get_query_var('es_seo_llm_sitemap')) {
			return;
		}

		if (!Options::getOptionChecked(['sitemap', 'llmSitemap', 'enabled'])) {
			\status_header(404);
			echo '404 Not Found';
			exit;
		}

		$cacheKey = self::TRANSIENT_PREFIX . 'v1';
		$cached   = \get_transient($cacheKey);

		if (\is_string($cached) && $cached !== '') {
			$output = $cached;
		} else {
			$output = $this->generate();
			\set_transient($cacheKey, $output, \HOUR_IN_SECONDS);
		}

		\status_header(200);
		\header('Content-Type: application/xml; charset=utf-8');
		\header('Cache-Control: public, max-age=3600');

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
		exit;
	}

	/**
	 * Build the XML body of the sitemap.
	 *
	 * @return string
	 */
	public function generate(): string
	{
		$includeMd = (bool) Options::getOption(['sitemap', 'llmSitemap', 'includeMd']);

		$configuredTypes = Options::getOption(['sitemap', 'llmSitemap', 'postTypes']);
		if (!\is_array($configuredTypes) || empty($configuredTypes)) {
			$configuredTypes = Options::getPublicPostTypes();

			$excluded = Options::getOption(['sitemap', 'excludedPostTypes']);
			if (\is_array($excluded)) {
				$configuredTypes = \array_values(\array_diff($configuredTypes, $excluded));
			}
		}

		$supported = \apply_filters(
			Options::getFilter('supportedPostTypes'),
			Options::getPublicPostTypes()
		);

		$postTypes  = \array_values(\array_intersect($configuredTypes, $supported));
		$noindexKey = Options::getMetaKey('noindex');

		$urls = [];

		$perTypeLimit = (int) Options::getOption(['llmsTxt', 'perTypeLimit']);
		if ($perTypeLimit <= 0) {
			$perTypeLimit = 200;
		}

		foreach ($postTypes as $postType) {
			$posts = \get_posts([
				'post_type'      => $postType,
				'post_status'    => 'publish',
				'posts_per_page' => $perTypeLimit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => [
					'relation' => 'OR',
					['key' => $noindexKey, 'compare' => 'NOT EXISTS'],
					['key' => $noindexKey, 'value' => '1', 'compare' => '!='],
				],
			]);

			foreach ($posts as $post) {
				if (!$post instanceof WP_Post) {
					continue;
				}

				$entry = $this->buildEntry($post, false);
				if ($entry !== null) {
					$urls[] = $entry;
				}

				if ($includeMd) {
					$mdEntry = $this->buildEntry($post, true);
					if ($mdEntry !== null) {
						$urls[] = $mdEntry;
					}
				}
			}
		}

		$lines   = [];
		$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$lines[] = '<urlset xmlns="' . self::NS_SITEMAP . '" xmlns:llm="' . self::NS_LLM . '">';

		foreach ($urls as $url) {
			$lines[] = '  <url>';
			$lines[] = '    <loc>' . \esc_url($url['loc']) . '</loc>';
			if (!empty($url['lastmod'])) {
				$lines[] = '    <lastmod>' . \esc_html($url['lastmod']) . '</lastmod>';
			}
			if (!empty($url['dateReviewed'])) {
				$lines[] = '    <llm:dateReviewed>' . \esc_html($url['dateReviewed']) . '</llm:dateReviewed>';
			}
			if (!empty($url['canonical'])) {
				$lines[] = '    <llm:canonical>' . \esc_url($url['canonical']) . '</llm:canonical>';
			}
			$lines[] = '  </url>';
		}

		$lines[] = '</urlset>';

		return \implode("\n", $lines) . "\n";
	}

	/**
	 * Build a single sitemap entry for a post (or its .md variant).
	 *
	 * @param WP_Post $post  Source post.
	 * @param bool    $isMd  True to build the .md sibling entry.
	 *
	 * @return array{loc: string, lastmod: string, dateReviewed: string, canonical: string}|null
	 */
	private function buildEntry(WP_Post $post, bool $isMd): ?array
	{
		$canonical = (string) \get_permalink($post->ID);
		if ($canonical === '') {
			return null;
		}

		$loc = $isMd ? $this->mdUrlFor($canonical) : $canonical;
		if ($loc === '') {
			return null;
		}

		$lastmod  = \get_the_modified_date('c', $post) ?: '';
		$reviewed = (string) \get_post_meta($post->ID, Options::getMetaKey('dateReviewed'), true);

		$entry = [
			'loc'          => $loc,
			'lastmod'      => $lastmod,
			'dateReviewed' => \preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewed) ? $reviewed : '',
			'canonical'    => $isMd ? $canonical : '',
		];

		$filtered = \apply_filters(Options::getFilter('llmSitemapEntry'), $entry, $post, $isMd);

		return \is_array($filtered) ? $filtered : $entry;
	}

	/**
	 * Build the .md sibling URL for a canonical permalink.
	 *
	 * @param string $canonical Canonical URL of the post.
	 *
	 * @return string Empty string if the URL cannot be transformed.
	 */
	private function mdUrlFor(string $canonical): string
	{
		$parsed = \wp_parse_url($canonical);
		if (!\is_array($parsed) || empty($parsed['path'])) {
			return '';
		}

		$path = \rtrim((string) $parsed['path'], '/');
		if ($path === '') {
			return '';
		}

		$rebuilt = (isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '//')
			. ($parsed['host'] ?? '')
			. (isset($parsed['port']) ? ':' . $parsed['port'] : '')
			. $path . '.md';

		return $rebuilt;
	}

	/**
	 * Invalidate the cached LLM sitemap.
	 *
	 * @return void
	 */
	public function invalidateCache(): void
	{
		\delete_transient(self::TRANSIENT_PREFIX . 'v1');
	}
}
