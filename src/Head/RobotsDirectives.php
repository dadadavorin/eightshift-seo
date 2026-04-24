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
		$robots = $this->applyArchiveDefaults($robots);

		$post = \get_post();

		if (\is_singular() && $post instanceof WP_Post) {
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
	 * Apply noindex defaults for low-value archive contexts (search, date,
	 * paginated archives, 404, attachment, author).
	 *
	 * @param array<string, string|bool|int> $robots Current directives.
	 *
	 * @return array<string, string|bool|int>
	 */
	private function applyArchiveDefaults(array $robots): array
	{
		$defaults = Options::getOption(['robotsDefaults', 'archives']);
		if (!\is_array($defaults)) {
			return $robots;
		}

		$noindex = false;

		if (!empty($defaults['search']) && \is_search()) {
			$noindex = true;
		} elseif (!empty($defaults['date']) && \is_date()) {
			$noindex = true;
		} elseif (!empty($defaults['404']) && \is_404()) {
			$noindex = true;
		} elseif (!empty($defaults['attachment']) && \is_attachment()) {
			$noindex = true;
		} elseif (!empty($defaults['paged']) && \is_paged()) {
			$noindex = true;
		} elseif (isset($defaults['author']) && \is_author()) {
			$authorSetting = $defaults['author'];
			if ($authorSetting === true || $authorSetting === 'always') {
				$noindex = true;
			} elseif ($authorSetting === 'auto') {
				$noindex = $this->isSingleAuthorSite();
			}
		}

		if ($noindex) {
			$robots['noindex'] = true;
			unset($robots['index']);
		}

		return $robots;
	}

	/**
	 * Determine whether the site has only one active author.
	 *
	 * Caches the result in a short-lived request static to avoid repeated
	 * count_users() calls on the same request.
	 *
	 * @return bool
	 */
	private function isSingleAuthorSite(): bool
	{
		static $isSingle = null;

		if ($isSingle !== null) {
			return $isSingle;
		}

		$counts = \count_users();
		$roles  = $counts['avail_roles'] ?? [];

		$authorsCount = 0;
		foreach (['administrator', 'editor', 'author'] as $role) {
			$authorsCount += (int) ($roles[$role] ?? 0);
		}

		$isSingle = $authorsCount <= 1;
		return $isSingle;
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

		// Extended directives.
		if ((bool) \get_post_meta($post->ID, Options::getMetaKey('noarchive'), true)) {
			$robots['noarchive'] = true;
		}

		if ((bool) \get_post_meta($post->ID, Options::getMetaKey('nosnippet'), true)) {
			$robots['nosnippet'] = true;
		}

		if ((bool) \get_post_meta($post->ID, Options::getMetaKey('noimageindex'), true)) {
			$robots['noimageindex'] = true;
		}

		if ((bool) \get_post_meta($post->ID, Options::getMetaKey('notranslate'), true)) {
			$robots['notranslate'] = true;
		}

		$unavailableAfter = (string) \get_post_meta($post->ID, Options::getMetaKey('unavailableAfter'), true);
		if (!empty($unavailableAfter)) {
			// Format: "unavailable_after: YYYY-MM-DDTHH:MM:SSZ" — the wp_robots filter uses this as a key.
			$robots['unavailable_after'] = \gmdate('Y-m-d\TH:i:s\Z', (int) \strtotime($unavailableAfter));
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

		if ($noindex) {
			$robots['noindex'] = true;
			unset($robots['index']);
		}

		if ($nofollow) {
			$robots['nofollow'] = true;
			unset($robots['follow']);
		}

		// Extended directives: per-term meta, falling back to taxonomy settings defaults.
		foreach (['noarchive', 'nosnippet', 'noimageindex', 'notranslate'] as $directive) {
			$termValue    = $term instanceof WP_Term
				? (bool) \get_term_meta($term->term_id, Options::getTermMetaKey($directive), true)
				: false;
			$defaultValue = !empty($taxDefault[$directive]);

			if ($termValue || $defaultValue) {
				$robots[$directive] = true;
			}
		}

		return $robots;
	}
}
