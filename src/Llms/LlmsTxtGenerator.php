<?php

/**
 * The file that generates and serves llms.txt.
 *
 * @package EightshiftSeo\Llms
 */

declare(strict_types=1);

namespace EightshiftSeo\Llms;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * LlmsTxtGenerator — generates and serves the llms.txt file.
 *
 * llms.txt is a Markdown-formatted file designed to give LLMs structured access
 * to site content. It follows the llmstxt.org convention.
 *
 * URL: {site_url}/llms.txt
 *
 * Features:
 *   - Grouped by post type (pages first, then posts, then others)
 *   - TL;DR or excerpt used as per-entry description
 *   - Noindexed posts skipped
 *   - Per-type limit configurable via settings
 *   - Output cached in a transient for 24 hours
 *   - Cache invalidated on post save/delete and settings change
 *   - Hard 256KB size cap
 */
class LlmsTxtGenerator implements ServiceInterface
{
	/**
	 * Transient key for cached llms.txt content.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'es_seo_llms_txt';

	/**
	 * Maximum output size in bytes (256KB).
	 *
	 * @var int
	 */
	private const MAX_BYTES = 262144;

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
	 * Prevent WordPress from redirect-to-slash-ing our endpoint.
	 *
	 * @param string|false $redirectUrl The canonical redirect URL WP wants to issue.
	 *
	 * @return string|false
	 */
	public function suppressCanonicalRedirect(string|false $redirectUrl): string|false
	{
		if (\get_query_var('es_seo_llms_txt')) {
			return false;
		}

		return $redirectUrl;
	}

	/**
	 * Register the llms.txt rewrite rule.
	 *
	 * @return void
	 */
	public function registerRewriteRule(): void
	{
		\add_rewrite_rule('^llms\.txt$', 'index.php?es_seo_llms_txt=1', 'top');
	}

	/**
	 * Add custom query variable.
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string>
	 */
	public function addQueryVars(array $vars): array
	{
		$vars[] = 'es_seo_llms_txt';
		return $vars;
	}

	/**
	 * Serve the llms.txt file.
	 *
	 * @return void
	 */
	public function serve(): void
	{
		if (!\get_query_var('es_seo_llms_txt')) {
			return;
		}

		if (!Options::getOptionChecked(['llmsTxt', 'enabled'])) {
			\status_header(404);
			echo '404 Not Found';
			exit;
		}

		$cached = \get_transient(self::TRANSIENT_KEY);

		if (\is_string($cached) && $cached !== '') {
			$output = $cached;
		} else {
			$output = $this->generate();
			\set_transient(self::TRANSIENT_KEY, $output, \DAY_IN_SECONDS);
		}

		\status_header(200);
		\header('Content-Type: text/markdown; charset=utf-8');
		\header('Cache-Control: public, max-age=3600');

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
		exit;
	}

	/**
	 * Generate the full llms.txt Markdown content.
	 *
	 * @return string
	 */
	public function generate(): string
	{
		$siteName     = (string) \get_bloginfo('name');
		$intro        = (string) Options::getOption(['llmsTxt', 'intro']);
		$outro        = (string) Options::getOption(['llmsTxt', 'outro']);
		$postTypes    = Options::getOption(['llmsTxt', 'postTypes']);
		$perTypeLimit = (int) Options::getOption(['llmsTxt', 'perTypeLimit']);

		if (!\is_array($postTypes) || empty($postTypes)) {
			$postTypes = ['page', 'post'];
		}

		if ($perTypeLimit <= 0) {
			$perTypeLimit = 200;
		}

		$noindexKey = Options::getMetaKey('noindex');
		$tldrKey    = Options::getMetaKey('tldr');
		$descKey    = Options::getMetaKey('description');

		$lines = [];

		// Header.
		$lines[] = '# ' . $siteName;
		$lines[] = '';

		if ($intro !== '') {
			$lines[] = '> ' . $intro;
			$lines[] = '';
		}

		// Reorder: pages first, posts second, rest alphabetical.
		$orderedTypes = $this->orderPostTypes($postTypes);

		foreach ($orderedTypes as $postType) {
			$postTypeObj = \get_post_type_object($postType);
			if (!$postTypeObj) {
				continue;
			}

			$label = $postTypeObj->labels->name ?? $postType;

			$posts = \get_posts([
				'post_type'      => $postType,
				'post_status'    => 'publish',
				'posts_per_page' => $perTypeLimit + 1, // Fetch one extra to detect truncation.
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => [
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
			]);

			if (empty($posts)) {
				continue;
			}

			$truncated   = \count($posts) > $perTypeLimit;
			$posts       = \array_slice($posts, 0, $perTypeLimit);

			$lines[] = '## ' . $label;

			foreach ($posts as $post) {
				$url   = (string) \get_permalink($post->ID);
				$title = \get_the_title($post->ID);

				// TL;DR → SEO description → trimmed excerpt.
				$summary = (string) \get_post_meta($post->ID, $tldrKey, true);
				if ($summary === '') {
					$summary = (string) \get_post_meta($post->ID, $descKey, true);
				}
				if ($summary === '') {
					$summary = \wp_trim_words(\get_the_excerpt($post->ID), 20, '');
				}

				$entry = '- [' . $title . '](' . $url . ')';
				if ($summary !== '') {
					$entry .= ' — ' . $summary;
				}

				$lines[] = $entry;
			}

			if ($truncated) {
				$lines[] = '';
				$lines[] = '_(showing ' . $perTypeLimit . ' most recent — see sitemap for full list)_';
			}

			$lines[] = '';
		}

		if ($outro !== '') {
			$lines[] = $outro;
			$lines[] = '';
		}

		$output = \implode("\n", $lines);

		// Hard size cap.
		if (\strlen($output) > self::MAX_BYTES) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			\trigger_error(
				'[eightshift-seo] llms.txt output exceeds 256KB. Consider reducing perTypeLimit.',
				\E_USER_NOTICE
			);

			\doing_it_wrong(
				__METHOD__,
				'llms.txt output exceeds the 256KB hard cap. Truncation applied.',
				'1.0.0'
			);

			$output = \substr($output, 0, self::MAX_BYTES);
		}

		return $output;
	}

	/**
	 * Delete the cached llms.txt transient.
	 *
	 * @return void
	 */
	public function invalidateCache(): void
	{
		\delete_transient(self::TRANSIENT_KEY);
	}

	/**
	 * Order post types: page first, post second, rest in original order.
	 *
	 * @param array<string> $postTypes Post type slugs.
	 *
	 * @return array<string>
	 */
	private function orderPostTypes(array $postTypes): array
	{
		$ordered = [];

		// Pages first.
		if (\in_array('page', $postTypes, true)) {
			$ordered[] = 'page';
		}

		// Posts second.
		if (\in_array('post', $postTypes, true)) {
			$ordered[] = 'post';
		}

		// All others in their original order.
		foreach ($postTypes as $type) {
			if ($type !== 'page' && $type !== 'post') {
				$ordered[] = $type;
			}
		}

		return $ordered;
	}
}
