<?php

/**
 * Null adapter — returned when no multilingual system is detected.
 *
 * @package EightshiftSeo\Multilingual
 */

declare(strict_types=1);

namespace EightshiftSeo\Multilingual;

/**
 * NullAdapter — safe no-op adapter used when no multilingual plugin is active.
 */
class NullAdapter implements MultilingualAdapterInterface
{
	/**
	 * Always returns true — the null adapter is always available as a fallback.
	 *
	 * @return bool
	 */
	public static function isActive(): bool
	{
		return true;
	}

	/**
	 * Returns an empty array — no alternates without a multilingual plugin.
	 *
	 * @param int|null $postId Unused.
	 *
	 * @return array<int, array{locale: string, url: string}>
	 */
	public function getAlternates(?int $postId = null): array
	{
		return [];
	}

	/**
	 * Returns the WordPress locale converted to BCP 47 format.
	 *
	 * @return string
	 */
	public function getDefaultLocale(): string
	{
		return \str_replace('_', '-', \get_locale());
	}
}
