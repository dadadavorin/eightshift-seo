<?php

/**
 * File containing an interface for holding Manifest Cache functionality.
 *
 * It is used to provide manifest.json file location stored in the transient cache.
 *
 * @package EightshiftLibs\Cache
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Cache;

/**
 * Interface ManifestCacheInterface
 */
interface ManifestCacheInterface
{
	/**
	 * Set all cache.
	 *
	 * @return void
	 */
	public function setAllCache(): void;
}
