<?php

/**
 * The file that defines the manifest cache class.
 *
 * @package EightshiftSeo\Cache
 */

declare(strict_types=1);

namespace EightshiftSeo\Cache;

use EightshiftSeo\Config\Config;
use EightshiftSeoVendor\EightshiftLibs\Cache\AbstractManifestCache;

/**
 * The manifest cache class.
 */
class ManifestCache extends AbstractManifestCache
{
	/**
	 * Get cache name.
	 *
	 * @return string Cache name.
	 */
	public function getCacheName(): string
	{
		return Config::getProjectTextDomain();
	}

	/**
	 * Get cache version.
	 *
	 * @return string
	 */
	public function getVersion(): string
	{
		return Config::getProjectVersion();
	}
}
