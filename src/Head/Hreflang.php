<?php

/**
 * The file that emits hreflang alternate link tags.
 *
 * @package EightshiftSeo\Head
 */

declare(strict_types=1);

namespace EightshiftSeo\Head;

use EightshiftSeo\Multilingual\AdapterResolver;
use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;
use WP_Post;

/**
 * Hreflang class — emits <link rel="alternate" hreflang="…"> tags in wp_head
 * using the active multilingual adapter.
 *
 * Skipped when:
 *   - No multilingual plugin is active (NullAdapter returns empty array).
 *   - The post has es_seo_hreflang_disabled set.
 *   - The page is a feed or admin page.
 */
class Hreflang implements ServiceInterface
{
	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('wp_head', [$this, 'outputHreflangTags'], 6);
	}

	/**
	 * Emit hreflang link tags.
	 *
	 * @return void
	 */
	public function outputHreflangTags(): void
	{
		if (\is_feed() || \is_admin()) {
			return;
		}

		$postId = null;
		$post   = \get_post();

		if (\is_singular() && $post instanceof WP_Post) {
			// Skip when the post has hreflang suppressed.
			$disabled = (bool) \get_post_meta($post->ID, Options::getMetaKey('hreflangDisabled'), true);
			if ($disabled) {
				return;
			}
			$postId = $post->ID;
		}

		$adapter    = AdapterResolver::resolve();
		$alternates = $adapter->getAlternates($postId);

		// Allow per-project overrides.
		$alternates = \apply_filters(Options::getFilter('hreflangAlternates'), $alternates, $postId);

		if (!\is_array($alternates) || empty($alternates)) {
			return;
		}

		foreach ($alternates as $alt) {
			if (empty($alt['locale']) || empty($alt['url'])) {
				continue;
			}
			printf(
				'<link rel="alternate" hreflang="%s" href="%s">' . "\n",
				\esc_attr((string) $alt['locale']),
				\esc_url((string) $alt['url'])
			);
		}

		// x-default points to the default locale URL.
		$defaultUrl = $this->findDefaultUrl($alternates, $adapter->getDefaultLocale());
		if ($defaultUrl !== '') {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s">' . "\n",
				\esc_url($defaultUrl)
			);
		}
	}

	/**
	 * Find the URL for the default locale among the alternates.
	 *
	 * @param array<int, array{locale: string, url: string}> $alternates Alternates list.
	 * @param string                                          $defaultLocale BCP 47 default locale.
	 *
	 * @return string
	 */
	private function findDefaultUrl(array $alternates, string $defaultLocale): string
	{
		foreach ($alternates as $alt) {
			if (\str_starts_with((string) $alt['locale'], \substr($defaultLocale, 0, 2))) {
				return (string) $alt['url'];
			}
		}

		// Fallback to first entry if no match.
		return (string) ($alternates[0]['url'] ?? '');
	}
}
