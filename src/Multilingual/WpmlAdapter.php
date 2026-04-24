<?php

/**
 * Adapter for the WPML plugin.
 *
 * @package EightshiftSeo\Multilingual
 */

declare(strict_types=1);

namespace EightshiftSeo\Multilingual;

/**
 * WpmlAdapter — hreflang adapter for WPML (Sitepress Multilingual CMS).
 *
 * Reads the wpml_hreflangs filter output when available; falls back to
 * icl_get_languages() for older WPML installs.
 */
class WpmlAdapter implements MultilingualAdapterInterface
{
	/**
	 * Returns true when WPML is active.
	 *
	 * @return bool
	 */
	public static function isActive(): bool
	{
		return \defined('ICL_SITEPRESS_VERSION') || \class_exists('SitePress');
	}

	/**
	 * Build alternates from WPML.
	 *
	 * @param int|null $postId Unused — WPML resolves per current request.
	 *
	 * @return array<int, array{locale: string, url: string}>
	 */
	public function getAlternates(?int $postId = null): array
	{
		// WPML 4.0+ provides a wpml_hreflangs filter that returns ready-made alternates.
		$hreflangs = \apply_filters('wpml_hreflangs', null);

		if (\is_array($hreflangs) && !empty($hreflangs)) {
			$alternates = [];
			foreach ($hreflangs as $locale => $url) {
				if ($locale === 'x-default') {
					continue; // We handle x-default ourselves.
				}
				$alternates[] = [
					'locale' => (string) $locale,
					'url'    => (string) $url,
				];
			}
			return $alternates;
		}

		// Fallback: use icl_get_languages().
		if (!\function_exists('icl_get_languages')) {
			return [];
		}

		$languages  = \icl_get_languages('skip_missing=0&orderby=code');
		$alternates = [];

		if (!\is_array($languages)) {
			return [];
		}

		foreach ($languages as $lang) {
			$locale = \str_replace('_', '-', (string) ($lang['default_locale'] ?? ''));
			$url    = (string) ($lang['url'] ?? '');

			if ($locale === '' || $url === '') {
				continue;
			}

			$alternates[] = ['locale' => $locale, 'url' => $url];
		}

		return $alternates;
	}

	/**
	 * Returns the WPML default language locale.
	 *
	 * @return string
	 */
	public function getDefaultLocale(): string
	{
		$default = \apply_filters('wpml_default_language', null);
		if (\is_string($default) && $default !== '') {
			// WPML returns a language code like 'en'; look up the full locale.
			$locale = \apply_filters('wpml_locale', null, $default);
			if (\is_string($locale) && $locale !== '') {
				return \str_replace('_', '-', $locale);
			}
			return $default;
		}

		return \str_replace('_', '-', \get_locale());
	}
}
