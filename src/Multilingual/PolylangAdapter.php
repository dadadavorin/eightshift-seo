<?php

/**
 * Adapter for the Polylang plugin.
 *
 * @package EightshiftSeo\Multilingual
 */

declare(strict_types=1);

namespace EightshiftSeo\Multilingual;

/**
 * PolylangAdapter — hreflang adapter for Polylang (free + Pro).
 *
 * Uses pll_get_post() to resolve translated post IDs and pll_the_languages()
 * to build locale-to-URL pairs for archive / home pages.
 */
class PolylangAdapter implements MultilingualAdapterInterface
{
	/**
	 * Returns true when Polylang is active.
	 *
	 * @return bool
	 */
	public static function isActive(): bool
	{
		return \function_exists('pll_the_languages') && \function_exists('pll_get_post');
	}

	/**
	 * Build alternates from Polylang's translation data.
	 *
	 * @param int|null $postId Current post ID.
	 *
	 * @return array<int, array{locale: string, url: string}>
	 */
	public function getAlternates(?int $postId = null): array
	{
		if (!\function_exists('pll_the_languages')) {
			return [];
		}

		$langs = \pll_the_languages(['raw' => 1]);

		if (!\is_array($langs) || empty($langs)) {
			return [];
		}

		$alternates = [];

		foreach ($langs as $lang) {
			$locale = \str_replace('_', '-', (string) ($lang['locale'] ?? ''));
			$url    = (string) ($lang['url'] ?? '');

			// For singular posts, override with the specific translation URL.
			if ($postId && \function_exists('pll_get_post')) {
				$translatedId = \pll_get_post($postId, $lang['slug'] ?? '');
				if ($translatedId) {
					$translatedUrl = \get_permalink((int) $translatedId);
					if (\is_string($translatedUrl)) {
						$url = $translatedUrl;
					}
				}
			}

			if ($locale === '' || $url === '') {
				continue;
			}

			$alternates[] = ['locale' => $locale, 'url' => $url];
		}

		return $alternates;
	}

	/**
	 * Returns the default language locale from Polylang.
	 *
	 * @return string
	 */
	public function getDefaultLocale(): string
	{
		if (\function_exists('pll_default_language')) {
			$default = \pll_default_language('locale');
			if (\is_string($default) && $default !== '') {
				return \str_replace('_', '-', $default);
			}
		}

		return \str_replace('_', '-', \get_locale());
	}
}
