<?php

/**
 * The interface for multilingual adapters.
 *
 * @package EightshiftSeo\Multilingual
 */

declare(strict_types=1);

namespace EightshiftSeo\Multilingual;

/**
 * MultilingualAdapterInterface — contract for hreflang alternate providers.
 *
 * Implementations must be stateless; each call to getAlternates() resolves
 * fresh data for the current request.
 */
interface MultilingualAdapterInterface
{
	/**
	 * Return true when this plugin/system is active and usable.
	 *
	 * @return bool
	 */
	public static function isActive(): bool;

	/**
	 * Return hreflang alternates for the current request.
	 *
	 * Each entry is an associative array with:
	 *   - 'locale' (string) — BCP 47 language tag, e.g. 'en-US', 'hr', 'de-DE'.
	 *   - 'url'    (string) — Absolute URL of the alternate page.
	 *
	 * @param int|null $postId Current post ID, or null for archive/non-singular pages.
	 *
	 * @return array<int, array{locale: string, url: string}>
	 */
	public function getAlternates(?int $postId = null): array;

	/**
	 * Return the BCP 47 locale tag for the site default language.
	 *
	 * Used to emit the x-default hreflang entry.
	 *
	 * @return string
	 */
	public function getDefaultLocale(): string;
}
