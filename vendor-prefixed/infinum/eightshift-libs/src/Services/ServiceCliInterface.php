<?php

/**
 * File that holds Service WP-CLI interface
 *
 * @package EightshiftLibs\Services
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\Services;

/**
 * Interface Service WP-CLI.
 *
 * A generic service. Service is a part of the plugin/theme functionality used only for WP-CLI classes.
 */
interface ServiceCliInterface
{
	/**
	 * Register the current service.
	 *
	 * A register method holds the plugin action and filter hooks.
	 * Following the single responsibility principle, every class
	 * holds a functionality for a certain part of the plugin.
	 * This is why every class should hold its own hooks.
	 *
	 * @return void
	 */
	public function register(): void;
}
