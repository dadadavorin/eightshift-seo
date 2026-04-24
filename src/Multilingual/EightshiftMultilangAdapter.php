<?php

/**
 * Adapter for the eightshift-multilang plugin.
 *
 * @package EightshiftSeo\Multilingual
 */

declare(strict_types=1);

namespace EightshiftSeo\Multilingual;

/**
 * EightshiftMultilangAdapter — hreflang adapter for eightshift-multilang.
 *
 * Detects the plugin via its main class or the `eightshift_multilang_get_translations`
 * function, then builds alternates from the translations map.
 */
class EightshiftMultilangAdapter implements MultilingualAdapterInterface
{
	/**
	 * Returns true when eightshift-multilang is active.
	 *
	 * @return bool
	 */
	public static function isActive(): bool
	{
		return \function_exists('eightshift_multilang_get_translations')
			|| \class_exists('EightshiftMultilang\\Main\\Main');
	}

	/**
	 * Build alternates from eightshift-multilang's translation map.
	 *
	 * @param int|null $postId Current post ID.
	 *
	 * @return array<int, array{locale: string, url: string}>
	 */
	public function getAlternates(?int $postId = null): array
	{
		if (!\function_exists('eightshift_multilang_get_translations')) {
			return [];
		}

		// The function returns [ locale => post_id ] for the current post/object.
		$translations = \eightshift_multilang_get_translations($postId);

		if (!\is_array($translations) || empty($translations)) {
			return [];
		}

		$alternates = [];
		foreach ($translations as $locale => $translatedPostId) {
			$url = \get_permalink((int) $translatedPostId);
			if (!\is_string($url) || $url === '') {
				continue;
			}
			$alternates[] = [
				'locale' => \str_replace('_', '-', (string) $locale),
				'url'    => $url,
			];
		}

		return $alternates;
	}

	/**
	 * Returns the default locale from eightshift-multilang or WordPress.
	 *
	 * @return string
	 */
	public function getDefaultLocale(): string
	{
		if (\function_exists('eightshift_multilang_get_default_locale')) {
			$locale = \eightshift_multilang_get_default_locale();
			if (\is_string($locale) && $locale !== '') {
				return \str_replace('_', '-', $locale);
			}
		}

		return \str_replace('_', '-', \get_locale());
	}
}
