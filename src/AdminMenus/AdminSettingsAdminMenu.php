<?php

/**
 * File that holds class for admin menu - settings.
 *
 * @package EightshiftSeo\AdminMenus
 */

declare(strict_types=1);

namespace EightshiftSeo\AdminMenus;

use EightshiftSeo\Options\Options;
use EightshiftSeoVendor\EightshiftLibs\AdminMenus\AbstractAdminMenu;
use EightshiftSeoVendor\EightshiftLibs\Helpers\Helpers;

/**
 * AdminSettingsAdminMenu class.
 */
class AdminSettingsAdminMenu extends AbstractAdminMenu
{
	/**
	 * Menu slug constant used by EnqueueAdmin to detect the current page.
	 *
	 * @var string
	 */
	public const ADMIN_MENU_SLUG = 'settings';

	/**
	 * SVG icon for the admin menu entry (magnifying-glass with gear motif).
	 */
	public const ADMIN_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/><path d="M12 10h-2v2H9v-2H7V9h2V7h1v2h2v1z"/></svg>';

	/**
	 * Menu position — below second separator (below Settings menu).
	 *
	 * @var int
	 */
	public const ADMIN_MENU_POSITION = 100;

	/**
	 * Get the title to use for the admin page.
	 *
	 * @return string
	 */
	protected function getTitle(): string
	{
		return \esc_html__('Eightshift SEO', 'eightshift-seo');
	}

	/**
	 * Get the menu title to use for the admin menu.
	 *
	 * @return string
	 */
	protected function getMenuTitle(): string
	{
		return \esc_html__('Eightshift SEO', 'eightshift-seo');
	}

	/**
	 * Get the capability required for this menu to be displayed.
	 *
	 * @return string
	 */
	protected function getCapability(): string
	{
		return Options::getCap('manage');
	}

	/**
	 * Get the menu slug.
	 *
	 * @return string
	 */
	protected function getMenuSlug(): string
	{
		return Options::getAdminPageSlug(self::ADMIN_MENU_SLUG);
	}

	/**
	 * Get the base64-encoded SVG icon for the menu.
	 *
	 * @return string
	 */
	protected function getIcon(): string
	{
		return 'data:image/svg+xml;base64,' . \base64_encode(self::ADMIN_ICON); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Get the menu position number.
	 *
	 * @return int
	 */
	protected function getPosition(): int
	{
		return self::ADMIN_MENU_POSITION;
	}

	/**
	 * Get the view component that will render the admin page root.
	 *
	 * @param array<string, mixed> $attributes Processed attributes.
	 *
	 * @return string Rendered HTML.
	 */
	protected function getViewComponent(array $attributes): string
	{
		return Helpers::render('admin-settings', $attributes);
	}

	/**
	 * Process the admin menu attributes.
	 *
	 * @param array<string, mixed>|string $attr Raw admin menu attributes.
	 *
	 * @return array<string, mixed> Processed attributes.
	 */
	protected function processAttributes($attr): array
	{
		return [
			'adminSettingsTitle' => \esc_html__('Eightshift SEO', 'eightshift-seo'),
			'adminSettingsType'  => 'manage',
		];
	}
}
