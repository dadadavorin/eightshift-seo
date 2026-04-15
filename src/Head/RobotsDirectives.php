<?php

/**
 * The file that handles robots meta directives.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;
use WP_Term;

/**
 * RobotsDirectives class — merges per-post/per-term noindex/nofollow and advanced
 * directives (max-snippet, max-image-preview, max-video-preview) into wp_robots filter.
 *
 * Uses the native wp_robots filter (WP 5.7+). Always merges — never removes
 * directives that were already set upstream (e.g., by WordPress core for
 * draft pages or search results).
 */
class RobotsDirectives implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_filter('wp_robots', [$this, 'applyRobotsDirectives'], 10);
	}

	/**
	 * Add SEO-controlled directives to the robots array.
	 *
	 * @param array<string, string|bool|int> $robots Current robots directives.
	 *
	 * @return array<string, string|bool|int>
	 */
	public function applyRobotsDirectives(array $robots): array
	{
		$post = \get_post();

		if ($post instanceof WP_Post) {
			$robots = $this->applyPostDirectives($robots, $post);
		} elseif (\is_tax() || \is_category() || \is_tag()) {
			$robots = $this->applyTermDirectives($robots);
		}

		// Allow per-project overrides of the full directives array.
		return \apply_filters(
			Options::getFilter('robots'),
			$robots,
			$post instanceof WP_Post ? $post : null
		);
	}

	/**
	 * Apply post-level robots directives including advanced directives.
	 *
	 * @param array<string, string|bool|int> $robots Current directives.
	 * @param WP_Post                        $post   Current post.
	 *
	 * @return array<string, string|bool|int>
	 */
	private function applyPostDirectives(array $robots, WP_Post $post): array
	{
		$noindex  = (bool) \get_post_meta($post->ID, Options::getMetaKey('noindex'), true);
		$nofollow = (bool) \get_post_meta($post->ID, Options::getMetaKey('nofollow'), true);

		if ($noindex) {
			$robots['noindex'] = true;
			unset($robots['index']);
		}

		if ($nofollow) {
			$robots['nofollow'] = true;
			unset($robots['follow']);
		}

		// Advanced directives — only apply non-default values.
		$maxSnippet = (int) \get_post_meta($post->ID, Options::getMetaKey('maxSnippet'), true);
		if ($maxSnippet !== -1) {
			$robots['max-snippet'] = $maxSnippet;
		}

		$maxImagePreview = (string) \get_post_meta($post->ID, Options::getMetaKey('maxImagePreview'), true);
		if (!empty($maxImagePreview) && $maxImagePreview !== 'large') {
			$robots['max-image-preview'] = $maxImagePreview;
		}

		$maxVideoPreview = (int) \get_post_meta($post->ID, Options::getMetaKey('maxVideoPreview'), true);
		if ($maxVideoPreview !== -1) {
			$robots['max-video-preview'] = $maxVideoPreview;
		}

		return $robots;
	}

	/**
	 * Apply term-level and per-taxonomy-default robots directives for archive pages.
	 *
	 * Resolution order:
	 *   1. Per-term meta (es_seo_term_noindex / es_seo_term_nofollow)
	 *   2. Per-taxonomy settings defaults (robotsDefaults.taxonomies.<slug>)
	 *
	 * @param array<string, string|bool|int> $robots Current directives.
	 *
	 * @return array<string, string|bool|int>
	 */
	private function applyTermDirectives(array $robots): array
	{
		$term = \get_queried_object();

		if ($term instanceof WP_Term) {
			$noindex  = (bool) \get_term_meta($term->term_id, Options::getTermMetaKey('noindex'), true);
			$nofollow = (bool) \get_term_meta($term->term_id, Options::getTermMetaKey('nofollow'), true);
		} else {
			$noindex  = false;
			$nofollow = false;
		}

		// Fall back to per-taxonomy settings defaults when term meta is not set.
		if (!$noindex || !$nofollow) {
			$taxonomy   = $term instanceof WP_Term ? $term->taxonomy : '';
			$taxDefault = $taxonomy
				? (Options::getOption(['robotsDefaults', 'taxonomies', $taxonomy]) ?: [])
				: [];

			if (!$noindex && !empty($taxDefault['noindex'])) {
				$noindex = true;
			}

			if (!$nofollow && !empty($taxDefault['nofollow'])) {
				$nofollow = true;
			}
		}

		if ($noindex) {
			$robots['noindex'] = true;
			unset($robots['index']);
		}

		if ($nofollow) {
			$robots['nofollow'] = true;
			unset($robots['follow']);
		}

		return $robots;
	}
}
