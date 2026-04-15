<?php

/**
 * The file that defines the main start class.
 *
 * @package EightshiftSeo\Main
 */

declare(strict_types=1);

namespace EightshiftSeo\Main;

use EightshiftSeoVendor\EightshiftLibs\Main\AbstractMain;

/**
 * The main start class.
 *
 * This is used to define admin-specific hooks, and
 * theme-facing site hooks.
 *
 * Maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 */
class Main extends AbstractMain
{
	/**
	 * Register the project with the WordPress system.
	 *
	 * The register_service method will call the register() method in every service class,
	 * which holds the actions and filters - effectively replacing the need to manually add
	 * them in one place.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('plugins_loaded', [$this, 'registerServices']);
	}
}
