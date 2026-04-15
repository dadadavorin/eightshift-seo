<?php

/**
 * Plugin Name: Eightshift SEO
 * Plugin URI: https://github.com/infinum/eightshift-seo
 * Description: Lightweight SEO plugin for Eightshift projects. Handles meta title/description, robots directives, canonical URLs, OpenGraph/Twitter Cards, XML sitemap control, and BreadcrumbList JSON-LD.
 * Author: Team Eightshift
 * Author URI: https://eightshift.com/
 * Version: 1.0.0
 * License: MIT
 * License URI: http://www.gnu.org/licenses/gpl.html
 * Text Domain: eightshift-seo
 *
 * @package EightshiftSeo
 */

declare(strict_types=1);

namespace EightshiftSeo;

use EightshiftSeo\Cache\ManifestCache;
use EightshiftSeo\Main\Main;

/**
 * If this file is called directly, abort.
 */
if (! \defined('WPINC')) {
	die;
}

/**
 * Bailout, if the plugin is not loaded via Composer.
 */
if (!\file_exists(__DIR__ . '/vendor/autoload.php')) {
	return;
}

/**
 * Require the Composer autoloader.
 */
$loader = require __DIR__ . '/vendor/autoload.php';

/**
 * Require the Composer autoloader for the prefixed libraries.
 */
if (\file_exists(__DIR__ . '/vendor-prefixed/autoload.php')) {
	require __DIR__ . '/vendor-prefixed/autoload.php';
}

if (\class_exists(PluginFactory::class)) {
	/**
	 * The code that runs during plugin activation.
	 */
	\register_activation_hook(
		__FILE__,
		function () {
			PluginFactory::activate();
		}
	);

	/**
	 * The code that runs during plugin deactivation.
	 */
	\register_deactivation_hook(
		__FILE__,
		function () {
			PluginFactory::deactivate();
		}
	);
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
if (\class_exists(Main::class) && \class_exists(ManifestCache::class)) {
	(new ManifestCache())->setAllCache();
	(new Main($loader->getPrefixesPsr4(), __NAMESPACE__))->register();
}
