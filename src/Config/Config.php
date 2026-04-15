<?php

/**
 * The file that defines the project entry point class.
 *
 * @package EightshiftSeo\Config
 */

declare(strict_types=1);

namespace EightshiftSeo\Config;

use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;

/**
 * The project config class.
 */
class Config
{
	/**
	 * Method that returns project name.
	 *
	 * Generally used for naming assets handlers, languages, etc.
	 */
	public static function getProjectName(): string
	{
		return Helpers::getPluginName();
	}

	/**
	 * Method that returns project version.
	 *
	 * Generally used for versioning asset handlers while enqueueing them.
	 */
	public static function getProjectVersion(): string
	{
		return Helpers::getPluginVersion();
	}

	/**
	 * Method that returns project text domain.
	 *
	 * Generally used for caching and translations.
	 */
	public static function getProjectTextDomain(): string
	{
		return Helpers::getPluginTextDomain();
	}

	/**
	 * Method that returns project REST-API namespace.
	 *
	 * Used for namespacing projects REST-API routes and fields.
	 *
	 * @return string Project name.
	 */
	public static function getProjectRoutesNamespace(): string
	{
		return self::getProjectTextDomain();
	}

	/**
	 * Method that returns project REST-API version.
	 *
	 * Used for versioning projects REST-API routes and fields.
	 *
	 * @return string Project route version.
	 */
	public static function getProjectRoutesVersion(): string
	{
		return 'v1';
	}

	/**
	 * Default action key used for admin notices.
	 *
	 * @return string Default action key.
	 */
	public static function getDefaultActionKey(): string
	{
		return 'es_seo_action';
	}

	/**
	 * Default nonce key used for admin notices.
	 *
	 * @return string Default nonce key.
	 */
	public static function getDefaultNonceKey(): string
	{
		return 'es_seo_nonce';
	}
}
