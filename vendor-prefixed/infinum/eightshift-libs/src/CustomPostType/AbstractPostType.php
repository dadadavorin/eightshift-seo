<?php

/**
 * File that holds base abstract class for custom post type registration.
 *
 * @package EightshiftLibs\CustomPostType
 *
 * @license MIT
 * Modified by eightshift-meilisearch on 01-April-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace EightshiftSeoVendor\EightshiftLibs\CustomPostType;

use EightshiftSeoVendor\EightshiftLibs\Services\ServiceCliInterface;
use EightshiftSeoVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Abstract class AbstractPostType class.
 */
abstract class AbstractPostType implements ServiceInterface, ServiceCliInterface
{
	/**
	 * Register custom post type.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'postTypeRegisterCallback']);
	}

	/**
	 * Method that registers post_type that is used inside init hook.
	 *
	 * @return void
	 */
	public function postTypeRegisterCallback(): void
	{
		\register_post_type(
			$this->getPostTypeSlug(),
			$this->getPostTypeArguments()
		);
	}

	/**
	 * Get the slug to use for the custom post type.
	 *
	 * @return string Custom post type slug.
	 */
	abstract protected function getPostTypeSlug(): string;

	/**
	 * Get the arguments that configure the custom post type.
	 *
	 * @return array<string, mixed> Array of arguments.
	 */
	abstract protected function getPostTypeArguments(): array;
}
