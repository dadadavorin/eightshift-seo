<?php

/**
 * Projects MenuPositionsInterface interface.
 *
 * Used to define available menu positions in your project.
 *
 * @package EightshiftLibs\Menu
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Menu;

/**
 * Interface MenuPositionsInterface
 */
interface MenuPositionsInterface
{
	/**
	 * Return all menu positions
	 *
	 * @return array<string, mixed> Of menu positions with name and slug.
	 */
	public function getMenuPositions(): array;
}
