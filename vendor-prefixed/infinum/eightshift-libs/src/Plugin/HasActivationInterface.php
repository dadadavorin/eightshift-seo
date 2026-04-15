<?php

/**
 * File that holds Has_Activation interface
 *
 * @package EightshiftLibs\Plugin
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Plugin;

/**
 * Interface Has_Activation.
 *
 * An object that can be activated.
 */
interface HasActivationInterface
{
	/**
	 * Activate the service.
	 *
	 * Used when adding certain capabilities of a service.
	 *
	 * Example: add_role, add_cap, etc.
	 *
	 * @return void
	 */
	public function activate(): void;
}
