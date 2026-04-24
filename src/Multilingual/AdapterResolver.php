<?php

/**
 * The resolver that picks the active multilingual adapter.
 *
 * @package EightshiftSeo\Multilingual
 */

declare(strict_types=1);

namespace EightshiftSeo\Multilingual;

/**
 * AdapterResolver — returns the first active multilingual adapter for the
 * current request, caching the result for the lifetime of the request.
 *
 * Priority: EightshiftMultilang → Polylang → WPML → Null.
 */
class AdapterResolver
{
	/**
	 * Adapter classes to probe in order of preference.
	 *
	 * @var array<class-string<MultilingualAdapterInterface>>
	 */
	private const ADAPTERS = [
		EightshiftMultilangAdapter::class,
		PolylangAdapter::class,
		WpmlAdapter::class,
	];

	/**
	 * Return the first active adapter (cached per request).
	 *
	 * @return MultilingualAdapterInterface
	 */
	public static function resolve(): MultilingualAdapterInterface
	{
		static $resolved = null;

		if ($resolved !== null) {
			return $resolved;
		}

		foreach (self::ADAPTERS as $adapterClass) {
			if ($adapterClass::isActive()) {
				$resolved = new $adapterClass();
				return $resolved;
			}
		}

		$resolved = new NullAdapter();
		return $resolved;
	}
}
